<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Models\User;
use Modules\Catalog\Models\Brand;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Models\Store;

final readonly class BrandService
{
    public function __construct(
        private StoreContext $context,
        private CatalogAccessService $access,
    ) {}

    /** @return LengthAwarePaginator<int, Brand> */
    public function list(User $user, int $perPage): LengthAwarePaginator
    {
        $store = $this->store($user, false);

        return Brand::query()
            ->where('store_id', $store->getKey())
            ->with(['store', 'translations'])
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function show(User $user, Brand $brand): Brand
    {
        $store = $this->store($user, false);
        $this->ensureOwned($brand, $store);

        return $brand->load(['store', 'translations']);
    }

    /** @param array<string, mixed> $data */
    public function create(User $user, array $data): Brand
    {
        $store = $this->store($user, true);

        return DB::transaction(function () use ($data, $store): Brand {
            $brand = Brand::query()->create([
                ...Arr::except($data, ['translations']),
                'store_id' => $store->getKey(),
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);
            $this->upsertTranslations($brand, $store, $data['translations']);

            return $brand->load(['store', 'translations']);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, Brand $brand, array $data): Brand
    {
        $store = $this->store($user, true);
        $this->ensureOwned($brand, $store);

        return DB::transaction(function () use ($brand, $data, $store): Brand {
            $brand->fill(Arr::except($data, ['translations']))->save();
            if (isset($data['translations']) && is_array($data['translations'])) {
                $this->upsertTranslations($brand, $store, $data['translations']);
            }

            return $brand->refresh()->load(['store', 'translations']);
        });
    }

    public function delete(User $user, Brand $brand): void
    {
        $store = $this->store($user, true);
        $this->ensureOwned($brand, $store);
        $brand->delete();
    }

    private function store(User $user, bool $manage): Store
    {
        $store = $this->context->require();
        $manage ? $this->access->ensureCanManageProducts($user, $store) : $this->access->ensureCanView($user, $store);

        return $store;
    }

    private function ensureOwned(Brand $brand, Store $store): void
    {
        if ($brand->store_id !== $store->getKey()) {
            abort(404);
        }
    }

    /** @param list<array<string, mixed>> $translations */
    private function upsertTranslations(Brand $brand, Store $store, array $translations): void
    {
        $now = now();
        $rows = [];
        $locales = [];
        $existingLocks = DB::table('brand_translations')
            ->where('brand_id', $brand->getKey())
            ->get(['locale', 'lock_it'])
            ->mapWithKeys(static fn (object $row): array => [
                Str::lower((string) $row->locale) => (bool) $row->lock_it,
            ]);

        foreach ($translations as $translation) {
            $locale = str_replace('-', '_', trim((string) $translation['locale']));
            $slug = Str::slug((string) $translation['slug']);
            $localeKey = Str::lower($locale);
            if (isset($locales[$localeKey])) {
                throw ValidationException::withMessages([
                    'translations' => ['Each locale may appear only once.'],
                ]);
            }
            if ($slug === '') {
                throw ValidationException::withMessages([
                    'translations' => ['Every translation requires a URL-safe slug.'],
                ]);
            }
            if (DB::table('brand_translations')
                ->where('store_id', $store->getKey())
                ->whereRaw('LOWER(locale) = ?', [$localeKey])
                ->where('slug', $slug)
                ->where('brand_id', '<>', $brand->getKey())
                ->exists()) {
                throw ValidationException::withMessages([
                    'translations' => ["The slug [{$slug}] is already used for locale [{$locale}]."],
                ]);
            }

            $locales[$localeKey] = true;
            $rows[] = [
                'store_id' => $store->getKey(),
                'brand_id' => $brand->getKey(),
                'locale' => $locale,
                'name' => $translation['name'],
                'slug' => $slug,
                'description' => $translation['description'] ?? null,
                'seo_title' => $translation['seo_title'] ?? null,
                'seo_description' => $translation['seo_description'] ?? null,
                'lock_it' => array_key_exists('lock_it', $translation)
                    ? (bool) $translation['lock_it']
                    : $existingLocks->get($localeKey, false),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('brand_translations')->upsert(
            $rows,
            ['brand_id', 'locale'],
            ['name', 'slug', 'description', 'seo_title', 'seo_description', 'lock_it', 'updated_at'],
        );
    }
}
