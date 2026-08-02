<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Authentication\Models\User;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StoreMembership;

/** @extends JsonResource<Store> */
final class PlatformStoreListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $store = (new StoreResource($this->resource))->toArray($request);
        $membership = $this->resource->relationLoaded('primaryMembership')
            ? $this->resource->primaryMembership
            : null;
        $owner = $membership instanceof StoreMembership && $membership->relationLoaded('user')
            ? $membership->user
            : null;

        return [
            ...$store,
            'owner' => $owner instanceof User ? [
                'id' => $owner->public_id,
                'name' => $owner->name,
                'email' => $owner->email,
            ] : null,
        ];
    }
}
