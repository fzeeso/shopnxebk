<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Resources;

use App\Http\Resources\TranslationRequestResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\Collection as CatalogCollection;
use Modules\Catalog\Models\CollectionTranslation;

/** @extends JsonResource<CatalogCollection> */
final class CollectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'parent_id' => $this->parentPublicId(),
            'image_url' => $this->image_url,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'collection_type' => $this->collection_type,
            'rules_match_type' => $this->rules_match_type,
            'ai_prompt' => $this->ai_prompt,
            'ai_model' => $this->ai_model,
            'ai_status' => $this->ai_status,
            'ai_last_run_at' => $this->ai_last_run_at?->toIso8601String(),
            'ai_error_message' => $this->ai_error_message,
            'children_count' => $this->whenCounted('children'),
            'products_count' => $this->whenCounted('products'),
            'rules_count' => $this->whenCounted('rules'),
            'ai_jobs_count' => $this->whenCounted('aiJobs'),
            'translations' => $this->whenLoaded('translations', fn () => $this->translations
                ->map(static fn (CollectionTranslation $translation): array => [
                    'locale' => $translation->locale,
                    'title' => $translation->title,
                    'slug' => $translation->slug,
                    'description' => $translation->description,
                    'seo_title' => $translation->seo_title,
                    'seo_description' => $translation->seo_description,
                    'lock_it' => $translation->lock_it,
                    'created_at' => $translation->created_at?->toIso8601String(),
                    'updated_at' => $translation->updated_at?->toIso8601String(),
                ])
                ->values()),
            'rules' => CollectionRuleResource::collection($this->whenLoaded('rules')),
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
