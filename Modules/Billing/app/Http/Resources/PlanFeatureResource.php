<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Billing\Models\PlanFeature;

/** @extends JsonResource<PlanFeature> */
final class PlanFeatureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'feature' => new FeatureResource($this->whenLoaded('feature')),
            'value' => $this->value,
            'is_included' => $this->is_included,
            'is_addon' => $this->is_addon,
            'addon_price' => $this->is_addon ? [
                'amount_minor' => $this->addon_price_amount,
                'currency_code' => $this->addon_currency_code,
                'billing_interval' => $this->resource->addonBillingIntervalValue(),
            ] : null,
            'sort_order' => $this->sort_order,
        ];
    }
}
