<?php

declare(strict_types=1);

namespace Modules\Stores\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Authentication\Models\User;
use Modules\Stores\Actions\EnsureStorePolicyCatalog;
use Modules\Stores\Enums\StoreStatus;
use Modules\Stores\Models\Store;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class PlatformStoreAdminService
{
    private const WRITABLE_FIELDS = [
        'name', 'legal_name', 'description', 'email', 'phone', 'slug', 'status',
        'primary_domain', 'logo', 'favicon', 'cover_image', 'industry', 'business_type',
        'currency_code', 'language_code', 'timezone', 'country_code', 'is_verified',
        'is_ai_enabled', 'is_pos_enabled', 'is_b2b_enabled', 'is_marketplace_enabled',
        'launched_at', 'trial_ends_at',
    ];

    private const BOOLEAN_FILTERS = [
        'is_verified', 'is_ai_enabled', 'is_pos_enabled', 'is_b2b_enabled',
        'is_marketplace_enabled',
    ];

    private const LOCALE_SETTING_FIELDS = [
        'date_format', 'time_format', 'week_starts_on', 'weight_unit',
        'dimension_unit', 'decimal_places', 'decimal_separator', 'thousands_separator',
    ];

    public function __construct(
        private PlatformStoreAccessService $access,
        private StoreDomainManager $domains,
        private EnsureStorePolicyCatalog $storePolicies,
    ) {}

    public function authorizeCreation(User $actor): void
    {
        $this->access->ensureCanManageStores($actor);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<Store>
     */
    public function paginate(User $actor, array $filters): LengthAwarePaginator
    {
        $this->access->ensureCanManageStores($actor);
        $query = Store::query()->with('primaryMembership.user');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $pattern = '%'.addcslashes($search, '\\%_').'%';
            $query->where(function (Builder $query) use ($pattern): void {
                $query->where('name', 'ilike', $pattern)
                    ->orWhere('legal_name', 'ilike', $pattern)
                    ->orWhere('slug', 'ilike', $pattern)
                    ->orWhere('email', 'ilike', $pattern)
                    ->orWhere('primary_domain', 'ilike', $pattern)
                    ->orWhereHas('memberships.user', function (Builder $query) use ($pattern): void {
                        $query->where('users.name', 'ilike', $pattern)
                            ->orWhere('users.email', 'ilike', $pattern);
                    });
            });
        }

        foreach (['status', 'business_type', 'currency_code', 'language_code', 'country_code'] as $field) {
            if (array_key_exists($field, $filters)) {
                $query->where($field, $filters[$field]);
            }
        }

        foreach (self::BOOLEAN_FILTERS as $field) {
            if (array_key_exists($field, $filters)) {
                $query->where($field, (bool) $filters[$field]);
            }
        }

        if (isset($filters['created_from'])) {
            $query->whereDate('created_at', '>=', (string) $filters['created_from']);
        }
        if (isset($filters['created_to'])) {
            $query->whereDate('created_at', '<=', (string) $filters['created_to']);
        }

        $sort = (string) ($filters['sort'] ?? 'created_at');
        $direction = (string) ($filters['direction'] ?? 'desc');
        $query->orderBy($sort, $direction)->orderBy('id', $direction);

        return $query->paginate(
            perPage: (int) ($filters['per_page'] ?? 10),
            page: (int) ($filters['page'] ?? 1),
        );
    }

    /** @param array<string, mixed> $data */
    public function create(User $actor, array $data): Store
    {
        $this->authorizeCreation($actor);

        return DB::transaction(function () use ($actor, $data): Store {
            $attributes = Arr::only($data, self::WRITABLE_FIELDS);
            $localeSettings = is_array($data['locale_settings'] ?? null)
                ? Arr::only($data['locale_settings'], self::LOCALE_SETTING_FIELDS)
                : [];
            $primaryDomain = array_key_exists('primary_domain', $attributes)
                ? $attributes['primary_domain']
                : null;
            unset($attributes['primary_domain']);
            $attributes['legal_name'] = filled($attributes['legal_name'] ?? null)
                ? $attributes['legal_name']
                : $attributes['name'];
            $attributes['status'] = $attributes['status'] ?? StoreStatus::Draft;
            $attributes['settings'] = Arr::only($localeSettings, [
                'date_format', 'time_format', 'weight_unit', 'dimension_unit',
            ]);
            $attributes['metadata'] = [];

            $store = Store::query()->create($attributes);
            $store->localeSettings()->create($localeSettings);
            $store->storeSettings()->create([
                'contact_email' => $store->email,
                'contact_phone' => $store->phone,
                'weight_unit' => $localeSettings['weight_unit'] ?? 'kg',
                'social_links' => [],
                'extra_settings' => [],
            ]);
            $this->domains->initialize($store, is_string($primaryDomain) ? $primaryDomain : null);
            $this->storePolicies->ensureForStore($store, $actor);

            return $store->refresh();
        });
    }

    public function view(User $actor, string $publicId): Store
    {
        $this->access->ensureCanManageStores($actor);

        return $this->find($publicId);
    }

    /** @param array<string, mixed> $data */
    public function update(User $actor, string $publicId, array $data): Store
    {
        $this->access->ensureCanManageStores($actor);
        $store = $this->find($publicId);

        return DB::transaction(function () use ($data, $store): Store {
            $attributes = Arr::only($data, self::WRITABLE_FIELDS);
            $updatesPrimaryDomain = array_key_exists('primary_domain', $attributes);
            $primaryDomain = $updatesPrimaryDomain ? $attributes['primary_domain'] : null;
            unset($attributes['primary_domain']);
            if ($attributes !== []) {
                $store->fill($attributes)->save();
            }
            if ($updatesPrimaryDomain) {
                $this->domains->syncPrimaryDomain(
                    $store,
                    is_string($primaryDomain) ? $primaryDomain : null,
                );
            }

            return $store->refresh();
        });
    }

    private function find(string $publicId): Store
    {
        $store = Store::query()->where('public_id', $publicId)->first();
        if (! $store instanceof Store) {
            throw new NotFoundHttpException('Store not found.');
        }

        return $store;
    }
}
