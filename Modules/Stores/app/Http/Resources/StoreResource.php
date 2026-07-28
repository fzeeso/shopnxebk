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
        return ['id' => $this->public_id, 'name' => $this->name, 'slug' => $this->slug, 'status' => $this->status->value ?? $this->status, 'primary_domain' => $this->primary_domain];
    }
}
