<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Authentication\Models\User;
use Modules\Stores\Database\Factories\StoreMembershipFactory;
use Modules\Stores\Enums\MembershipStatus;

#[Fillable(['store_id', 'user_id', 'status', 'invited_at', 'joined_at'])]
final class StoreMembership extends Model
{
    use HasFactory, HasPublicId;

    protected static function newFactory(): StoreMembershipFactory
    {
        return StoreMembershipFactory::new();
    }

    protected $table = 'store_memberships';

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
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
