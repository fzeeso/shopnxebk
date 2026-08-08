<?php

declare(strict_types=1);

namespace App\Support\Media;

use App\Models\Brand;
use App\Support\Translations\AutomatedTranslationWriter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Models\User;
use Modules\Catalog\Services\CatalogAccessService;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Models\Store;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class BrandService
{
    public function __construct(
        private StoreContext $context,
        private CatalogAccessService $access,
        private AutomatedTranslationWriter $translations,
        private ImageService $images,
    ) {}

    /** @return LengthAwarePaginator<int, Brand> */
    public function list(User $user, int $perPage): LengthAwarePaginator
    {
        $store = $this->store($user, false);

        return Brand::query()
            ->where('store_id', $store->getKey())
            ->with(['store', 'translations', 'media'])
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function show(User $user, Brand $brand): Brand
    {
        $store = $this->store($user, false);
        $this->ensureOwned($brand, $store);

        return $brand->load(['store', 'translations', 'media']);
    }

    /** @param array<string, mixed> $data */
    public function create(User $user, array $data): Brand
    {
        $store = $this->store($user, true);

        return DB::transaction(function () use ($data, $store): Brand {
            $brand = Brand::query()->create([
                ...Arr::except($data, ['translations', 'image', 'banner']),
                'store_id' => $store->getKey(),
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);
            $this->syncTranslations($brand, $store, $data['translations']);
            $this->syncImages($brand, $data);

            return $brand->load(['store', 'translations', 'media']);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, Brand $brand, array $data): Brand
    {
        $store = $this->store($user, true);
        $this->ensureOwned($brand, $store);

        return DB::transaction(function () use ($brand, $data, $store): Brand {
            $brand->fill(Arr::except($data, ['translations', 'image', 'banner']))->save();
            if (isset($data['translations']) && is_array($data['translations'])) {
                $this->syncTranslations($brand, $store, $data['translations']);
            }
            $this->syncImages($brand, $data);

            return $brand->refresh()->load(['store', 'translations', 'media']);
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
    private function syncTranslations(Brand $brand, Store $store, array $translations): void
    {
        [$storeLocales, $defaultLocale] = $this->storeLocales($store);
        $storeLocaleKeys = array_fill_keys(array_map($this->localeKey(...), $storeLocales), true);
        $translationsByLocale = [];

        foreach ($translations as $index => $translation) {
            $locale = $this->normalizeLocale((string) $translation['locale']);
            $localeKey = $this->localeKey($locale);
            if (isset($translationsByLocale[$localeKey])) {
                throw ValidationException::withMessages(['translations' => ['Each locale may appear only once.']]);
            }
            if (! isset($storeLocaleKeys[$localeKey])) {
                throw ValidationException::withMessages([
                    "translations.{$index}.locale" => ["The locale [{$locale}] is not an active language for this Store."],
                ]);
            }

            $translation['locale'] = $locale;
            $translationsByLocale[$localeKey] = $translation;
        }

        $source = $translationsByLocale[$this->localeKey($defaultLocale)] ?? reset($translationsByLocale);
        if (! is_array($source)) {
            throw ValidationException::withMessages([
                'translations' => ['At least one Store-language translation is required.'],
            ]);
        }

        $now = now();
        $rows = [];
        $existingLocks = DB::table('brand_translations')
            ->where('brand_id', $brand->getKey())
            ->get(['locale', 'lock_it'])
            ->mapWithKeys(static fn (object $row): array => [
                Str::lower((string) $row->locale) => (bool) $row->lock_it,
            ]);

        foreach ($translationsByLocale as $localeKey => $translation) {
            if (array_key_exists('lock_it', $translation)
                && ! (bool) $translation['lock_it']
                && $existingLocks->get($localeKey, false)) {
                DB::table('brand_translations')
                    ->where('brand_id', $brand->getKey())
                    ->whereRaw('LOWER(locale) = ?', [$localeKey])
                    ->update(['lock_it' => false]);
                $existingLocks->put($localeKey, false);
            }
        }

        foreach ($storeLocales as $locale) {
            $localeKey = $this->localeKey($locale);
            $translation = $translationsByLocale[$localeKey] ?? $source;
            $slug = Str::slug((string) $translation['slug']);
            if ($slug === '') {
                throw ValidationException::withMessages([
                    'translations' => ['Every translation requires a URL-safe slug.'],
                ]);
            }
            if (! $existingLocks->get($localeKey, false) && DB::table('brand_translations')
                ->where('store_id', $store->getKey())
                ->whereRaw('LOWER(locale) = ?', [$localeKey])
                ->where('slug', $slug)
                ->where('brand_id', '<>', $brand->getKey())
                ->exists()) {
                throw ValidationException::withMessages([
                    'translations' => ["The slug [{$slug}] is already used for locale [{$locale}]."],
                ]);
            }

            $rows[] = [
                'store_id' => $store->getKey(),
                'brand_id' => $brand->getKey(),
                'locale' => $locale,
                'name' => $translation['name'],
                'slug' => $slug,
                'description' => $translation['description'] ?? null,
                'seo_title' => $translation['seo_title'] ?? null,
                'seo_description' => $translation['seo_description'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->translations->upsert(
            'brand_translations',
            $rows,
            ['brand_id', 'locale'],
            ['name', 'slug', 'description', 'seo_title', 'seo_description', 'updated_at'],
        );

        foreach ($translationsByLocale as $localeKey => $translation) {
            if (! array_key_exists('lock_it', $translation)) {
                continue;
            }

            DB::table('brand_translations')
                ->where('brand_id', $brand->getKey())
                ->whereRaw('LOWER(locale) = ?', [$localeKey])
                ->update(['lock_it' => (bool) $translation['lock_it']]);
        }
    }

    /** @param array<string, mixed> $data */
    private function syncImages(Brand $brand, array $data): void
    {
        foreach (['image' => Brand::MEDIA_IMAGE, 'banner' => Brand::MEDIA_BANNER] as $input => $collection) {
            if (! array_key_exists($input, $data)) {
                continue;
            }

            $image = $data[$input];
            if ($image !== null && ! $image instanceof UploadedFile) {
                throw new \LogicException("Validated Brand [{$input}] must be an uploaded file or null.");
            }

            $this->images->replace($brand, $collection, $image);
        }
    }

    /** @return array{list<string>, string} */
    private function storeLocales(Store $store): array
    {
        $locales = DB::table('store_languages')
            ->join('languages', 'languages.id', '=', 'store_languages.language_id')
            ->where('store_languages.store_id', $store->getKey())
            ->where('store_languages.is_active', true)
            ->where('languages.is_active', true)
            ->orderByDesc('store_languages.is_default')
            ->orderBy('languages.locale')
            ->pluck('languages.locale')
            ->map(fn (mixed $locale): string => $this->normalizeLocale((string) $locale))
            ->unique(fn (string $locale): string => $this->localeKey($locale))
            ->values()
            ->all();

        if ($locales === []) {
            $locales = [$this->normalizeLocale((string) ($store->language_code ?: config('app.locale', 'en')))];
        }

        return [$locales, $locales[0]];
    }

    private function normalizeLocale(string $locale): string
    {
        return str_replace('-', '_', trim($locale));
    }

    private function localeKey(string $locale): string
    {
        return Str::lower($this->normalizeLocale($locale));
    }
}
