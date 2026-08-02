<?php

declare(strict_types=1);

namespace Modules\Themes\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Themes\Models\ThemePublisher;

/** @extends JsonResource<ThemePublisher> */
final class ThemePublisherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'owner_user_id' => $this->whenLoaded('owner', fn () => $this->owner?->public_id),
            'publisher_type' => $this->publisher_type,
            'display_name' => $this->display_name,
            'slug' => $this->slug,
            'status' => $this->status,
            'support_email' => $this->support_email,
            'support_url' => $this->support_url,
            'website_url' => $this->website_url,
            'payout_account_reference' => $this->payout_account_reference,
            'default_commission_bps' => $this->default_commission_bps,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'terms_accepted_at' => $this->terms_accepted_at?->toIso8601String(),
        ];
    }
}
