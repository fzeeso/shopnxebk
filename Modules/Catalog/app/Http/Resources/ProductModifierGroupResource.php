<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\ProductModifierGroup;

/** @extends JsonResource<ProductModifierGroup> */
final class ProductModifierGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id, 'code' => $this->code, 'sort_order' => $this->sort_order,
            'is_active' => $this->is_active, 'settings' => $this->settings,
            'translations' => $this->whenLoaded('translations', fn () => $this->translations->map(fn ($translation): array => [
                'locale' => $translation->locale, 'name' => $translation->name,
                'description' => $translation->description, 'lock_it' => $translation->lock_it,
            ])->values()),
        ];
    }
}
