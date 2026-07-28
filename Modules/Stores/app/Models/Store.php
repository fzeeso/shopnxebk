<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Authentication\Models\User;
use Modules\Stores\Database\Factories\StoreFactory;
use Modules\Stores\Enums\StoreStatus;
use Spatie\Multitenancy\Models\Tenant as BaseTenant;

#[Fillable(['name', 'slug', 'status', 'primary_domain', 'settings', 'metadata'])]
final class Store extends BaseTenant
{
    /** @use HasFactory<StoreFactory> */
    use HasFactory, HasPublicId;

    protected static function newFactory(): StoreFactory
    {
        return StoreFactory::new();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(StoreMembership::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'store_memberships')
            ->withPivot(['id', 'public_id', 'status', 'invited_at', 'joined_at'])
            ->withTimestamps();
    }

    public function getDatabaseName(): string
    {
        return (string) config('database.connections.pgsql.database');
    }

    protected function casts(): array
    {
        return ['status' => StoreStatus::class, 'settings' => 'array', 'metadata' => 'array'];
    }
}
