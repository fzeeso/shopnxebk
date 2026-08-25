<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Stores\Models\Concerns\StoreScoped;

#[Fillable(['store_id', 'product_modifier_assignment_id', 'modifier_id', 'modifier_value_id', 'is_enabled', 'is_default_override', 'sort_order', 'settings_override'])]
final class ProductModifierValueAssignment extends Model
{
    use StoreScoped;

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ProductModifierAssignment::class, 'product_modifier_assignment_id');
    }

    public function value(): BelongsTo
    {
        return $this->belongsTo(ModifierValue::class, 'modifier_value_id');
    }

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean', 'is_default_override' => 'boolean', 'sort_order' => 'integer', 'settings_override' => 'array'];
    }
}
