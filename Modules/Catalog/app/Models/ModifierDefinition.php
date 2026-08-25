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

#[Fillable(['store_id', 'library_category_id', 'code', 'type', 'is_active', 'is_required_default', 'supports_multiple', 'min_selections', 'max_selections', 'sort_order', 'settings'])]
final class ModifierDefinition extends Model
{
    use HasPublicId, SoftDeletes, StoreScoped;

    public function category(): BelongsTo
    {
        return $this->belongsTo(ModifierLibraryCategory::class, 'library_category_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(ModifierTranslation::class, 'modifier_id')->orderBy('locale');
    }

    public function values(): HasMany
    {
        return $this->hasMany(ModifierValue::class, 'modifier_id')->orderBy('sort_order')->orderBy('id');
    }

    public function validationRules(): HasMany
    {
        return $this->hasMany(ModifierValidationRule::class, 'modifier_id')->orderBy('sort_order')->orderBy('id');
    }

    public function priceAdjustments(): HasMany
    {
        return $this->hasMany(ModifierPriceAdjustment::class, 'modifier_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ProductModifierAssignment::class, 'modifier_id');
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_required_default' => 'boolean',
            'supports_multiple' => 'boolean',
            'min_selections' => 'integer',
            'max_selections' => 'integer',
            'sort_order' => 'integer',
            'settings' => 'array',
        ];
    }
}
