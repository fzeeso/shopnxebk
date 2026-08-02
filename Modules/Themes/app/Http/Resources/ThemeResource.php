<?php

declare(strict_types=1);

namespace Modules\Themes\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Themes\Models\Theme;

/** @extends JsonResource<Theme> */
final class ThemeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'publisher' => new ThemePublisherResource($this->whenLoaded('publisher')),
            'owner_store_id' => $this->whenLoaded('ownerStore', fn () => $this->ownerStore?->public_id),
            'created_by_user_id' => $this->whenLoaded('creator', fn () => $this->creator?->public_id),
            'name' => $this->name,
            'slug' => $this->slug,
            'summary' => $this->summary,
            'description' => $this->description,
            'source_type' => $this->resource->sourceTypeValue(),
            'visibility' => $this->visibility,
            'commercial_type' => $this->commercial_type,
            'status' => $this->resource->statusValue(),
            'price' => [
                'amount_minor' => $this->price_amount_minor,
                'currency_code' => $this->price_currency,
            ],
            'support_email' => $this->support_email,
            'support_url' => $this->support_url,
            'documentation_url' => $this->documentation_url,
            'demo_url' => $this->demo_url,
            'listing_metadata' => $this->listing_metadata,
            'is_featured' => $this->is_featured,
            'published_at' => $this->published_at?->toIso8601String(),
            'current_version' => new ThemeVersionResource($this->whenLoaded('currentVersion')),
            'versions' => ThemeVersionResource::collection($this->whenLoaded('versions')),
            'categories' => ThemeCategoryResource::collection($this->whenLoaded('categories')),
            'license_count' => $this->whenCounted('licenses'),
            'install_count' => $this->whenCounted('installations'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
