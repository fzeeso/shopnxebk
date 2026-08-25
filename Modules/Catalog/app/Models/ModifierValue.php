<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\Media;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Stores\Models\Concerns\StoreScoped;

#[Fillable(['store_id', 'modifier_id', 'code', 'sort_order', 'is_default', 'is_active', 'colour_value', 'image_id', 'icon', 'settings'])]
final class ModifierValue extends Model
{
    use HasPublicId, SoftDeletes, StoreScoped;

    public function modifier(): BelongsTo
    {
        return $this->belongsTo(ModifierDefinition::class, 'modifier_id');
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'image_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(ModifierValueTranslation::class, 'modifier_value_id')->orderBy('locale');
    }

    public function priceAdjustments(): HasMany
    {
        return $this->hasMany(ModifierValuePriceAdjustment::class, 'modifier_value_id');
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }
}
