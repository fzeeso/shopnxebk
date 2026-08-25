<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use App\Support\Translations\StoreTranslationLanguages;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Stores\Models\Store;

final readonly class LocalizedTranslationWriter
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
        array $requiredFields = [],
    ): void {
        $activeLocales = $this->languages->activeFor($store)
            ->pluck('locale')
            ->map(fn (mixed $locale): string => $this->localeKey((string) $locale))
            ->all();
        if ($activeLocales === []) {
            $activeLocales = [$this->localeKey((string) ($store->language_code ?: config('app.locale', 'en')))];
        }
        $activeLocaleKeys = array_fill_keys($activeLocales, true);
        $existing = DB::table($table)
            ->where($ownerKey, $ownerId)
            ->get(['locale', ...$fields, 'lock_it'])
            ->keyBy(fn (object $row): string => $this->localeKey((string) $row->locale));
        $seen = [];
        $rows = [];
        $now = now();

        foreach ($translations as $index => $translation) {
            $locale = str_replace('-', '_', trim((string) ($translation['locale'] ?? '')));
            $localeKey = $this->localeKey($locale);
            if ($locale === '' || isset($seen[$localeKey])) {
                throw ValidationException::withMessages([
                    'translations' => ['Each translation must use one unique locale.'],
                ]);
            }
            if (! isset($activeLocaleKeys[$localeKey])) {
                throw ValidationException::withMessages([
                    "translations.{$index}.locale" => ["The locale [{$locale}] is not active for this Store."],
                ]);
            }
            foreach ($requiredFields as $field) {
                if (trim((string) ($translation[$field] ?? '')) === '') {
                    throw ValidationException::withMessages([
                        "translations.{$index}.{$field}" => ["The {$field} field is required."],
                    ]);
                }
            }

            $seen[$localeKey] = true;
            $old = $existing->get($localeKey);
            $row = [
                'store_id' => $store->getKey(),
                $ownerKey => $ownerId,
                'locale' => (string) ($old?->locale ?? $locale),
                'lock_it' => array_key_exists('lock_it', $translation)
                    ? (bool) $translation['lock_it']
                    : (bool) ($old?->lock_it ?? false),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            foreach ($fields as $field) {
                $row[$field] = array_key_exists($field, $translation)
                    ? $translation[$field]
                    : $old?->{$field};
            }
            $rows[] = $row;
        }

        if ($rows !== []) {
            DB::table($table)->upsert(
                $rows,
                [$ownerKey, 'locale'],
                [...$fields, 'lock_it', 'updated_at'],
            );
        }
    }

    private function localeKey(string $locale): string
    {
        return strtolower(str_replace('-', '_', trim($locale)));
    }
}
