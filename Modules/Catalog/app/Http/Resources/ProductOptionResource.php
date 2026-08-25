<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\ProductOption;
use Modules\Catalog\Models\ProductOptionTranslation;

/** @extends JsonResource<ProductOption> */
final class ProductOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'product_id' => $this->whenLoaded('product', fn () => $this->product->public_id),
            'position' => $this->position,
            'translations' => $this->whenLoaded('translations', fn () => $this->translations
                ->map(static fn (ProductOptionTranslation $translation): array => [
                    'locale' => $translation->locale,
                    'name' => $translation->name,
                    'lock_it' => $translation->lock_it,
                    'created_at' => $translation->created_at?->toIso8601String(),
                    'updated_at' => $translation->updated_at?->toIso8601String(),
                ])
                ->values()),
            'values' => $this->whenLoaded(
                'values',
                fn () => ProductOptionValueResource::collection($this->values),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
