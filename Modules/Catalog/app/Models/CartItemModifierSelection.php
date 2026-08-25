<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Stores\Models\Concerns\StoreScoped;

#[Fillable(['store_id', 'cart_item_id', 'product_modifier_assignment_id', 'modifier_id', 'modifier_value_id', 'input_value', 'price_adjustment', 'currency_code'])]
final class CartItemModifierSelection extends Model
{
    use StoreScoped;

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ProductModifierAssignment::class, 'product_modifier_assignment_id')->withTrashed();
    }

    public function modifier(): BelongsTo
    {
        return $this->belongsTo(ModifierDefinition::class, 'modifier_id')->withTrashed();
    }

    public function value(): BelongsTo
    {
        return $this->belongsTo(ModifierValue::class, 'modifier_value_id')->withTrashed();
    }

    protected function casts(): array
    {
        return ['input_value' => 'array', 'price_adjustment' => 'decimal:4'];
    }
}
