<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Stores\Models\Concerns\StoreScoped;

#[Fillable(['store_id', 'modifier_id', 'rule_type', 'rule_value', 'sort_order', 'is_active'])]
final class ModifierValidationRule extends Model
{
    use StoreScoped;

    public function modifier(): BelongsTo
    {
        return $this->belongsTo(ModifierDefinition::class, 'modifier_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(ModifierValidationRuleTranslation::class, 'rule_id')->orderBy('locale');
    }

    protected function casts(): array
    {
        return ['rule_value' => 'array', 'sort_order' => 'integer', 'is_active' => 'boolean'];
    }
}
