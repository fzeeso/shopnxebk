<?php

declare(strict_types=1);

namespace Modules\Tenancy\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Authentication\Models\User;
use Modules\Tenancy\Database\Factories\TenantMembershipFactory;
use Modules\Tenancy\Enums\MembershipStatus;

#[Fillable(['tenant_id', 'user_id', 'status', 'invited_at', 'joined_at'])]
final class TenantMembership extends Model
{
    use HasFactory, HasUuids;

    protected static function newFactory(): TenantMembershipFactory
    {
        return TenantMembershipFactory::new();
    }

    protected $table = 'tenant_memberships';

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['status' => MembershipStatus::class, 'invited_at' => 'immutable_datetime', 'joined_at' => 'immutable_datetime'];
    }
}
