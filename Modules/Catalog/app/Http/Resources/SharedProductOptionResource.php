<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\SharedProductOption;
use Modules\Catalog\Models\SharedProductOptionTranslation;

/** @extends JsonResource<SharedProductOption> */
final class SharedProductOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'type' => $this->type,
            'position' => $this->position,
            'products_count' => $this->whenCounted('assignments'),
            'translations' => $this->whenLoaded('translations', fn () => $this->translations
                ->map(static fn (SharedProductOptionTranslation $translation): array => [
                    'locale' => $translation->locale,
                    'display_name' => $translation->display_name,
                    'lock_it' => $translation->lock_it,
                    'created_at' => $translation->created_at?->toIso8601String(),
                    'updated_at' => $translation->updated_at?->toIso8601String(),
                ])->values()),
            'values' => $this->whenLoaded(
                'values',
                fn () => SharedProductOptionValueResource::collection($this->values),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
