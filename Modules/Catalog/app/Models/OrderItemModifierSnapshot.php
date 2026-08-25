<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Modules\Stores\Models\Concerns\StoreScoped;

#[Fillable(['store_id', 'order_item_id', 'modifier_id', 'modifier_value_id', 'modifier_public_id', 'value_public_id', 'modifier_code', 'modifier_name', 'value_code', 'value_name', 'input_value', 'price_adjustment', 'currency_code', 'locale', 'metadata', 'created_at'])]
final class OrderItemModifierSnapshot extends Model
{
    use StoreScoped;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['input_value' => 'array', 'price_adjustment' => 'decimal:4', 'metadata' => 'array', 'created_at' => 'immutable_datetime'];
    }
}
