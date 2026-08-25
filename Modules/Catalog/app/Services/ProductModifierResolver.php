<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use Illuminate\Support\Collection;
use Modules\Catalog\Models\ModifierValidationRule;
use Modules\Catalog\Models\ModifierValue;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductModifierAssignment;
use Modules\Catalog\Models\ProductModifierGroupTranslation;
use Modules\Catalog\Models\ProductModifierValueAssignment;
use Modules\Stores\Models\Store;

final readonly class ProductModifierResolver
{
    public function __construct(
        private ModifierTranslationResolver $translations,
        private ModifierPricingResolver $pricing,
    ) {}

    /** @return list<array<string, mixed>> */
    public function resolve(
        Store $store,
        Product $product,
        string $locale,
        string $currency,
        ?int $channelId = null,
        ?int $customerGroupId = null,
    ): array {
        $assignments = ProductModifierAssignment::query()
            ->where('store_id', $store->getKey())
            ->where('product_id', $product->getKey())
            ->where('is_active', true)
            ->whereHas('modifier', fn ($query) => $query->where('is_active', true))
            ->with([
                'translations', 'group.translations', 'priceOverrides', 'valuePriceOverrides',
                'modifier.translations', 'modifier.priceAdjustments', 'modifier.validationRules.translations',
                'modifier.values' => fn ($query) => $query->where('is_active', true)->with(['translations', 'priceAdjustments', 'image']),
                'valueAssignments.value.translations',
            ])
            ->orderBy('sort_order')->orderBy('id')->get();

        return $this->resolveLoaded($store, $product, $assignments, $locale, $currency, $channelId, $customerGroupId);
    }

    /**
     * @param  iterable<ProductModifierAssignment>  $assignments
     * @return list<array<string, mixed>>
     */
    public function resolveLoaded(
        Store $store,
        Product $product,
        iterable $assignments,
        string $locale,
        string $currency,
        ?int $channelId = null,
        ?int $customerGroupId = null,
    ): array {
        return Collection::make($assignments)->map(fn (ProductModifierAssignment $assignment): array => $this->assignment(
            $store,
            $product,
            $assignment,
            $locale,
            $currency,
            $channelId,
            $customerGroupId,
        ))->values()->all();
    }

    /** @return array<string, mixed> */
    private function assignment(Store $store, Product $product, ProductModifierAssignment $assignment, string $locale, string $currency, ?int $channelId, ?int $customerGroupId): array
    {
        $modifier = $assignment->modifier;
        $copy = $this->translations->resolveModifier(
            $assignment->translations,
            $modifier->translations,
            $locale,
            (string) $store->language_code,
            (string) $modifier->code,
        );
        $valueAssignments = $assignment->valueAssignments->keyBy('modifier_value_id');
        $values = $modifier->values
            ->filter(fn (ModifierValue $value): bool => $valueAssignments->isEmpty() || (bool) $valueAssignments->get($value->getKey())?->is_enabled)
            ->sortBy(fn (ModifierValue $value): array => [
                $valueAssignments->get($value->getKey())?->sort_order ?? $value->sort_order,
                $value->getKey(),
            ])->values()->map(function (ModifierValue $value) use ($assignment, $currency, $customerGroupId, $channelId, $locale, $modifier, $product, $store, $valueAssignments): array {
                /** @var ProductModifierValueAssignment|null $valueAssignment */
                $valueAssignment = $valueAssignments->get($value->getKey());

                return [
                    'id' => $value->public_id,
                    'code' => $value->code,
                    'name' => $this->translations->resolveValueName($value->translations, $locale, (string) $store->language_code, (string) $value->code),
                    'description' => $this->valueDescription($value, $locale, (string) $store->language_code),
                    'colour' => $value->colour_value,
                    'imageId' => $value->image?->public_id,
                    'icon' => $value->icon,
                    'default' => $valueAssignment?->is_default_override ?? $value->is_default,
                    'settings' => $this->merge($value->settings, $valueAssignment?->settings_override),
                    'priceAdjustment' => $this->pricing->resolve($assignment, $modifier, $value, $currency, (string) ($product->price ?? 0), $channelId, $customerGroupId),
                ];
            })->all();

        return [
            'id' => $assignment->public_id,
            'modifierId' => $modifier->public_id,
            'group' => $this->group($assignment, $locale, (string) $store->language_code),
            'code' => $modifier->code,
            'type' => $modifier->type,
            ...$copy,
            'required' => $assignment->is_required_override ?? $modifier->is_required_default,
            'supportsMultiple' => $modifier->supports_multiple,
            'minSelections' => $assignment->min_selections_override ?? $modifier->min_selections,
            'maxSelections' => $assignment->max_selections_override ?? $modifier->max_selections,
            'settings' => $this->merge($modifier->settings, $assignment->settings_override),
            'priceAdjustment' => $this->pricing->resolve($assignment, $modifier, null, $currency, (string) ($product->price ?? 0), $channelId, $customerGroupId),
            'validationRules' => $modifier->validationRules->where('is_active', true)->map(fn (ModifierValidationRule $rule): array => [
                'type' => $rule->rule_type,
                'value' => $rule->rule_value,
                'message' => $this->ruleMessage($rule, $locale, (string) $store->language_code, $copy['validationMessage']),
            ])->values()->all(),
            'values' => $values,
        ];
    }

    /** @return array<string, mixed>|null */
    private function group(ProductModifierAssignment $assignment, string $locale, string $defaultLocale): ?array
    {
        if ($assignment->group === null || ! $assignment->group->is_active) {
            return null;
        }
        $translations = $assignment->group->translations->keyBy(fn (ProductModifierGroupTranslation $translation): string => $this->locale($translation->locale));
        $translation = $translations->get($this->locale($locale)) ?? $translations->get($this->locale($defaultLocale));

        return [
            'id' => $assignment->group->public_id,
            'code' => $assignment->group->code,
            'sortOrder' => $assignment->group->sort_order,
            'name' => $translation?->name ?? ucwords(str_replace('_', ' ', (string) $assignment->group->code)),
            'description' => $translation?->description,
            'settings' => $assignment->group->settings,
        ];
    }

    private function valueDescription(ModifierValue $value, string $locale, string $defaultLocale): ?string
    {
        $translations = $value->translations->keyBy(fn ($translation): string => $this->locale((string) $translation->locale));

        return ($translations->get($this->locale($locale)) ?? $translations->get($this->locale($defaultLocale)))?->description;
    }

    private function ruleMessage(ModifierValidationRule $rule, string $locale, string $defaultLocale, ?string $fallback): string
    {
        $translations = $rule->translations->keyBy(fn ($translation): string => $this->locale((string) $translation->locale));

        return (string) (($translations->get($this->locale($locale)) ?? $translations->get($this->locale($defaultLocale)))?->message
            ?? $fallback
            ?? 'The modifier input is invalid.');
    }

    /** @param array<string, mixed>|null $base @param array<string, mixed>|null $override @return array<string, mixed>|null */
    private function merge(?array $base, ?array $override): ?array
    {
        $merged = array_replace_recursive($base ?? [], $override ?? []);

        return $merged === [] ? null : $merged;
    }

    private function locale(string $locale): string
    {
        return strtolower(str_replace('-', '_', trim($locale)));
    }
}
