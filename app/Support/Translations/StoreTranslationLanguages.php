<?php

declare(strict_types=1);

namespace App\Support\Translations;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Stores\Models\Store;

final class StoreTranslationLanguages
{
    /**
     * @return Collection<int, object{
     *     id: int,
     *     public_id: string,
     *     name: string,
     *     native_name: string,
     *     locale: string,
     *     lang_icon: string|null,
     *     lang_image: string|null,
     *     direction: string,
     *     is_default: bool
     * }>
     */
    public function activeFor(Store $store): Collection
    {
        return DB::table('store_languages')
            ->join('languages', 'languages.id', '=', 'store_languages.language_id')
            ->where('store_languages.store_id', $store->getKey())
            ->where('store_languages.is_active', true)
            ->where('languages.is_active', true)
            ->orderByDesc('store_languages.is_default')
            ->orderBy('languages.locale')
            ->get([
                'languages.id',
                'languages.public_id',
                'languages.name',
                'languages.native_name',
                'languages.locale',
                'languages.lang_icon',
                'languages.lang_image',
                'languages.direction',
                'store_languages.is_default',
            ]);
    }

    /**
     * @return list<array{
     *     id: string,
     *     name: string,
     *     native_name: string,
     *     locale: string,
     *     lang_icon: string,
     *     lang_image: string,
     *     direction: string,
     *     is_default: bool
     * }>
     */
    public function presentationFor(Store $store): array
    {
        return $this->activeFor($store)->map(function (object $language): array {
            $icon = $this->assetUrl((string) ($language->lang_icon ?? ''), '/assets/languages/flags/generic.svg');

            return [
                'id' => (string) $language->public_id,
                'name' => (string) $language->name,
                'native_name' => (string) $language->native_name,
                'locale' => (string) $language->locale,
                'lang_icon' => $icon,
                'lang_image' => $this->assetUrl((string) ($language->lang_image ?? ''), $icon),
                'direction' => (string) $language->direction,
                'is_default' => (bool) $language->is_default,
            ];
        })->values()->all();
    }

    private function assetUrl(string $reference, string $fallback): string
    {
        $reference = trim($reference) !== '' ? trim($reference) : $fallback;

        return Str::startsWith($reference, ['http://', 'https://'])
            ? $reference
            : url('/'.ltrim($reference, '/'));
    }
}
