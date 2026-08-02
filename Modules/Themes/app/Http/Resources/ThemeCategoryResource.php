<?php

declare(strict_types=1);

namespace Modules\Themes\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Themes\Models\ThemeCategory;

/** @extends JsonResource<ThemeCategory> */
final class ThemeCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'parent_id' => $this->whenLoaded('parent', fn () => $this->parent?->public_id),
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'category_type' => $this->category_type,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'is_primary' => $this->whenPivotLoaded('theme_category_assignments', fn (): bool => (bool) $this->pivot->is_primary),
        ];
    }
}
