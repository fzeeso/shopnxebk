<?php

declare(strict_types=1);

namespace Modules\Stores\Services;

use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Authentication\Enums\AccessScope;
use Modules\Authentication\Models\User;
use Modules\Authentication\Services\ScopedRoleAssignmentService;
use Modules\Stores\Contracts\StoreProvisioner;
use Modules\Stores\Models\Store;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class PlatformMerchantService
{
    private const PROFILE_FIELDS = [
        'legal_name',
        'description',
        'email',
        'phone',
        'primary_domain',
        'industry',
        'business_type',
        'currency_code',
        'language_code',
        'timezone',
        'country_code',
    ];

    private const ADDRESS_FIELDS = [
        'store_country_code',
        'store_state',
        'store_city',
        'store_zip',
        'store_address_1',
        'store_address_2',
    ];

    public function __construct(
        private PlatformStoreAccessService $access,
        private StoreProvisioner $storeProvisioner,
        private ScopedRoleAssignmentService $roleAssignments,
    ) {}

    /** @return LengthAwarePaginator<int, Store> */
    public function list(User $actor, int $perPage = 25): LengthAwarePaginator
    {
        $this->access->ensureCanManageMerchants($actor);
        $stores = Store::query()->orderBy('name')->paginate($perPage);
        $stores->through(fn (Store $store): Store => $this->loadUsersAndRoles($store));

        return $stores;
    }

    /** @param array<string, mixed> $data */
    public function create(User $actor, array $data): Store
    {
        $this->access->ensureCanManageMerchants($actor);

        $store = DB::transaction(function () use ($data): Store {
            /** @var array{name: string, email: string, password: string} $ownerData */
            $ownerData = $data['owner'];
            /** @var array{name: string, slug: string, theme_template_key?: string, primary_domain?: string|null, email?: string|null, phone?: string|null, country_code?: string|null, store_country_code?: string|null, store_state?: string|null, store_city?: string|null, store_zip?: string|null, store_address_1?: string|null, store_address_2?: string|null} $storeData */
            $storeData = $data['store'];
            $owner = User::query()->create([
                'name' => $ownerData['name'],
                'email' => $ownerData['email'],
                'password' => $ownerData['password'],
                'scope' => AccessScope::Store,
            ]);
            $store = $this->storeProvisioner->provision($owner, $storeData['name'], $storeData['slug'], [
                'theme_template_key' => $storeData['theme_template_key'] ?? config('stores.default_theme_key', 'default'),
                'primary_domain' => $storeData['primary_domain'] ?? null,
                'contact_email' => $storeData['email'] ?? $owner->email,
                'contact_phone' => $storeData['phone'] ?? null,
                'store_country_code' => $storeData['store_country_code'] ?? $storeData['country_code'] ?? null,
                'store_state' => $storeData['store_state'] ?? null,
                'store_city' => $storeData['store_city'] ?? null,
                'store_zip' => $storeData['store_zip'] ?? null,
                'store_address_1' => $storeData['store_address_1'] ?? null,
                'store_address_2' => $storeData['store_address_2'] ?? null,
            ]);
            $profile = Arr::except(Arr::only($storeData, self::PROFILE_FIELDS), ['primary_domain']);
            if ($profile !== []) {
                $store->fill($profile)->save();
            }

            /** @var list<string> $roles */
            $roles = array_values(array_unique(['Owner', ...($data['roles'] ?? [])]));
            $this->roleAssignments->syncStoreRoles($owner, $store, $roles);

            DB::afterCommit(function () use ($owner): void {
                event(new Registered($owner));
                $owner->sendEmailVerificationNotification();
            });

            return $store;
        });

        return $this->loadUsersAndRoles($store->refresh());
    }

    public function view(User $actor, string $publicId): Store
    {
        $this->access->ensureCanManageMerchants($actor);
        $store = $this->findMerchant($publicId);

        return $this->loadUsersAndRoles($store);
    }

    /** @param array<string, mixed> $data */
    public function update(User $actor, string $publicId, array $data): Store
    {
        $this->access->ensureCanManageMerchants($actor);
        $store = $this->loadUsersAndRoles($this->findMerchant($publicId));
        $owner = $store->users->first(fn (User $user): bool => $user->roles->contains('name', 'Owner'));
        if (! $owner instanceof User) {
            throw new NotFoundHttpException('Merchant owner not found.');
        }

        /** @var array{name: string, email: string, password?: string|null} $ownerData */
        $ownerData = $data['owner'];
        /** @var array{name: string, slug: string} $storeData */
        $storeData = $data['store'];
        $emailChanged = $owner->email !== $ownerData['email'];

        DB::transaction(function () use ($emailChanged, $owner, $ownerData, $store, $storeData): void {
            $owner->name = $ownerData['name'];
            $owner->email = $ownerData['email'];
            if ($emailChanged) {
                $owner->email_verified_at = null;
            }
            if (isset($ownerData['password']) && $ownerData['password'] !== '') {
                $owner->password = $ownerData['password'];
            }
            $owner->save();

            $store->fill([
                'name' => $storeData['name'],
                'slug' => $storeData['slug'],
                ...Arr::only($storeData, [...self::PROFILE_FIELDS, 'status']),
            ])->save();

            $settings = Arr::only($storeData, self::ADDRESS_FIELDS);
            if (array_key_exists('email', $storeData)) {
                $settings['contact_email'] = $storeData['email'];
            }
            if (array_key_exists('phone', $storeData)) {
                $settings['contact_phone'] = $storeData['phone'];
            }
            if ($settings !== []) {
                $store->storeSettings()->updateOrCreate([], $settings);
            }

            if ($emailChanged) {
                DB::afterCommit(function () use ($owner): void {
                    $owner->sendEmailVerificationNotification();
                });
            }
        });

        return $this->loadUsersAndRoles($store->refresh());
    }

    /** @return list<string> */
    public function roles(User $actor): array
    {
        $this->access->ensureCanManageMerchants($actor);

        return $this->roleAssignments->storeRoleNames();
    }

    private function loadUsersAndRoles(Store $store): Store
    {
        $store->load([
            'storeSettings',
            'users' => fn ($query) => $query->orderBy('users.name')->orderBy('users.email'),
        ]);
        $previousStoreId = getPermissionsTeamId();
        setPermissionsTeamId($store->getKey());

        try {
            foreach ($store->users as $user) {
                $user->unsetRelation('roles')->load('roles');
            }
        } finally {
            setPermissionsTeamId($previousStoreId);
        }

        return $store;
    }

    private function findMerchant(string $publicId): Store
    {
        $store = Store::query()->where('public_id', $publicId)->first();
        if ($store === null) {
            throw new NotFoundHttpException('Merchant not found.');
        }

        return $store;
    }
}
