<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use App\Support\Translations\StoreTranslationLanguages;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Stores\Models\Store;

final readonly class CatalogTranslationManager
{
    public function __construct(private StoreTranslationLanguages $languages) {}

    /**
     * @param  list<array<string, mixed>>  $translations
     * @param  list<string>  $fields
     * @param  list<string>  $requiredFields
     */
    public function sync(
        Store $store,
        string $table,
        string $ownerKey,
        int $ownerId,
        array $translations,
        array $fields,
        array $requiredFields,
    ): string {
        [$storeLocales, $defaultLocale] = $this->storeLocales($store);
        $activeLocaleKeys = array_fill_keys(array_map($this->localeKey(...), $storeLocales), true);
        $byLocale = [];

        foreach ($translations as $index => $translation) {
            $locale = $this->normalizeLocale((string) ($translation['locale'] ?? ''));
            $localeKey = $this->localeKey($locale);
            if ($locale === '' || isset($byLocale[$localeKey])) {
                throw ValidationException::withMessages([
                    'input.translations' => ['Each translation must use one unique locale.'],
                ]);
            }
            if (! isset($activeLocaleKeys[$localeKey])) {
                throw ValidationException::withMessages([
                    "input.translations.{$index}.locale" => ["The locale [{$locale}] is not active for this Store."],
                ]);
            }

            foreach ($requiredFields as $field) {
                if (trim((string) ($translation[$field] ?? '')) === '') {
                    throw ValidationException::withMessages([
                        "input.translations.{$index}.{$field}" => ["The {$field} field is required."],
                    ]);
                }
            }

            $translation['locale'] = $locale;
            $byLocale[$localeKey] = $translation;
        }

        if ($byLocale === []) {
            throw ValidationException::withMessages([
                'input.translations' => ['At least one Store-language translation is required.'],
            ]);
        }

        $existing = DB::table($table)
            ->where($ownerKey, $ownerId)
            ->get(['locale', 'lock_it'])
            ->keyBy(fn (object $row): string => $this->localeKey((string) $row->locale));
        $now = now();
        $rows = [];

        foreach ($byLocale as $localeKey => $translation) {
            $locale = (string) $translation['locale'];
            $slug = Str::slug((string) ($translation['slug'] ?? ''));
            if ($slug === '') {
                throw ValidationException::withMessages([
                    'input.translations' => ['Every translation requires a URL-safe slug.'],
                ]);
            }
            if (DB::table($table)
                ->where('store_id', $store->getKey())
                ->whereRaw('LOWER(locale) = ?', [$localeKey])
                ->where('slug', $slug)
                ->where($ownerKey, '<>', $ownerId)
                ->exists()) {
                throw ValidationException::withMessages([
                    'input.translations' => ["The slug [{$slug}] is already used for locale [{$locale}]."],
                ]);
            }

            $row = [
                'store_id' => $store->getKey(),
                $ownerKey => $ownerId,
                'locale' => $locale,
                'slug' => $slug,
                'lock_it' => array_key_exists('lock_it', $translation)
                    ? (bool) $translation['lock_it']
                    : (bool) ($existing->get($localeKey)?->lock_it ?? false),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            foreach ($fields as $field) {
                $row[$field] = $translation[$field] ?? null;
            }
            $rows[] = $row;
        }

        DB::table($table)->upsert(
            $rows,
            [$ownerKey, 'locale'],
            [...$fields, 'slug', 'lock_it', 'updated_at'],
        );

        return $this->sourceLocale($store, $table, $ownerKey, $ownerId, $defaultLocale);
    }

    public function sourceLocale(
        Store $store,
        string $table,
        string $ownerKey,
        int $ownerId,
        ?string $fallback = null,
    ): string {
        [, $defaultLocale] = $this->storeLocales($store);
        $source = DB::table($table)
            ->where($ownerKey, $ownerId)
            ->whereRaw('LOWER(locale) = ?', [$this->localeKey($defaultLocale)])
            ->first(['locale'])
            ?? DB::table($table)
                ->where($ownerKey, $ownerId)
                ->when($fallback !== null, fn ($query) => $query->orderByRaw(
                    'CASE WHEN LOWER(locale) = ? THEN 0 ELSE 1 END',
                    [$this->localeKey($fallback)],
                ))
                ->orderBy('locale')
                ->first(['locale']);

        if (! is_object($source)) {
            throw ValidationException::withMessages([
                'input.translations' => ['At least one Store-language translation is required.'],
            ]);
        }

        return $this->normalizeLocale((string) $source->locale);
    }

    /** @return array{list<string>, string} */
    private function storeLocales(Store $store): array
    {
        $locales = $this->languages->activeFor($store)
            ->pluck('locale')
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
