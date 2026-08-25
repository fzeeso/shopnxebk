<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\ModifierLibraryCategory;

/** @extends JsonResource<ModifierLibraryCategory> */
final class ModifierLibraryCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'code' => $this->code,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'translations' => $this->whenLoaded('translations', fn () => $this->translations->map(fn ($translation): array => [
                'locale' => $translation->locale, 'name' => $translation->name, 'description' => $translation->description,
                'lock_it' => $translation->lock_it,
            ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
