<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Authentication\Http\Resources\ManagedUserResource;
use Modules\Stores\Models\Store;

/** @extends JsonResource<Store> */
final class MerchantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $owner = $this->resource->relationLoaded('users')
            ? ($this->resource->users->first(fn ($user): bool => $user->roles->contains('name', 'Owner'))
                ?? $this->resource->users->first())
            : null;

        return [
            'store' => new StoreResource($this->resource),
            'owner' => $owner === null ? null : new ManagedUserResource($owner),
            'users' => ManagedUserResource::collection($this->whenLoaded('users')),
        ];
    }
}
