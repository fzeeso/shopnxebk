<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\ProductModifierAssignment;

/** @extends JsonResource<ProductModifierAssignment> */
final class ProductModifierAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'modifier_id' => $this->modifier?->public_id,
            'group_id' => $this->group?->public_id,
            'sort_order' => $this->sort_order, 'is_active' => $this->is_active,
            'is_required_override' => $this->is_required_override,
            'min_selections_override' => $this->min_selections_override,
            'max_selections_override' => $this->max_selections_override,
            'settings_override' => $this->settings_override,
            'translations' => $this->whenLoaded('translations', fn () => $this->translations->map(fn ($translation): array => [
                'locale' => $translation->locale, 'name_override' => $translation->name_override,
                'description_override' => $translation->description_override,
                'placeholder_override' => $translation->placeholder_override,
                'help_text_override' => $translation->help_text_override, 'lock_it' => $translation->lock_it,
            ])->values()),
            'value_assignments' => $this->whenLoaded('valueAssignments', fn () => $this->valueAssignments->map(fn ($row): array => [
                'value_id' => $row->value?->public_id, 'is_enabled' => $row->is_enabled,
                'is_default_override' => $row->is_default_override, 'sort_order' => $row->sort_order,
                'settings_override' => $row->settings_override,
            ])->values()),
            'price_overrides' => $this->whenLoaded('priceOverrides', fn () => $this->priceOverrides->map(fn ($price): array => $this->price($price))->values()),
            'value_price_overrides' => $this->whenLoaded('valuePriceOverrides', fn () => $this->valuePriceOverrides->map(fn ($price): array => [
                'value_id' => $price->value?->public_id,
                ...$this->price($price),
            ])->values()),
        ];
    }

    /** @return array<string, mixed> */
    private function price(object $price): array
    {
        return [
            'currency_code' => $price->currency_code, 'adjustment_type' => $price->adjustment_type,
            'amount' => $price->amount, 'starts_at' => $price->starts_at?->toIso8601String(),
            'ends_at' => $price->ends_at?->toIso8601String(), 'is_active' => $price->is_active,
        ];
    }
}
