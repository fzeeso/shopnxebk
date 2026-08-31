<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\CustomObjectField;
use Modules\Catalog\Models\CustomObjectFieldTranslation;
use Modules\Catalog\Models\CustomObjectTypeTranslation;

/** @extends JsonResource<CustomObjectField> */
final class CustomObjectFieldResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var CustomObjectFieldTranslation|null $translation */
        $translation = $this->relationLoaded('translations')
            ? CustomObjectResourceSupport::resolved($this->translations, $request)
            : null;
        $referenceTranslation = null;
        if ($this->relationLoaded('referenceObjectType') && $this->referenceObjectType?->relationLoaded('translations')) {
            /** @var CustomObjectTypeTranslation|null $referenceTranslation */
            $referenceTranslation = CustomObjectResourceSupport::resolved($this->referenceObjectType->translations, $request);
        }

        return [
            'id' => $this->public_id,
            'type_id' => $this->whenLoaded('type', fn () => $this->type->public_id),
            'handle' => $this->handle,
            'field_type' => $this->field_type,
            'is_required' => $this->is_required,
            'is_unique' => $this->is_unique,
            'is_localized' => $this->is_localized,
            'is_searchable' => $this->is_searchable,
            'is_filterable' => $this->is_filterable,
            'sort_order' => $this->sort_order,
            'status' => $this->status,
            'settings' => $this->settings,
            'validation_rules' => $this->validation_rules,
            'label' => $translation?->label,
            'description' => $translation?->description,
            'help_text' => $translation?->help_text,
            'placeholder' => $translation?->placeholder,
            'resolved_locale' => $translation?->locale,
            'reference_object_type' => $this->whenLoaded('referenceObjectType', fn () => $this->referenceObjectType === null ? null : [
                'id' => $this->referenceObjectType->public_id,
                'handle' => $this->referenceObjectType->handle,
                'name' => $referenceTranslation?->name,
            ]),
            'translations' => $this->whenLoaded('translations', fn () => $this->translations->map(
                static fn (CustomObjectFieldTranslation $item): array => [
                    'locale' => $item->locale,
                    'label' => $item->label,
                    'description' => $item->description,
                    'help_text' => $item->help_text,
                    'placeholder' => $item->placeholder,
                    'lock_it' => $item->lock_it,
                ],
            )->values()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
