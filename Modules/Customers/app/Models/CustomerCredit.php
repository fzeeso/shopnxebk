<?php

declare(strict_types=1);

namespace Modules\Customers\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Authentication\Models\User;
use Modules\Customers\Enums\CustomerCreditType;
use Modules\Stores\Models\Concerns\StoreScoped;
use Modules\Stores\Models\Store;

#[Fillable([
    'store_id',
    'customer_id',
    'amount',
    'type',
    'external_reference',
    'created_by',
    'reason',
    'occurred_at',
])]
final class CustomerCredit extends Model
{
    use HasPublicId, StoreScoped;

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'type' => CustomerCreditType::class,
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
