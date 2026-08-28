<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\CustomFieldDefinition;
use Modules\Catalog\Models\CustomFieldDefinitionTranslation;

/** @extends JsonResource<CustomFieldDefinition> */
final class CustomFieldDefinitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'product_type' => $this->product_type,
            'field_key' => $this->field_key,
            'field_type' => $this->field_type,
            'is_required' => $this->is_required,
            'is_filterable' => $this->is_filterable,
            'position' => $this->position,
            'values_count' => $this->whenCounted('values'),
            'translations' => $this->whenLoaded('translations', fn () => $this->translations
                ->map(static fn (CustomFieldDefinitionTranslation $translation): array => [
                    'locale' => $translation->locale,
                    'label' => $translation->label,
                    'help_text' => $translation->help_text,
                    'lock_it' => $translation->lock_it,
                    'created_at' => $translation->created_at?->toIso8601String(),
                    'updated_at' => $translation->updated_at?->toIso8601String(),
                ])->values()),
            'options' => $this->whenLoaded(
                'options',
                fn () => CustomFieldOptionResource::collection($this->options),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
