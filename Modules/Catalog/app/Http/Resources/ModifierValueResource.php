<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\ModifierValue;

/** @extends JsonResource<ModifierValue> */
final class ModifierValueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'code' => $this->code,
            'sort_order' => $this->sort_order,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'colour_value' => $this->colour_value,
            'image_id' => $this->image?->public_id,
            'icon' => $this->icon,
            'settings' => $this->settings,
            'translations' => $this->whenLoaded('translations', fn () => $this->translations->map(fn ($translation): array => [
                'locale' => $translation->locale,
                'name' => $translation->name,
                'description' => $translation->description,
                'lock_it' => $translation->lock_it,
            ])->values()),
            'price_adjustments' => $this->whenLoaded('priceAdjustments', fn () => $this->priceAdjustments->map(fn ($price): array => [
                'currency_code' => $price->currency_code,
                'adjustment_type' => $price->adjustment_type,
                'amount' => $price->amount,
                'starts_at' => $price->starts_at?->toIso8601String(),
                'ends_at' => $price->ends_at?->toIso8601String(),
                'is_active' => $price->is_active,
            ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
