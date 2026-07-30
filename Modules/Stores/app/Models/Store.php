<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Authentication\Models\User;
use Modules\Settings\Models\Language;
use Modules\Stores\Database\Factories\StoreFactory;
use Modules\Stores\Enums\BusinessType;
use Modules\Stores\Enums\StoreStatus;
use Spatie\Multitenancy\Models\Tenant as BaseTenant;

#[Fillable([
    'name',
    'legal_name',
    'description',
    'email',
    'phone',
    'slug',
    'status',
    'primary_domain',
    'logo',
    'favicon',
    'cover_image',
    'industry',
    'business_type',
    'plan_id',
    'subscription_id',
    'currency_code',
    'language_code',
    'timezone',
    'country_code',
    'is_verified',
    'is_ai_enabled',
    'is_pos_enabled',
    'is_b2b_enabled',
    'is_marketplace_enabled',
    'launched_at',
    'trial_ends_at',
    'settings',
    'metadata',
])]
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

    public function storeLanguages(): HasMany
    {
        return $this->hasMany(StoreLanguage::class);
    }

    public function languages(): BelongsToMany
    {
        return $this->belongsToMany(Language::class, 'store_languages')
            ->withPivot(['is_default', 'is_active'])
            ->withTimestamps();
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

    public function statusValue(): string
    {
        return $this->status instanceof StoreStatus ? $this->status->value : (string) $this->status;
    }

    public function businessTypeValue(): ?string
    {
        if ($this->business_type === null) {
            return null;
        }

        return $this->business_type instanceof BusinessType ? $this->business_type->value : (string) $this->business_type;
    }

    protected function casts(): array
    {
        return [
            'status' => StoreStatus::class,
            'business_type' => BusinessType::class,
            'is_verified' => 'boolean',
            'is_ai_enabled' => 'boolean',
            'is_pos_enabled' => 'boolean',
            'is_b2b_enabled' => 'boolean',
            'is_marketplace_enabled' => 'boolean',
            'launched_at' => 'immutable_datetime',
            'trial_ends_at' => 'immutable_datetime',
            'settings' => 'array',
            'metadata' => 'array',
        ];
    }
}
