<?php

declare(strict_types=1);

namespace Modules\Authentication\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;
use Modules\Tenancy\Models\Tenant;

#[Fillable(['name', 'token', 'abilities', 'tenant_id', 'expires_at', 'metadata'])]
/** @property string|null $tenant_id @property \Carbon\CarbonImmutable|null $expires_at */
final class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use HasUuids;

    /** @return BelongsTo<Tenant, self> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'metadata' => 'array',
            'last_used_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
