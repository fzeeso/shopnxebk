<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Resources;

use App\Http\Resources\TranslationRequestResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Stores\Models\PageTranslation;

/** @extends JsonResource<PageTranslation> */
final class PageTranslationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'language' => $this->whenLoaded('language', fn (): array => [
                'id' => $this->language->public_id,
                'name' => $this->language->name,
                'native_name' => $this->language->native_name,
                'locale' => $this->language->locale,
                'direction' => $this->language->direction->value,
                'lang_icon' => $this->language->langIconUrl(),
                'lang_image' => $this->language->langImageUrl(),
            ]),
            'title' => $this->title,
            'slug' => $this->slug,
            'content' => $this->content,
            'summary' => $this->summary,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'seo_keywords' => $this->seo_keywords,
            'search_keywords' => $this->search_keywords,
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
