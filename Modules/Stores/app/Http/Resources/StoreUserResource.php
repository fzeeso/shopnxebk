<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Modules\Authentication\Models\User;

/** @extends JsonResource<User> */
final class StoreUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'email' => $this->email,
            'scope' => $this->resource->scopeValue(),
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'membership' => [
                'id' => $this->pivot?->public_id,
                'status' => $this->pivot?->status instanceof \BackedEnum
                    ? $this->pivot->status->value
                    : $this->pivot?->status,
                'joined_at' => $this->pivot?->joined_at === null
                    ? null
                    : Carbon::parse((string) $this->pivot->joined_at)->toISOString(),
            ],
            'roles' => $this->whenLoaded('roles', fn (): array => $this->resource->roles
                ->pluck('name')
                ->map(static fn (mixed $name): string => (string) $name)
                ->sort()
                ->values()
                ->all()),
        ];
    }
}
