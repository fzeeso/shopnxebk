<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductTranslation;

/** @extends JsonResource<Product> */
final class CollectionProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'status' => $this->status,
            'sku' => $this->sku,
            'price' => $this->price,
            'sort_order' => (int) $this->pivot->sort_order,
            'added_by' => $this->pivot->added_by,
            'is_pinned' => (bool) $this->pivot->is_pinned,
            'translations' => $this->whenLoaded('translations', fn () => $this->translations
                ->map(static fn (ProductTranslation $translation): array => [
                    'locale' => $translation->locale,
                    'title' => $translation->title,
                    'slug' => $translation->slug,
                ])
                ->values()),
        ];
    }
}
