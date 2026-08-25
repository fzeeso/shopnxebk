<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\ModifierDefinition;

/** @extends JsonResource<ModifierDefinition> */
final class ModifierDefinitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'category_id' => $this->category?->public_id,
            'code' => $this->code,
            'type' => $this->type,
            'is_active' => $this->is_active,
            'is_required_default' => $this->is_required_default,
            'supports_multiple' => $this->supports_multiple,
            'min_selections' => $this->min_selections,
            'max_selections' => $this->max_selections,
            'sort_order' => $this->sort_order,
            'settings' => $this->settings,
            'translations' => $this->whenLoaded('translations', fn () => $this->translations->map(fn ($translation): array => [
                'locale' => $translation->locale, 'name' => $translation->name,
                'description' => $translation->description, 'placeholder' => $translation->placeholder,
                'help_text' => $translation->help_text, 'required_message' => $translation->required_message,
                'validation_message' => $translation->validation_message, 'lock_it' => $translation->lock_it,
            ])->values()),
            'values' => $this->whenLoaded('values', fn () => $this->values->map(fn ($value): array => [
                'id' => $value->public_id, 'code' => $value->code, 'sort_order' => $value->sort_order,
                'is_default' => $value->is_default, 'is_active' => $value->is_active,
                'colour_value' => $value->colour_value, 'image_id' => $value->image?->public_id,
                'icon' => $value->icon, 'settings' => $value->settings,
                'translations' => $value->translations->map(fn ($translation): array => [
                    'locale' => $translation->locale, 'name' => $translation->name,
                    'description' => $translation->description, 'lock_it' => $translation->lock_it,
                ])->values(),
                'price_adjustments' => $value->priceAdjustments->map(fn ($price): array => $this->price($price))->values(),
            ])->values()),
            'validation_rules' => $this->whenLoaded('validationRules', fn () => $this->validationRules->map(fn ($rule): array => [
                'rule_type' => $rule->rule_type, 'rule_value' => $rule->rule_value,
                'sort_order' => $rule->sort_order, 'is_active' => $rule->is_active,
                'translations' => $rule->translations->map(fn ($translation): array => [
                    'locale' => $translation->locale, 'message' => $translation->message, 'lock_it' => $translation->lock_it,
                ])->values(),
            ])->values()),
            'price_adjustments' => $this->whenLoaded('priceAdjustments', fn () => $this->priceAdjustments->map(fn ($price): array => $this->price($price))->values()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
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
