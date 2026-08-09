<?php

declare(strict_types=1);

namespace App\Support\Media;

use App\Models\Brand;
use App\Support\Translations\AutomatedTranslationWriter;
use App\Support\Translations\OpenAiTranslationException;
use App\Support\Translations\TranslationProvider;
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

final readonly class BrandManagementService
{
    public function __construct(
        private StoreContext $context,
        private CatalogAccessService $access,
        private AutomatedTranslationWriter $translations,
        private TranslationProvider $machineTranslations,
        private BrandImageService $images,
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
            $sourceLocale = $this->syncTranslations($brand, $store, $data['translations']);
            $this->refreshAutomaticTranslations($brand, $store, $sourceLocale);
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
                $sourceLocale = $this->syncTranslations($brand, $store, $data['translations']);
                $this->refreshAutomaticTranslations($brand, $store, $sourceLocale);
            } else {
                [$sourceLocale, $missingLocales] = $this->ensureMissingTranslations($brand, $store);
                $this->refreshAutomaticTranslations($brand, $store, $sourceLocale, $missingLocales);
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
    private function syncTranslations(Brand $brand, Store $store, array $translations): string
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

        $defaultLocaleKey = $this->localeKey($defaultLocale);
        $source = $translationsByLocale[$defaultLocaleKey] ?? null;
        $sourceLocale = $defaultLocale;

        if (! is_array($source)) {
            $savedSource = DB::table('brand_translations')
                ->where('brand_id', $brand->getKey())
                ->whereRaw('LOWER(locale) = ?', [$defaultLocaleKey])
                ->first(['locale', 'name', 'slug', 'description', 'seo_title', 'seo_description']);

            if (is_object($savedSource)) {
                $source = (array) $savedSource;
                $sourceLocale = (string) $savedSource->locale;
            } else {
                $source = reset($translationsByLocale);
                if (is_array($source)) {
                    $sourceLocale = (string) $source['locale'];
                }
            }
        }
        if (! is_array($source)) {
            throw ValidationException::withMessages([
                'translations' => ['At least one Store-language translation is required.'],
            ]);
        }

        $now = now();
        $rows = [];
        $rowsByLocale = [];
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
            $isManualLocked = isset($translationsByLocale[$localeKey])
                && (bool) ($translationsByLocale[$localeKey]['lock_it'] ?? false);
            $slug = Str::slug((string) $translation['slug']);
            if ($slug === '') {
                throw ValidationException::withMessages([
                    'translations' => ['Every translation requires a URL-safe slug.'],
                ]);
            }
            if ((! $existingLocks->get($localeKey, false) || $isManualLocked) && DB::table('brand_translations')
                ->where('store_id', $store->getKey())
                ->whereRaw('LOWER(locale) = ?', [$localeKey])
                ->where('slug', $slug)
                ->where('brand_id', '<>', $brand->getKey())
                ->exists()) {
                throw ValidationException::withMessages([
                    'translations' => ["The slug [{$slug}] is already used for locale [{$locale}]."],
                ]);
            }

            $row = [
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
            $rows[] = $row;
            $rowsByLocale[$localeKey] = $row;
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

            if ((bool) $translation['lock_it']) {
                DB::table('brand_translations')->upsert(
                    [[...$rowsByLocale[$localeKey], 'lock_it' => true]],
                    ['brand_id', 'locale'],
                    ['name', 'slug', 'description', 'seo_title', 'seo_description', 'lock_it', 'updated_at'],
                );

                continue;
            }

            DB::table('brand_translations')
                ->where('brand_id', $brand->getKey())
                ->whereRaw('LOWER(locale) = ?', [$localeKey])
                ->update(['lock_it' => false]);
        }

        return $this->normalizeLocale($sourceLocale);
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

    /**
     * @param  list<string>|null  $targetLocales
     */
    private function refreshAutomaticTranslations(
        Brand $brand,
        Store $store,
        string $sourceLocale,
        ?array $targetLocales = null,
    ): void {
        [$storeLocales] = $this->storeLocales($store);
        $storeLocalesByKey = [];
        foreach ($storeLocales as $locale) {
            $storeLocalesByKey[$this->localeKey($locale)] = $locale;
        }

        $sourceLocale = $this->normalizeLocale($sourceLocale);
        $sourceLocaleKey = $this->localeKey($sourceLocale);
        $source = DB::table('brand_translations')
            ->where('brand_id', $brand->getKey())
            ->whereRaw('LOWER(locale) = ?', [$sourceLocaleKey])
            ->first(['name', 'slug', 'description', 'seo_title', 'seo_description']);

        if (! is_object($source)) {
            throw ValidationException::withMessages([
                'translations' => ['The source Brand translation could not be found.'],
            ]);
        }

        $lockedLocales = DB::table('brand_translations')
            ->where('brand_id', $brand->getKey())
            ->where('lock_it', true)
            ->pluck('locale')
            ->mapWithKeys(fn (mixed $locale): array => [$this->localeKey((string) $locale) => true]);
        $candidateLocales = $targetLocales ?? $storeLocales;
        $automaticLocales = [];

        foreach ($candidateLocales as $locale) {
            $localeKey = $this->localeKey($locale);
            if ($localeKey === $sourceLocaleKey
                || ! isset($storeLocalesByKey[$localeKey])
                || $lockedLocales->has($localeKey)) {
                continue;
            }

            $automaticLocales[$localeKey] = $storeLocalesByKey[$localeKey];
        }

        if ($automaticLocales === []) {
            return;
        }

        try {
            $translated = $this->machineTranslations->translateFields([
                'name' => (string) $source->name,
                'description' => $source->description !== null ? (string) $source->description : null,
                'seo_title' => $source->seo_title !== null ? (string) $source->seo_title : null,
                'seo_description' => $source->seo_description !== null ? (string) $source->seo_description : null,
            ], $sourceLocale, array_values($automaticLocales), 'ecommerce Brand metadata', ['name']);
        } catch (OpenAiTranslationException) {
            throw ValidationException::withMessages([
                'translations' => ['Automatic Brand translation failed. Please try again.'],
            ]);
        }

        $now = now();
        $rows = [];
        foreach ($automaticLocales as $localeKey => $locale) {
            $translation = $translated[$localeKey] ?? null;
            if (! is_array($translation)) {
                throw ValidationException::withMessages([
                    'translations' => ["Automatic Brand translation omitted locale [{$locale}]."],
                ]);
            }

            $rows[] = [
                'store_id' => $store->getKey(),
                'brand_id' => $brand->getKey(),
                'locale' => $locale,
                'name' => $translation['name'],
                'slug' => $this->generatedSlug(
                    $brand,
                    $store,
                    $locale,
                    $translation['name'],
                    (string) $source->slug,
                ),
                'description' => $translation['description'],
                'seo_title' => $translation['seo_title'],
                'seo_description' => $translation['seo_description'],
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
    }

    /** @return array{string, list<string>} */
    private function ensureMissingTranslations(Brand $brand, Store $store): array
    {
        [$storeLocales, $defaultLocale] = $this->storeLocales($store);
        $existing = DB::table('brand_translations')
            ->where('brand_id', $brand->getKey())
            ->get([
                'locale',
                'name',
                'slug',
                'description',
                'seo_title',
                'seo_description',
            ])
            ->keyBy(fn (object $row): string => $this->localeKey((string) $row->locale));
        $source = $existing->get($this->localeKey($defaultLocale)) ?? $existing->first();

        if (! is_object($source)) {
            throw ValidationException::withMessages([
                'translations' => ['At least one Store-language translation is required.'],
            ]);
        }

        $now = now();
        $rows = [];
        $missingLocales = [];

        foreach ($storeLocales as $locale) {
            $localeKey = $this->localeKey($locale);
            if ($existing->has($localeKey)) {
                continue;
            }

            $rows[] = [
                'store_id' => $store->getKey(),
                'brand_id' => $brand->getKey(),
                'locale' => $locale,
                'name' => $source->name,
                'slug' => $this->generatedSlug(
                    $brand,
                    $store,
                    $locale,
                    (string) $source->name,
                    (string) $source->slug,
                ),
                'description' => $source->description,
                'seo_title' => $source->seo_title,
                'seo_description' => $source->seo_description,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $missingLocales[] = $locale;
        }

        $this->translations->upsert(
            'brand_translations',
            $rows,
            ['brand_id', 'locale'],
            ['name', 'slug', 'description', 'seo_title', 'seo_description', 'updated_at'],
        );

        return [$this->normalizeLocale((string) $source->locale), $missingLocales];
    }

    private function generatedSlug(
        Brand $brand,
        Store $store,
        string $locale,
        string $name,
        string $fallbackSlug,
    ): string {
        $base = Str::slug($name);
        if ($base === '') {
            $base = Str::slug($fallbackSlug);
        }
        if ($base === '') {
            $base = 'brand';
        }

        $base = Str::limit($base, 240, '');
        $candidate = $base;
        $suffix = 2;
        $localeKey = $this->localeKey($locale);

        while (DB::table('brand_translations')
            ->where('store_id', $store->getKey())
            ->whereRaw('LOWER(locale) = ?', [$localeKey])
            ->where('slug', $candidate)
            ->where('brand_id', '<>', $brand->getKey())
            ->exists()) {
            $candidate = Str::limit($base, 240, '').'-'.$suffix;
            $suffix++;
        }

        return $candidate;
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
