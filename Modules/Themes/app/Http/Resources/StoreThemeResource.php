<?php

declare(strict_types=1);

namespace Modules\Themes\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Themes\Models\StoreTheme;

/** @extends JsonResource<StoreTheme> */
final class StoreThemeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'store_id' => $this->whenLoaded('store', fn () => $this->store?->public_id),
            'theme' => new ThemeResource($this->whenLoaded('theme')),
            'theme_version' => new ThemeVersionResource($this->whenLoaded('themeVersion')),
            'license' => new ThemeLicenseResource($this->whenLoaded('license')),
            'parent_store_theme_id' => $this->whenLoaded('parent', fn () => $this->parent?->public_id),
            'installed_by_user_id' => $this->whenLoaded('installer', fn () => $this->installer?->public_id),
            'name' => $this->name,
            'status' => $this->resource->statusValue(),
            'installed_from' => $this->installed_from,
            'settings_data' => $this->settings_data,
            'template_data' => $this->template_data,
            'custom_css' => $this->custom_css,
            'customization_revision' => $this->customization_revision,
            'installed_at' => $this->installed_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
