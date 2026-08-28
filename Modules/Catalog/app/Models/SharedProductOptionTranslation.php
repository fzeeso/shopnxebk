<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Stores\Models\Concerns\StoreScoped;

#[Fillable(['store_id', 'option_id', 'locale', 'display_name', 'lock_it'])]
final class SharedProductOptionTranslation extends Model
{
    use StoreScoped;

    public function option(): BelongsTo
    {
        return $this->belongsTo(SharedProductOption::class, 'option_id');
    }

    protected function casts(): array
    {
        return ['lock_it' => 'boolean'];
    }
}
