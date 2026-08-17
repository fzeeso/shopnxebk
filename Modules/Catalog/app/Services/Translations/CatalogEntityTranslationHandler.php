<?php

declare(strict_types=1);

namespace Modules\Catalog\Services\Translations;

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

abstract readonly class CatalogEntityTranslationHandler implements TranslationContentHandler
{
    public function __construct(
        private StoreTranslationLanguages $languages,
        private AutomatedTranslationWriter $writer,
    ) {}

    final public function snapshot(
        Store $store,
        int $contentId,
        TranslationSelection $selection,
    ): ?TranslationSnapshot {
        if (! DB::table($this->entityTable())
            ->where('store_id', $store->getKey())
            ->where('id', $contentId)
            ->exists()) {
            return null;
        }

        $activeLocales = $this->languages->activeFor($store)
            ->pluck('locale')
            ->map(fn (mixed $locale): string => $this->normalizeLocale((string) $locale))
            ->unique(fn (string $locale): string => $this->localeKey($locale))
            ->values();
        if ($activeLocales->isEmpty()) {
            $activeLocales = collect([
                $this->normalizeLocale((string) ($store->language_code ?: config('app.locale', 'en'))),
            ]);
        }

        $existing = DB::table($this->translationTable())
            ->where('store_id', $store->getKey())
            ->where($this->foreignKey(), $contentId)
            ->get(array_values(array_unique([
                'locale',
                'slug',
                'lock_it',
                'updated_at',
                ...$this->fields(),
            ])))
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
        $targets = collect($selection->targetLocales ?? $activeLocales->all())
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
        $sourceFields = [];
        foreach ($this->fields() as $field) {
            $value = $source->{$field};
            $sourceFields[$field] = $value === null ? null : (string) $value;
        }

        return new TranslationSnapshot(
            sourceLocale: $sourceLocale,
            sourceFields: $sourceFields,
            targetLocales: $targets,
            contentDescription: $this->contentDescription(),
            requiredFields: [$this->titleField()],
            metadata: [
                'source_slug' => (string) $source->slug,
                'source_updated_at' => (string) $source->updated_at,
                'target_revisions' => collect($targets)->mapWithKeys(fn (string $locale): array => [
                    $this->localeKey($locale) => $existing->get($this->localeKey($locale))?->updated_at,
                ])->all(),
            ],
        );
    }

    final public function apply(
        TranslationRequest $request,
        TranslationSnapshot $snapshot,
        array $translations,
    ): void {
        if (! DB::table($this->entityTable())
            ->where('store_id', $request->store_id)
            ->where('id', $request->content_id)
            ->exists()) {
            throw ValidationException::withMessages([
                'translations' => ["The {$this->contentType()} no longer exists."],
            ]);
        }

        $now = now();
        $rows = [];
        foreach ($snapshot->targetLocales as $locale) {
            $fields = $translations[$this->localeKey($locale)] ?? null;
            if (! is_array($fields)) {
                throw ValidationException::withMessages([
                    'translations' => ["Automatic {$this->contentType()} translation omitted locale [{$locale}]."],
                ]);
            }

            $row = [
                'store_id' => $request->store_id,
                $this->foreignKey() => $request->content_id,
                'locale' => $locale,
                'slug' => $this->generatedSlug(
                    $request,
                    $locale,
                    (string) $fields[$this->titleField()],
                    (string) ($snapshot->metadata['source_slug'] ?? ''),
                ),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            foreach ($this->fields() as $field) {
                $row[$field] = $fields[$field] ?? null;
            }
            $rows[] = $row;
        }

        $this->writer->upsert(
            $this->translationTable(),
            $rows,
            [$this->foreignKey(), 'locale'],
            [...$this->fields(), 'slug', 'updated_at'],
        );
    }

    abstract protected function entityTable(): string;

    abstract protected function translationTable(): string;

    abstract protected function foreignKey(): string;

    /** @return list<string> */
    abstract protected function fields(): array;

    abstract protected function titleField(): string;

    abstract protected function contentDescription(): string;

    private function generatedSlug(
        TranslationRequest $request,
        string $locale,
        string $title,
        string $fallbackSlug,
    ): string {
        $base = Str::slug($title);
        if ($base === '') {
            $base = Str::slug($fallbackSlug);
        }
        if ($base === '') {
            $base = $this->contentType();
        }

        $base = Str::limit($base, 240, '');
        $candidate = $base;
        $suffix = 2;
        while (DB::table($this->translationTable())
            ->where('store_id', $request->store_id)
            ->whereRaw('LOWER(locale) = ?', [$this->localeKey($locale)])
            ->where('slug', $candidate)
            ->where($this->foreignKey(), '<>', $request->content_id)
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
