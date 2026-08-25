<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Stores\Models\Concerns\StoreScoped;

#[Fillable(['store_id', 'modifier_value_id', 'locale', 'name', 'description', 'lock_it'])]
final class ModifierValueTranslation extends Model
{
    use StoreScoped;

    public function value(): BelongsTo
    {
        return $this->belongsTo(ModifierValue::class, 'modifier_value_id');
    }

    protected function casts(): array
    {
        return ['lock_it' => 'boolean'];
    }
}
