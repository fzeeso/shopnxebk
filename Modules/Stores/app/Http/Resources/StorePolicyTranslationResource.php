<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Stores\Models\StorePolicyTranslation;

/** @extends JsonResource<StorePolicyTranslation> */
final class StorePolicyTranslationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'language' => $this->whenLoaded('language', fn (): array => [
                'id' => $this->language->public_id,
                'name' => $this->language->name,
                'native_name' => $this->language->native_name,
                'lang_icon' => $this->language->langIconUrl(),
                'locale' => $this->language->locale,
            ]),
            'title' => $this->title,
            'content' => $this->content,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
