<?php

declare(strict_types=1);

namespace Modules\Settings\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'code',
    'symbol',
    'symbol_position',
    'decimal_places',
    'usd_exchange_rate',
    'is_base',
    'is_active',
    'exchange_rate_updated_at',
])]
final class Currency extends Model
{
    use HasPublicId;

    protected function casts(): array
    {
        return [
            'decimal_places' => 'integer',
            'usd_exchange_rate' => 'decimal:8',
            'is_base' => 'boolean',
            'is_active' => 'boolean',
            'exchange_rate_updated_at' => 'immutable_datetime',
        ];
    }
}
