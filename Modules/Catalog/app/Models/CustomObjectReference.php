<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Stores\Models\Concerns\StoreScoped;
use Modules\Stores\Models\Store;

#[Fillable([
    'store_id',
    'source_type',
    'source_id',
    'custom_field_definition_id',
    'custom_object_type_id',
    'custom_object_entry_id',
    'sort_order',
])]
final class CustomObjectReference extends Model
{
    use HasPublicId, StoreScoped;

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(CustomFieldDefinition::class, 'custom_field_definition_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(CustomObjectType::class, 'custom_object_type_id');
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(CustomObjectEntry::class, 'custom_object_entry_id');
    }

    protected function casts(): array
    {
        return [
            'source_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
