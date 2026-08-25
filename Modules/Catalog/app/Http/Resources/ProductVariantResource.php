<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\ProductVariant;
use Modules\Catalog\Models\ProductVariantTranslation;

/** @extends JsonResource<ProductVariant> */
final class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'product_id' => $this->whenLoaded('product', fn () => $this->product->public_id),
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'price_amount_minor' => $this->price_amount_minor,
            'compare_at_price_amount_minor' => $this->compare_at_price_amount_minor,
            'msrp_amount_minor' => $this->msrp_amount_minor,
            'cost_per_item_amount_minor' => $this->cost_per_item_amount_minor,
            'currency_code' => $this->currency_code,
            'inventory_qty' => $this->inventory_qty,
            'inventory_policy' => $this->inventory_policy,
            'weight_grams' => $this->weight_grams,
            'height' => $this->height,
            'width' => $this->width,
            'depth' => $this->depth,
            'dimension_unit' => $this->dimension_unit,
            'requires_shipping' => $this->requires_shipping,
            'taxable' => $this->taxable,
            'call_for_price' => $this->call_for_price,
            'image_id' => $this->preferredImage?->public_id,
            'position' => $this->position,
            'translations' => $this->whenLoaded('translations', fn () => $this->translations
                ->map(static fn (ProductVariantTranslation $translation): array => [
                    'locale' => $translation->locale,
                    'title' => $translation->title,
                    'lock_it' => $translation->lock_it,
                    'created_at' => $translation->created_at?->toIso8601String(),
                    'updated_at' => $translation->updated_at?->toIso8601String(),
                ])
                ->values()),
            'option_values' => $this->whenLoaded('optionValues', fn () => ProductOptionValueResource::collection(
                $this->optionValues->sortBy(static fn ($value): string => sprintf(
                    '%010d:%010d:%010d',
                    (int) $value->option->position,
                    (int) $value->position,
                    (int) $value->getKey(),
                ))->values(),
            )),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
