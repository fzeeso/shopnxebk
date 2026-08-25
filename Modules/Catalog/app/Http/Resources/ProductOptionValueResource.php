<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\ProductOptionTranslation;
use Modules\Catalog\Models\ProductOptionValue;
use Modules\Catalog\Models\ProductOptionValueTranslation;

/** @extends JsonResource<ProductOptionValue> */
final class ProductOptionValueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'product_id' => $this->whenLoaded('product', fn () => $this->product->public_id),
            'option_id' => $this->whenLoaded('option', fn () => $this->option->public_id),
            'position' => $this->position,
            'option_translations' => $this->when(
                $this->relationLoaded('option') && $this->option->relationLoaded('translations'),
                fn () => $this->option->translations->map(static fn (ProductOptionTranslation $translation): array => [
                    'locale' => $translation->locale,
                    'name' => $translation->name,
                    'lock_it' => $translation->lock_it,
                ])->values(),
            ),
            'translations' => $this->whenLoaded('translations', fn () => $this->translations
                ->map(static fn (ProductOptionValueTranslation $translation): array => [
                    'locale' => $translation->locale,
                    'value' => $translation->value,
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
