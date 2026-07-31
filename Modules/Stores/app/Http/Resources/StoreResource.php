<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Stores\Models\Store;

/** @extends JsonResource<Store> */
final class StoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'legal_name' => $this->legal_name,
            'description' => $this->description,
            'email' => $this->email,
            'phone' => $this->phone,
            'slug' => $this->slug,
            'status' => $this->resource->statusValue(),
            'primary_domain' => $this->primary_domain,
            'logo' => $this->logo,
            'favicon' => $this->favicon,
            'cover_image' => $this->cover_image,
            'industry' => $this->industry,
            'business_type' => $this->resource->businessTypeValue(),
            'currency_code' => $this->currency_code,
            'language_code' => $this->language_code,
            'timezone' => $this->timezone,
            'country_code' => $this->country_code,
            'is_verified' => $this->is_verified,
            'is_ai_enabled' => $this->is_ai_enabled,
            'is_pos_enabled' => $this->is_pos_enabled,
            'is_b2b_enabled' => $this->is_b2b_enabled,
            'is_marketplace_enabled' => $this->is_marketplace_enabled,
            'launched_at' => $this->launched_at?->toIso8601String(),
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
