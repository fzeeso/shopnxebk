<?php

declare(strict_types=1);

namespace Modules\Tenancy\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Tenancy\Models\Tenant;

/** @extends JsonResource<Tenant> */
final class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'slug' => $this->slug, 'status' => $this->status->value ?? $this->status, 'primary_domain' => $this->primary_domain];
    }
}
