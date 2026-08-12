<?php

declare(strict_types=1);

namespace App\Support\Translations;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Stores\Models\Store;

final class StoreTranslationLanguages
{
    /** @return Collection<int, object{id: int, locale: string, is_default: bool}> */
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
                'languages.locale',
                'store_languages.is_default',
            ]);
    }
}
