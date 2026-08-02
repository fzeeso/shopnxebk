<?php

declare(strict_types=1);

namespace Modules\Themes\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Themes\Models\ThemeLicense;

/** @extends JsonResource<ThemeLicense> */
final class ThemeLicenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'theme_id' => $this->whenLoaded('theme', fn () => $this->theme?->public_id),
            'store_id' => $this->whenLoaded('store', fn () => $this->store?->public_id),
            'license_type' => $this->license_type,
            'status' => $this->status,
            'billing_order_item_id' => $this->billing_order_item_id,
            'purchased_by_user_id' => $this->whenLoaded('purchaser', fn () => $this->purchaser?->public_id),
            'issued_at' => $this->issued_at?->toIso8601String(),
            'trial_expires_at' => $this->trial_expires_at?->toIso8601String(),
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'refunded_at' => $this->refunded_at?->toIso8601String(),
        ];
    }
}
