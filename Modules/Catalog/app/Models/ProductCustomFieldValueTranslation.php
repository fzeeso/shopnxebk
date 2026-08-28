<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Stores\Models\Concerns\StoreScoped;
use Modules\Stores\Models\Store;

#[Fillable(['store_id', 'value_id', 'locale', 'value_text', 'lock_it'])]
final class ProductCustomFieldValueTranslation extends Model
{
    use StoreScoped;

    public $incrementing = false;

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function value(): BelongsTo
    {
        return $this->belongsTo(ProductCustomFieldValue::class, 'value_id');
    }

    protected function casts(): array
    {
        return ['lock_it' => 'boolean'];
    }
}
