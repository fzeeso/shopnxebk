<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Stores\Models\Concerns\StoreScoped;

#[Fillable(['store_id', 'product_id', 'modifier_id', 'modifier_group_id', 'sort_order', 'is_active', 'is_required_override', 'min_selections_override', 'max_selections_override', 'settings_override'])]
final class ProductModifierAssignment extends Model
{
    use HasPublicId, SoftDeletes, StoreScoped;

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function modifier(): BelongsTo
    {
        return $this->belongsTo(ModifierDefinition::class, 'modifier_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ProductModifierGroup::class, 'modifier_group_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(ProductModifierAssignmentTranslation::class, 'product_modifier_assignment_id')->orderBy('locale');
    }

    public function valueAssignments(): HasMany
    {
        return $this->hasMany(ProductModifierValueAssignment::class, 'product_modifier_assignment_id');
    }

    public function priceOverrides(): HasMany
    {
        return $this->hasMany(ProductModifierPriceOverride::class, 'product_modifier_assignment_id');
    }

    public function valuePriceOverrides(): HasMany
    {
        return $this->hasMany(ProductModifierValuePriceOverride::class, 'product_modifier_assignment_id');
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'is_required_override' => 'boolean',
            'min_selections_override' => 'integer',
            'max_selections_override' => 'integer',
            'settings_override' => 'array',
        ];
    }
}
