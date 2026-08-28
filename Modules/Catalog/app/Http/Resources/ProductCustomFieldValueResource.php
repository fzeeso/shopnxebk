<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\ProductCustomFieldValue;
use Modules\Catalog\Models\ProductCustomFieldValueTranslation;

/** @extends JsonResource<ProductCustomFieldValue> */
final class ProductCustomFieldValueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'product_id' => $this->whenLoaded('product', fn () => $this->product->public_id),
            'variant_id' => $this->whenLoaded('variant', fn () => $this->variant?->public_id),
            'definition_id' => $this->whenLoaded('definition', fn () => $this->definition->public_id),
            'definition' => $this->whenLoaded(
                'definition',
                fn () => new CustomFieldDefinitionResource($this->definition),
            ),
            'value_number' => $this->value_number,
            'value_boolean' => $this->value_boolean,
            'value_date' => $this->value_date?->format('Y-m-d'),
            'option_id' => $this->whenLoaded('selectedOption', fn () => $this->selectedOption?->public_id),
            'selected_option' => $this->whenLoaded(
                'selectedOption',
                fn () => $this->selectedOption === null ? null : new CustomFieldOptionResource($this->selectedOption),
            ),
            'option_ids' => $this->whenLoaded(
                'selectedOptions',
                fn () => $this->selectedOptions->pluck('public_id')->values(),
            ),
            'selected_options' => $this->whenLoaded(
                'selectedOptions',
                fn () => CustomFieldOptionResource::collection($this->selectedOptions),
            ),
            'translations' => $this->whenLoaded('translations', fn () => $this->translations
                ->map(static fn (ProductCustomFieldValueTranslation $translation): array => [
                    'locale' => $translation->locale,
                    'value_text' => $translation->value_text,
                    'lock_it' => $translation->lock_it,
                    'created_at' => $translation->created_at?->toIso8601String(),
                    'updated_at' => $translation->updated_at?->toIso8601String(),
                ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
