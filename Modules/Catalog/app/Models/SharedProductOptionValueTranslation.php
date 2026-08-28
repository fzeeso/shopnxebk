<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Stores\Models\Concerns\StoreScoped;

#[Fillable(['store_id', 'option_value_id', 'locale', 'display_label', 'lock_it'])]
final class SharedProductOptionValueTranslation extends Model
{
    use StoreScoped;

    public function value(): BelongsTo
    {
        return $this->belongsTo(SharedProductOptionValue::class, 'option_value_id');
    }

    protected function casts(): array
    {
        return ['lock_it' => 'boolean'];
    }
}
