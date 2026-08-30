<?php

declare(strict_types=1);

namespace Modules\Customers\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Customers\Enums\CustomerStatus;
use Modules\Stores\Models\Concerns\StoreScoped;
use Modules\Stores\Models\Store;

#[Fillable([
    'store_id',
    'customer_group_id',
    'email',
    'company',
    'first_name',
    'last_name',
    'phone',
    'status',
    'registered_ip',
    'admin_notes',
    'points_balance',
    'redeemed_points',
    'email_verified_at',
    'joined_at',
    'last_activity_at',
])]
final class Customer extends Model
{
    use HasPublicId, SoftDeletes, StoreScoped;

    /** @var list<string> */
    protected $hidden = [
        'password',
        'legacy_password_hash',
        'legacy_password_salt',
        'legacy_import_password_hash',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
    }

    public function credits(): HasMany
    {
        return $this->hasMany(CustomerCredit::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }

    protected function casts(): array
    {
        return [
            'status' => CustomerStatus::class,
            'points_balance' => 'integer',
            'redeemed_points' => 'integer',
            'email_verified_at' => 'immutable_datetime',
            'joined_at' => 'immutable_datetime',
            'last_activity_at' => 'immutable_datetime',
        ];
    }
}
