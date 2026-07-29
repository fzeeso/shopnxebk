<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Stores\Enums\LanguageDirection;
use Modules\Stores\Models\Language;
use Modules\Stores\Models\StoreLanguage;

/** @extends JsonResource<Language> */
final class StoreLanguageOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $selection = $this->storeLanguages->first();

        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'native_name' => $this->native_name,
            'locale' => $this->locale,
            'direction' => $this->direction instanceof LanguageDirection
                ? $this->direction->value
                : (string) $this->direction,
            'is_selected' => $selection instanceof StoreLanguage && $selection->is_active,
            'is_default' => $selection instanceof StoreLanguage && $selection->is_default,
        ];
    }
}
