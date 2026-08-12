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
use Modules\Stores\Models\PolicyVersion;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StorePolicy;
use Modules\Stores\Models\StorePolicyTranslation;

final readonly class StorePolicyTranslationHandler implements TranslationContentHandler
{
    public function __construct(
        private StoreTranslationLanguages $languages,
        private AutomatedTranslationWriter $writer,
    ) {}

    public function contentType(): string
    {
        return 'store_policy';
    }

    public function snapshot(
        Store $store,
        int $contentId,
        TranslationSelection $selection,
    ): ?TranslationSnapshot {
        $policy = StorePolicy::query()
            ->withoutGlobalScopes()
            ->where('store_id', $store->getKey())
            ->find($contentId);
        if (! $policy instanceof StorePolicy) {
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

        $source = StorePolicyTranslation::query()
            ->where('store_policy_id', $policy->getKey())
            ->where('language_id', (int) $defaultLanguage->id)
            ->first();
        if (! $source instanceof StorePolicyTranslation) {
            return null;
        }

        $existing = StorePolicyTranslation::query()
            ->where('store_policy_id', $policy->getKey())
            ->get(['language_id', 'lock_it', 'updated_at'])
            ->keyBy(fn (StorePolicyTranslation $row): int => (int) $row->language_id);
        $languagesByKey = $languages->mapWithKeys(fn (object $row): array => [
            $this->localeKey((string) $row->locale) => $row,
        ]);
        $candidates = collect($selection->targetLocales ?? $languages->pluck('locale')->all());
        $targets = $candidates
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
                'content' => (string) $source->content,
                'seo_title' => $source->seo_title !== null ? (string) $source->seo_title : null,
                'seo_description' => $source->seo_description !== null ? (string) $source->seo_description : null,
            ],
            targetLocales: $targets,
            contentDescription: 'ecommerce Store policy text',
            requiredFields: ['title', 'content'],
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
        $policy = StorePolicy::query()
            ->withoutGlobalScopes()
            ->where('store_id', $request->store_id)
            ->whereKey($request->content_id)
            ->lockForUpdate()
            ->firstOrFail();
        $languages = $this->languages->activeFor(Store::query()->findOrFail($request->store_id));
        $languagesByKey = $languages->mapWithKeys(fn (object $row): array => [
            $this->localeKey((string) $row->locale) => $row,
        ]);
        $targetLanguageIds = collect($snapshot->targetLocales)
            ->map(fn (string $locale): ?int => is_object($languagesByKey->get($this->localeKey($locale)))
                ? (int) $languagesByKey->get($this->localeKey($locale))->id
                : null)
            ->filter()
            ->values();
        $existing = StorePolicyTranslation::query()
            ->where('store_policy_id', $policy->getKey())
            ->whereIn('language_id', $targetLanguageIds)
            ->get()
            ->keyBy(fn (StorePolicyTranslation $row): int => (int) $row->language_id);
        $now = now();
        $rows = [];

        foreach ($snapshot->targetLocales as $locale) {
            $localeKey = $this->localeKey($locale);
            $language = $languagesByKey->get($localeKey);
            $fields = $translations[$localeKey] ?? null;
            if (! is_object($language) || ! is_array($fields)) {
                throw ValidationException::withMessages([
                    'translation' => ["Automatic policy translation omitted locale [{$locale}]."],
                ]);
            }

            $previous = $existing->get((int) $language->id);
            if ((bool) ($previous?->lock_it ?? false)) {
                continue;
            }
            $rows[] = [
                'public_id' => (string) ($previous?->public_id ?? Str::ulid()),
                'store_policy_id' => $policy->getKey(),
                'language_id' => (int) $language->id,
                'title' => $fields['title'],
                'content' => $fields['content'],
                'seo_title' => $fields['seo_title'],
                'seo_description' => $fields['seo_description'],
                'created_at' => $previous?->created_at ?? $now,
                'updated_at' => $now,
            ];
        }

        $this->writer->upsert(
            'store_policy_translations',
            $rows,
            ['store_policy_id', 'language_id'],
            ['title', 'content', 'seo_title', 'seo_description', 'updated_at'],
        );

        foreach ($rows as $row) {
            $previous = $existing->get((int) $row['language_id']);
            if ($previous !== null && $previous->content === $row['content']) {
                continue;
            }

            $version = ((int) PolicyVersion::query()
                ->where('store_policy_id', $policy->getKey())
                ->where('language_id', $row['language_id'])
                ->max('version')) + 1;
            PolicyVersion::query()->create([
                'store_policy_id' => $policy->getKey(),
                'language_id' => $row['language_id'],
                'version' => $version,
                'content' => $row['content'],
                'created_by' => $request->requested_by,
            ]);
        }
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
