<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Stores\Models\Concerns\StoreScoped;

#[Fillable(['store_id', 'rule_id', 'locale', 'message', 'lock_it'])]
final class ModifierValidationRuleTranslation extends Model
{
    use StoreScoped;

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ModifierValidationRule::class, 'rule_id');
    }

    protected function casts(): array
    {
        return ['lock_it' => 'boolean'];
    }
}
