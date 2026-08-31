<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Stores\Models\Concerns\StoreScoped;
use Modules\Stores\Models\Store;

#[Fillable(['store_id', 'custom_object_entry_id', 'locale', 'name', 'description', 'lock_it'])]
final class CustomObjectEntryTranslation extends Model
{
    use StoreScoped;

    public $incrementing = false;

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(CustomObjectEntry::class, 'custom_object_entry_id');
    }

    protected function casts(): array
    {
        return ['lock_it' => 'boolean'];
    }
}
