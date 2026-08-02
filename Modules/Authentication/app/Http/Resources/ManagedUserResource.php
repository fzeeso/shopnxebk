<?php

declare(strict_types=1);

namespace Modules\Authentication\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Authentication\Models\User;

/** @extends JsonResource<User> */
final class ManagedUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'email' => $this->email,
            'scope' => $this->resource->scopeValue(),
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'mfa_enabled' => $this->two_factor_confirmed_at !== null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'roles' => $this->whenLoaded('roles', fn (): array => $this->resource->roles
                ->pluck('name')
                ->map(static fn (mixed $name): string => (string) $name)
                ->sort()
                ->values()
                ->all()),
            'membership' => $this->whenPivotLoaded('store_users', fn (): array => [
                'status' => $this->pivot->status instanceof \BackedEnum
                    ? $this->pivot->status->value
                    : (string) $this->pivot->status,
                'invited_at' => $this->pivot->invited_at === null ? null : (string) $this->pivot->invited_at,
                'joined_at' => $this->pivot->joined_at === null ? null : (string) $this->pivot->joined_at,
            ]),
        ];
    }
}
