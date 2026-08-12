<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Resources;

use App\Http\Resources\TranslationRequestResource;
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
                'lang_image' => $this->language->langImageUrl(),
                'locale' => $this->language->locale,
            ]),
            'title' => $this->title,
            'content' => $this->content,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'lock_it' => $this->lock_it,
            'translation_request' => $this->when(
                $this->resource->relationLoaded('translationRequest'),
                fn () => $this->resource->getRelation('translationRequest') === null
                    ? null
                    : new TranslationRequestResource($this->resource->getRelation('translationRequest')),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
