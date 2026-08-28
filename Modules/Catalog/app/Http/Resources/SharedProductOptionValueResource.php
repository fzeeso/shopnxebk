<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\SharedProductOptionValue;
use Modules\Catalog\Models\SharedProductOptionValueTranslation;

/** @extends JsonResource<SharedProductOptionValue> */
final class SharedProductOptionValueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'position' => $this->position,
            'is_default' => $this->is_default,
            'translations' => $this->whenLoaded('translations', fn () => $this->translations
                ->map(static fn (SharedProductOptionValueTranslation $translation): array => [
                    'locale' => $translation->locale,
                    'display_label' => $translation->display_label,
                    'lock_it' => $translation->lock_it,
                    'created_at' => $translation->created_at?->toIso8601String(),
                    'updated_at' => $translation->updated_at?->toIso8601String(),
                ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
