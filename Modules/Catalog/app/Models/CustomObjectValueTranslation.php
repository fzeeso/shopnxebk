<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Stores\Models\Concerns\StoreScoped;
use Modules\Stores\Models\Store;

#[Fillable(['store_id', 'custom_object_value_id', 'locale', 'value_text', 'value_json', 'lock_it'])]
final class CustomObjectValueTranslation extends Model
{
    use StoreScoped;

    public $incrementing = false;

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function value(): BelongsTo
    {
        return $this->belongsTo(CustomObjectValue::class, 'custom_object_value_id');
    }

    protected function casts(): array
    {
        return [
            'value_json' => 'array',
            'lock_it' => 'boolean',
        ];
    }
}
