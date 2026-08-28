<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\CustomFieldOption;
use Modules\Catalog\Models\CustomFieldOptionTranslation;

/** @extends JsonResource<CustomFieldOption> */
final class CustomFieldOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'definition_id' => $this->whenLoaded('definition', fn () => $this->definition->public_id),
            'position' => $this->position,
            'translations' => $this->whenLoaded('translations', fn () => $this->translations
                ->map(static fn (CustomFieldOptionTranslation $translation): array => [
                    'locale' => $translation->locale,
                    'label' => $translation->label,
                    'lock_it' => $translation->lock_it,
                    'created_at' => $translation->created_at?->toIso8601String(),
                    'updated_at' => $translation->updated_at?->toIso8601String(),
                ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
