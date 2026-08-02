<?php

namespace Modules\Authentication\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Authentication\Database\Factories\UserFactory;
use Modules\Authentication\Enums\AccessScope;
use Modules\Authentication\Notifications\QueuedResetPassword;
use Modules\Authentication\Notifications\QueuedVerifyEmail;
use Modules\Stores\Models\Store;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'scope'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
/**
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasPublicId, HasRoles, MustVerifyEmailTrait, Notifiable, TwoFactorAuthenticatable;

    /** @var array<string, mixed> */
    protected $attributes = [
        'two_factor_secret' => null,
        'two_factor_recovery_codes' => null,
        'two_factor_confirmed_at' => null,
    ];

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /** @return BelongsToMany<Store, self> */
    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'store_users')
            ->withPivot(['id', 'public_id', 'status', 'invited_at', 'joined_at'])
            ->withTimestamps();
    }

    public function isPlatformSuperAdmin(): bool
    {
        if (! $this->isPlatformUser()) {
            return false;
        }

        $previousStoreId = getPermissionsTeamId();
        setPermissionsTeamId(null);

        try {
            return $this->fresh()?->hasRole('Super Admin', 'web') ?? false;
        } finally {
            setPermissionsTeamId($previousStoreId);
        }
    }

    public function isPlatformUser(): bool
    {
        return $this->scope === AccessScope::Platform;
    }

    public function isStoreUser(): bool
    {
        return $this->scope === AccessScope::Store;
    }

    public function scopeValue(): string
    {
        return $this->scope instanceof AccessScope ? $this->scope->value : (string) $this->scope;
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new QueuedVerifyEmail);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new QueuedResetPassword((string) $token));
    }

    /** @return Attribute<string, string> */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => Str::lower(trim($value)));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => AccessScope::class,
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
