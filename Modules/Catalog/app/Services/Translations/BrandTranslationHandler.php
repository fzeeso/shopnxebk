<?php

declare(strict_types=1);

namespace Modules\Catalog\Services\Translations;

use App\Models\Brand;
use App\Models\TranslationRequest;
use App\Support\Translations\AutomatedTranslationWriter;
use App\Support\Translations\Contracts\TranslationContentHandler;
use App\Support\Translations\StoreTranslationLanguages;
use App\Support\Translations\TranslationSelection;
use App\Support\Translations\TranslationSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Stores\Models\Store;

final readonly class BrandTranslationHandler implements TranslationContentHandler
{
    public function __construct(
        private StoreTranslationLanguages $languages,
        private AutomatedTranslationWriter $writer,
    ) {}

    public function contentType(): string
    {
        return 'brand';
    }

    public function snapshot(
        Store $store,
        int $contentId,
        TranslationSelection $selection,
    ): ?TranslationSnapshot {
        $brand = Brand::query()
            ->withoutGlobalScopes()
            ->where('store_id', $store->getKey())
            ->find($contentId);
        if (! $brand instanceof Brand) {
            return null;
        }

        $activeLocales = $this->languages->activeFor($store)
            ->pluck('locale')
            ->map(fn (mixed $locale): string => $this->normalizeLocale((string) $locale))
            ->unique(fn (string $locale): string => $this->localeKey($locale))
            ->values();
        if ($activeLocales->isEmpty()) {
            $activeLocales = collect([$this->normalizeLocale((string) ($store->language_code ?: config('app.locale', 'en')))]);
        }

        $existing = DB::table('brand_translations')
            ->where('store_id', $store->getKey())
            ->where('brand_id', $brand->getKey())
            ->get(['locale', 'name', 'slug', 'description', 'seo_title', 'seo_description', 'lock_it', 'updated_at'])
            ->keyBy(fn (object $row): string => $this->localeKey((string) $row->locale));
        $expectedSourceKey = $selection->expectedSourceLocale === null
            ? null
            : $this->localeKey($selection->expectedSourceLocale);
        $defaultLocaleKey = $this->localeKey((string) $activeLocales->first());
        $source = $expectedSourceKey === null
            ? ($existing->get($defaultLocaleKey) ?? $existing->first())
            : $existing->get($expectedSourceKey);

        if (! is_object($source)) {
            return null;
        }

        $sourceLocale = $this->normalizeLocale((string) $source->locale);
        $sourceLocaleKey = $this->localeKey($sourceLocale);
        $activeByKey = $activeLocales->mapWithKeys(fn (string $locale): array => [
            $this->localeKey($locale) => $locale,
        ]);
        $candidates = collect($selection->targetLocales ?? $activeLocales->all());
        $targets = $candidates
            ->map(fn (mixed $locale): string => $this->normalizeLocale((string) $locale))
            ->filter(function (string $locale) use ($activeByKey, $existing, $selection, $sourceLocaleKey): bool {
                $key = $this->localeKey($locale);
                $row = $existing->get($key);

                return $key !== $sourceLocaleKey
                    && $activeByKey->has($key)
                    && ! (bool) ($row->lock_it ?? false)
                    && (! $selection->missingOnly || $row === null);
            })
            ->map(fn (string $locale): string => (string) $activeByKey->get($this->localeKey($locale)))
            ->unique(fn (string $locale): string => $this->localeKey($locale))
            ->values()
            ->all();

        return new TranslationSnapshot(
            sourceLocale: $sourceLocale,
            sourceFields: [
                'name' => (string) $source->name,
                'description' => $source->description !== null ? (string) $source->description : null,
                'seo_title' => $source->seo_title !== null ? (string) $source->seo_title : null,
                'seo_description' => $source->seo_description !== null ? (string) $source->seo_description : null,
            ],
            targetLocales: $targets,
            contentDescription: 'ecommerce Brand metadata',
            requiredFields: ['name'],
            metadata: [
                'source_slug' => (string) $source->slug,
                'source_updated_at' => (string) $source->updated_at,
                'target_revisions' => collect($targets)->mapWithKeys(fn (string $locale): array => [
                    $this->localeKey($locale) => $existing->get($this->localeKey($locale))?->updated_at,
                ])->all(),
            ],
        );
    }

    public function apply(
        TranslationRequest $request,
        TranslationSnapshot $snapshot,
        array $translations,
    ): void {
        $brand = Brand::query()
            ->withoutGlobalScopes()
            ->where('store_id', $request->store_id)
            ->findOrFail($request->content_id);
        $store = Store::query()->findOrFail($request->store_id);
        $now = now();
        $rows = [];

        foreach ($snapshot->targetLocales as $locale) {
            $key = $this->localeKey($locale);
            $fields = $translations[$key] ?? null;
            if (! is_array($fields)) {
                throw ValidationException::withMessages([
                    'translations' => ["Automatic Brand translation omitted locale [{$locale}]."],
                ]);
            }

            $rows[] = [
                'store_id' => $store->getKey(),
                'brand_id' => $brand->getKey(),
                'locale' => $locale,
                'name' => $fields['name'],
                'slug' => $this->generatedSlug(
                    $brand,
                    $store,
                    $locale,
                    (string) $fields['name'],
                    (string) ($snapshot->metadata['source_slug'] ?? ''),
                ),
                'description' => $fields['description'],
                'seo_title' => $fields['seo_title'],
                'seo_description' => $fields['seo_description'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->writer->upsert(
            'brand_translations',
            $rows,
            ['brand_id', 'locale'],
            ['name', 'slug', 'description', 'seo_title', 'seo_description', 'updated_at'],
        );
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

    private function normalizeLocale(string $locale): string
    {
        return str_replace('-', '_', trim($locale));
    }

    private function localeKey(string $locale): string
    {
        return Str::lower($this->normalizeLocale($locale));
    }
}
