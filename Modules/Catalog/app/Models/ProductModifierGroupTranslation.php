<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Stores\Models\Concerns\StoreScoped;

#[Fillable(['store_id', 'group_id', 'locale', 'name', 'description', 'lock_it'])]
final class ProductModifierGroupTranslation extends Model
{
    use StoreScoped;

    public function group(): BelongsTo
    {
        return $this->belongsTo(ProductModifierGroup::class, 'group_id');
    }

    protected function casts(): array
    {
        return ['lock_it' => 'boolean'];
    }
}
