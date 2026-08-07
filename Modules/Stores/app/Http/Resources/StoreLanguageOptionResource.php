<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Settings\Enums\LanguageDirection;
use Modules\Settings\Models\Language;

/** @extends JsonResource<Language> */
final class StoreLanguageOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'native_name' => $this->native_name,
            'lang_icon' => $this->resource->langIconUrl(),
            'locale' => $this->locale,
            'direction' => $this->direction instanceof LanguageDirection
                ? $this->direction->value
                : (string) $this->direction,
            'is_selected' => (bool) $this->getAttribute('store_is_selected'),
            'is_default' => (bool) $this->getAttribute('store_is_default'),
        ];
    }
}
