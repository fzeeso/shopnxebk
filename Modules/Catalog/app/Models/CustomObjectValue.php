<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\Media;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Stores\Models\Concerns\StoreScoped;
use Modules\Stores\Models\Store;

#[Fillable([
    'store_id',
    'custom_object_type_id',
    'custom_object_entry_id',
    'custom_object_field_id',
    'value_text',
    'value_number',
    'value_boolean',
    'value_date',
    'value_datetime',
    'value_json',
    'value_media_id',
])]
final class CustomObjectValue extends Model
{
    use HasPublicId, StoreScoped;

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(CustomObjectType::class, 'custom_object_type_id');
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(CustomObjectEntry::class, 'custom_object_entry_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(CustomObjectField::class, 'custom_object_field_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'value_media_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(CustomObjectValueTranslation::class)->orderBy('locale');
    }

    public function referencedEntries(): BelongsToMany
    {
        return $this->belongsToMany(
            CustomObjectEntry::class,
            'custom_object_value_references',
            'custom_object_value_id',
            'custom_object_entry_id',
        )->withPivot(['store_id', 'sort_order', 'created_at', 'updated_at'])
            ->orderByPivot('sort_order')
            ->orderBy('custom_object_entries.id');
    }

    protected function casts(): array
    {
        return [
            'value_number' => 'decimal:8',
            'value_boolean' => 'boolean',
            'value_date' => 'immutable_date',
            'value_datetime' => 'immutable_datetime',
            'value_json' => 'array',
        ];
    }
}
