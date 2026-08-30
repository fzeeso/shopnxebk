<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Authentication\Models\User;
use Modules\Settings\Models\Language;
use Modules\Stores\Database\Factories\StoreFactory;
use Modules\Stores\Enums\BusinessType;
use Modules\Stores\Enums\StoreStatus;
use Modules\Themes\Models\StoreTheme;
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

    public function primaryMembership(): HasOne
    {
        return $this->hasOne(StoreMembership::class)->oldestOfMany();
    }

    public function storeLanguages(): HasMany
    {
        return $this->hasMany(StoreLanguage::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(StoreDomain::class);
    }

    public function storeSettings(): HasOne
    {
        return $this->hasOne(StoreSetting::class);
    }

    public function localeSettings(): HasOne
    {
        return $this->hasOne(StoreLocaleSetting::class);
    }

    public function themes(): HasMany
    {
        return $this->hasMany(StoreTheme::class);
    }

    public function policies(): HasMany
    {
        return $this->hasMany(StorePolicy::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function activeTheme(): HasOne
    {
        return $this->hasOne(StoreTheme::class)->where('status', 'published');
    }

    public function languages(): BelongsToMany
    {
        return $this->belongsToMany(Language::class, 'store_languages')
            ->withPivot(['is_default', 'is_active'])
            ->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'store_users')
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
