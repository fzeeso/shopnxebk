<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Billing\Models\Plan;

/** @extends JsonResource<Plan> */
final class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'best_for' => $this->best_for,
            'price' => [
                'amount_minor' => $this->price_amount,
                'currency_code' => $this->currency_code,
                'billing_interval' => $this->resource->billingIntervalValue(),
                'is_custom' => $this->is_custom_pricing,
            ],
            'status' => $this->resource->statusValue(),
            'is_featured' => $this->is_featured,
            'sort_order' => $this->sort_order,
            'features' => PlanFeatureResource::collection($this->whenLoaded('planFeatures')),
        ];
    }
}
