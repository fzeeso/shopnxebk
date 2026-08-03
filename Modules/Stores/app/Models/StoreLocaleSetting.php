<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'store_id',
    'date_format',
    'time_format',
    'week_starts_on',
    'weight_unit',
    'dimension_unit',
    'decimal_places',
    'decimal_separator',
    'thousands_separator',
])]
final class StoreLocaleSetting extends Model
{
    public $incrementing = false;

    protected $table = 'store_locale_settings';

    protected $primaryKey = 'store_id';

    protected $keyType = 'int';

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    protected function casts(): array
    {
        return [
            'decimal_places' => 'integer',
        ];
    }
}
