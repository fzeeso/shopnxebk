<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\Brand;

/** @extends JsonResource<Brand> */
final class BrandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'store_id' => $this->whenLoaded('store', fn () => $this->store?->public_id),
            'logo_url' => $this->logo_url,
            'website_url' => $this->website_url,
            'origin' => $this->origin,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'translations' => BrandTranslationResource::collection($this->whenLoaded('translations')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
