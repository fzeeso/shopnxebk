<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Resources;

use App\Http\Resources\TranslationRequestResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Stores\Models\Page;

/** @extends JsonResource<Page> */
final class PageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'store_id' => $this->whenLoaded('store', fn () => $this->store?->public_id),
            'parent_id' => $this->whenLoaded('parent', fn () => $this->parent?->public_id),
            'page_type' => $this->resource->typeValue(),
            'status' => $this->resource->statusValue(),
            'sort_order' => $this->sort_order,
            'layout_key' => $this->layout_key,
            'is_homepage' => $this->is_homepage,
            'customers_only' => $this->customers_only,
            'seo_enabled' => $this->seo_enabled,
            'external_url' => $this->external_url,
            'feed_url' => $this->feed_url,
            'contact_email' => $this->contact_email,
            'contact_fields' => $this->contact_fields,
            'published_at' => $this->published_at?->toIso8601String(),
            'children_count' => $this->whenCounted('children'),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->public_id),
            'updated_by' => $this->whenLoaded('updater', fn () => $this->updater?->public_id),
            'translations' => PageTranslationResource::collection($this->whenLoaded('translations')),
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
