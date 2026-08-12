<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use App\Models\Brand;
use App\Support\Media\BrandImageService;
use App\Support\Translations\TranslationCoordinator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Models\User;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Models\Store;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class BrandManagementService
{
    public function __construct(
        private StoreContext $context,
        private CatalogAccessService $access,
        private TranslationCoordinator $translationCoordinator,
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

        return DB::transaction(function () use ($data, $store, $user): Brand {
            $brand = Brand::query()->create([
                ...Arr::except($data, ['translations', 'image', 'banner']),
                'store_id' => $store->getKey(),
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);
            $sourceLocale = $this->syncTranslations($brand, $store, $data['translations']);
            $translationRequest = $this->translationCoordinator->request(
                store: $store,
                contentType: 'brand',
                contentId: (int) $brand->getKey(),
                expectedSourceLocale: $sourceLocale,
                requestedBy: (int) $user->getKey(),
            );
            $this->syncImages($brand, $data);

            return $brand->load(['store', 'translations', 'media'])
                ->setRelation('translationRequest', $translationRequest);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, Brand $brand, array $data): Brand
    {
        $store = $this->store($user, true);
        $this->ensureOwned($brand, $store);

        return DB::transaction(function () use ($brand, $data, $store, $user): Brand {
            $brand->fill(Arr::except($data, ['translations', 'image', 'banner']))->save();
            if (isset($data['translations']) && is_array($data['translations'])) {
                $sourceLocale = $this->syncTranslations($brand, $store, $data['translations']);
                $missingOnly = false;
            } else {
                $sourceLocale = $this->sourceLocale($brand, $store);
                $missingOnly = true;
            }
            $translationRequest = $this->translationCoordinator->request(
                store: $store,
                contentType: 'brand',
                contentId: (int) $brand->getKey(),
                expectedSourceLocale: $sourceLocale,
                missingOnly: $missingOnly,
                requestedBy: (int) $user->getKey(),
            );
            $this->syncImages($brand, $data);

            return $brand->refresh()->load(['store', 'translations', 'media'])
                ->setRelation('translationRequest', $translationRequest);
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

        $existing = DB::table('brand_translations')
            ->where('brand_id', $brand->getKey())
            ->get(['locale', 'lock_it'])
            ->mapWithKeys(static fn (object $row): array => [
                Str::lower(str_replace('-', '_', (string) $row->locale)) => $row,
            ]);
        $now = now();
        $rows = [];

        foreach ($translationsByLocale as $localeKey => $translation) {
            $locale = (string) $translation['locale'];
            $slug = Str::slug((string) $translation['slug']);
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

            $row = [
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
                    : (bool) ($existing->get($localeKey)?->lock_it ?? false),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $rows[] = $row;
        }

        DB::table('brand_translations')->upsert(
            $rows,
            ['brand_id', 'locale'],
            ['name', 'slug', 'description', 'seo_title', 'seo_description', 'lock_it', 'updated_at'],
        );

        return $this->sourceLocale($brand, $store, $sourceLocale);
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

    private function sourceLocale(Brand $brand, Store $store, ?string $fallback = null): string
    {
        [, $defaultLocale] = $this->storeLocales($store);
        $source = DB::table('brand_translations')
            ->where('brand_id', $brand->getKey())
            ->whereRaw('LOWER(locale) = ?', [$this->localeKey($defaultLocale)])
            ->first(['locale'])
            ?? DB::table('brand_translations')
                ->where('brand_id', $brand->getKey())
                ->when($fallback !== null, fn ($query) => $query->orderByRaw(
                    'CASE WHEN LOWER(locale) = ? THEN 0 ELSE 1 END',
                    [$this->localeKey((string) $fallback)],
                ))
                ->orderBy('locale')
                ->first(['locale']);

        if (! is_object($source)) {
            throw ValidationException::withMessages([
                'translations' => ['At least one Store-language translation is required.'],
            ]);
        }

        return $this->normalizeLocale((string) $source->locale);
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
