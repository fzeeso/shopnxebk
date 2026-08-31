<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Stores\Models\Concerns\StoreScoped;
use Modules\Stores\Models\Store;

#[Fillable([
    'store_id',
    'custom_object_field_id',
    'locale',
    'label',
    'description',
    'help_text',
    'placeholder',
    'lock_it',
])]
final class CustomObjectFieldTranslation extends Model
{
    use StoreScoped;

    public $incrementing = false;

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(CustomObjectField::class, 'custom_object_field_id');
    }

    protected function casts(): array
    {
        return ['lock_it' => 'boolean'];
    }
}
