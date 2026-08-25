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

#[Fillable(['store_id', 'product_id', 'code', 'sort_order', 'is_active', 'settings'])]
final class ProductModifierGroup extends Model
{
    use HasPublicId, SoftDeletes, StoreScoped;

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(ProductModifierGroupTranslation::class, 'group_id')->orderBy('locale');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ProductModifierAssignment::class, 'modifier_group_id')->orderBy('sort_order')->orderBy('id');
    }

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean', 'settings' => 'array'];
    }
}
