<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\ProductImage;
use Modules\Catalog\Models\ProductImageTranslation;

/** @extends JsonResource<ProductImage> */
final class ProductImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'product_id' => $this->whenLoaded('product', fn () => $this->product->public_id),
            'variant_id' => $this->variant?->public_id,
            'url' => $this->url,
            'width' => $this->width,
            'height' => $this->height,
            'position' => $this->position,
            'translations' => $this->whenLoaded('translations', fn () => $this->translations
                ->map(static fn (ProductImageTranslation $translation): array => [
                    'locale' => $translation->locale,
                    'alt_text' => $translation->alt_text,
                    'lock_it' => $translation->lock_it,
                    'created_at' => $translation->created_at?->toIso8601String(),
                    'updated_at' => $translation->updated_at?->toIso8601String(),
                ])
                ->values()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
