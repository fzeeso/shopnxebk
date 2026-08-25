<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Stores\Models\Concerns\StoreScoped;

#[Fillable(['store_id', 'product_modifier_assignment_id', 'currency_code', 'adjustment_type', 'amount', 'channel_id', 'customer_group_id', 'starts_at', 'ends_at', 'is_active'])]
final class ProductModifierPriceOverride extends Model
{
    use StoreScoped;

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ProductModifierAssignment::class, 'product_modifier_assignment_id');
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:4', 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'is_active' => 'boolean'];
    }
}
