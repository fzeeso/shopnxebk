<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use App\Support\Translations\StoreTranslationLanguages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Stores\Models\Store;

final readonly class CustomObjectTranslationResolver
{
    public function __construct(private StoreTranslationLanguages $languages) {}

    public function requestedLocale(Request $request): ?string
    {
        $locale = trim((string) $request->query('locale', ''));
        if ($locale !== '') {
            return $this->localeKey($locale);
        }

        $header = trim((string) $request->header('Accept-Language', ''));
        if ($header === '') {
            return null;
        }

        return $this->localeKey(explode(',', $header, 2)[0]);
    }

    /** @param Collection<int, Model> $translations */
    public function resolve(Collection $translations, Store $store, ?string $requestedLocale): ?Model
    {
        if ($translations->isEmpty()) {
            return null;
        }

        $requested = $requestedLocale === null ? null : $this->localeKey($requestedLocale);
        if ($requested !== null) {
            $translation = $translations->first(
                fn (Model $item): bool => $this->localeKey((string) $item->getAttribute('locale')) === $requested,
            );
            if ($translation !== null) {
                return $translation;
            }
        }

        $configured = $this->languages->activeFor($store);
        $defaultLocale = $configured->firstWhere('is_default', true)?->locale
            ?? $store->language_code
            ?? config('app.locale', 'en');
        $defaultKey = $this->localeKey((string) $defaultLocale);
        $translation = $translations->first(
            fn (Model $item): bool => $this->localeKey((string) $item->getAttribute('locale')) === $defaultKey,
        );

        return $translation ?? $translations->first();
    }

    private function localeKey(string $locale): string
    {
        $locale = trim(explode(';', $locale, 2)[0]);

        return strtolower(str_replace('-', '_', $locale));
    }
}
