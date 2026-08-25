<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Stores\Models\Concerns\StoreScoped;

#[Fillable(['store_id', 'modifier_value_id', 'currency_code', 'adjustment_type', 'amount', 'channel_id', 'customer_group_id', 'starts_at', 'ends_at', 'is_active'])]
final class ModifierValuePriceAdjustment extends Model
{
    use StoreScoped;

    public function value(): BelongsTo
    {
        return $this->belongsTo(ModifierValue::class, 'modifier_value_id');
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:4', 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'is_active' => 'boolean'];
    }
}
