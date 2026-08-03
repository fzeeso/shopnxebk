<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Stores\Models\StoreDomain;

/** @extends JsonResource<StoreDomain> */
final class StoreDomainResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'domain' => $this->domain,
            'domain_type' => $this->domain_type,
            'is_primary' => $this->is_primary,
            'status' => $this->status,
            'ssl_status' => $this->ssl_status,
            'is_verified' => $this->verified_at !== null,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
