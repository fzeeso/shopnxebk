<?php

declare(strict_types=1);

namespace Modules\Tenancy\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Authentication\Models\User;
use Modules\Tenancy\Database\Factories\TenantFactory;
use Modules\Tenancy\Enums\TenantStatus;
use Spatie\Multitenancy\Models\Tenant as BaseTenant;

#[Fillable(['name', 'slug', 'status', 'primary_domain', 'settings', 'metadata'])]
final class Tenant extends BaseTenant
{
    use HasUuids;

    protected static function newFactory(): TenantFactory
    {
        return TenantFactory::new();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_memberships')->withPivot(['id', 'status', 'invited_at', 'joined_at'])->withTimestamps();
    }

    public function getDatabaseName(): string
    {
        return (string) config('database.connections.pgsql.database');
    }

    protected function casts(): array
    {
        return ['status' => TenantStatus::class, 'settings' => 'array', 'metadata' => 'array'];
    }
}
