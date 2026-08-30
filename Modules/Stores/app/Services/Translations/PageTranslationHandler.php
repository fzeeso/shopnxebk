<?php

declare(strict_types=1);

namespace Modules\Stores\Services\Translations;

use App\Models\TranslationRequest;
use App\Support\Translations\AutomatedTranslationWriter;
use App\Support\Translations\Contracts\TranslationContentHandler;
use App\Support\Translations\StoreTranslationLanguages;
use App\Support\Translations\TranslationSelection;
use App\Support\Translations\TranslationSnapshot;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Stores\Models\Page;
use Modules\Stores\Models\PageTranslation;
use Modules\Stores\Models\Store;

final readonly class PageTranslationHandler implements TranslationContentHandler
{
    public function __construct(
        private StoreTranslationLanguages $languages,
        private AutomatedTranslationWriter $writer,
    ) {}

    public function contentType(): string
    {
        return 'page';
    }

    public function snapshot(
        Store $store,
        int $contentId,
        TranslationSelection $selection,
    ): ?TranslationSnapshot {
        $page = Page::query()
            ->withoutGlobalScopes()
            ->where('store_id', $store->getKey())
            ->find($contentId);
        if (! $page instanceof Page) {
            return null;
        }

        $languages = $this->languages->activeFor($store);
        $defaultLanguage = $languages->first(fn (object $row): bool => (bool) $row->is_default)
            ?? $languages->first();
        if (! is_object($defaultLanguage)) {
            return null;
        }

        $defaultLocale = $this->normalizeLocale((string) $defaultLanguage->locale);
        if ($selection->expectedSourceLocale !== null
            && $this->localeKey($selection->expectedSourceLocale) !== $this->localeKey($defaultLocale)) {
            return null;
        }

        $source = PageTranslation::query()
            ->where('page_id', $page->getKey())
            ->where('language_id', (int) $defaultLanguage->id)
            ->first();
        if (! $source instanceof PageTranslation) {
            return null;
        }

        $existing = PageTranslation::query()
            ->where('page_id', $page->getKey())
            ->get(['language_id', 'lock_it', 'updated_at'])
            ->keyBy(fn (PageTranslation $row): int => (int) $row->language_id);
        $languagesByKey = $languages->mapWithKeys(fn (object $row): array => [
            $this->localeKey((string) $row->locale) => $row,
        ]);
        $targets = collect($selection->targetLocales ?? $languages->pluck('locale')->all())
            ->map(fn (mixed $locale): string => $this->normalizeLocale((string) $locale))
            ->filter(function (string $locale) use ($defaultLanguage, $existing, $languagesByKey, $selection): bool {
                $language = $languagesByKey->get($this->localeKey($locale));
                if (! is_object($language) || (int) $language->id === (int) $defaultLanguage->id) {
                    return false;
                }
                $translation = $existing->get((int) $language->id);

                return ! (bool) ($translation?->lock_it ?? false)
                    && (! $selection->missingOnly || $translation === null);
            })
            ->map(fn (string $locale): string => (string) $languagesByKey->get($this->localeKey($locale))->locale)
            ->unique(fn (string $locale): string => $this->localeKey($locale))
            ->values()
            ->all();

        return new TranslationSnapshot(
            sourceLocale: $defaultLocale,
            sourceFields: [
                'title' => (string) $source->title,
                'content' => $source->content !== null ? (string) $source->content : null,
                'summary' => $source->summary !== null ? (string) $source->summary : null,
                'seo_title' => $source->seo_title !== null ? (string) $source->seo_title : null,
                'seo_description' => $source->seo_description !== null ? (string) $source->seo_description : null,
                'seo_keywords' => $source->seo_keywords !== null ? (string) $source->seo_keywords : null,
                'search_keywords' => $source->search_keywords !== null ? (string) $source->search_keywords : null,
            ],
            targetLocales: $targets,
            contentDescription: 'ecommerce Store page content and SEO metadata',
            requiredFields: ['title'],
            metadata: [
                'source_language_id' => (int) $defaultLanguage->id,
                'source_updated_at' => $source->updated_at?->toIso8601String(),
                'target_revisions' => collect($targets)->mapWithKeys(function (string $locale) use ($existing, $languagesByKey): array {
                    $language = $languagesByKey->get($this->localeKey($locale));
                    $translation = is_object($language) ? $existing->get((int) $language->id) : null;

                    return [$this->localeKey($locale) => $translation?->updated_at?->toIso8601String()];
                })->all(),
            ],
        );
    }

    public function apply(
        TranslationRequest $request,
        TranslationSnapshot $snapshot,
        array $translations,
    ): void {
        $page = Page::query()
            ->withoutGlobalScopes()
            ->where('store_id', $request->store_id)
            ->whereKey($request->content_id)
            ->lockForUpdate()
            ->firstOrFail();
        $store = Store::query()->findOrFail($request->store_id);
        $languages = $this->languages->activeFor($store);
        $languagesByKey = $languages->mapWithKeys(fn (object $row): array => [
            $this->localeKey((string) $row->locale) => $row,
        ]);
        $targetLanguageIds = collect($snapshot->targetLocales)
            ->map(fn (string $locale): ?int => is_object($languagesByKey->get($this->localeKey($locale)))
                ? (int) $languagesByKey->get($this->localeKey($locale))->id
                : null)
            ->filter()
            ->values();
        $existing = PageTranslation::query()
            ->where('page_id', $page->getKey())
            ->whereIn('language_id', $targetLanguageIds)
            ->get()
            ->keyBy(fn (PageTranslation $row): int => (int) $row->language_id);
        $now = now();
        $rows = [];

        foreach ($snapshot->targetLocales as $locale) {
            $localeKey = $this->localeKey($locale);
            $language = $languagesByKey->get($localeKey);
            $fields = $translations[$localeKey] ?? null;
            if (! is_object($language) || ! is_array($fields)) {
                throw ValidationException::withMessages([
                    'translation' => ["Automatic page translation omitted locale [{$locale}]."],
                ]);
            }

            $previous = $existing->get((int) $language->id);
            if ((bool) ($previous?->lock_it ?? false)) {
                continue;
            }
            $rows[] = [
                'public_id' => (string) ($previous?->public_id ?? Str::ulid()),
                'store_id' => $store->getKey(),
                'page_id' => $page->getKey(),
                'language_id' => (int) $language->id,
                'title' => $fields['title'],
                'slug' => $this->uniqueSlug(
                    $store,
                    $page,
                    (int) $language->id,
                    (string) $fields['title'],
                    $localeKey,
                ),
                'content' => $fields['content'],
                'summary' => $fields['summary'],
                'seo_title' => $fields['seo_title'],
                'seo_description' => $fields['seo_description'],
                'seo_keywords' => $fields['seo_keywords'],
                'search_keywords' => $fields['search_keywords'],
                'created_at' => $previous?->created_at ?? $now,
                'updated_at' => $now,
            ];
        }

        $this->writer->upsert(
            'page_translations',
            $rows,
            ['page_id', 'language_id'],
            [
                'title',
                'slug',
                'content',
                'summary',
                'seo_title',
                'seo_description',
                'seo_keywords',
                'search_keywords',
                'updated_at',
            ],
        );
    }

    private function uniqueSlug(
        Store $store,
        Page $page,
        int $languageId,
        string $title,
        string $localeKey,
    ): string {
        $base = Str::slug($title);
        if ($base === '') {
            $base = "page-{$page->getKey()}-".Str::slug($localeKey);
        }
        $slug = Str::limit($base, 230, '');
        $suffix = 2;
        while (PageTranslation::query()
            ->where('store_id', $store->getKey())
            ->where('language_id', $languageId)
            ->whereRaw('LOWER(slug) = ?', [Str::lower($slug)])
            ->where('page_id', '<>', $page->getKey())
            ->exists()) {
            $slug = Str::limit($base, 220, '').'-'.$suffix;
            $suffix++;
        }

        return Str::lower($slug);
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
