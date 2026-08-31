<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Stores\Models\Concerns\StoreScoped;
use Modules\Stores\Models\Store;

#[Fillable([
    'store_id',
    'custom_object_type_id',
    'handle',
    'field_type',
    'is_required',
    'is_unique',
    'is_localized',
    'is_searchable',
    'is_filterable',
    'sort_order',
    'reference_object_type_id',
    'settings',
    'validation_rules',
    'status',
])]
final class CustomObjectField extends Model
{
    use HasPublicId, SoftDeletes, StoreScoped;

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(CustomObjectType::class, 'custom_object_type_id');
    }

    public function referenceObjectType(): BelongsTo
    {
        return $this->belongsTo(CustomObjectType::class, 'reference_object_type_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(CustomObjectFieldTranslation::class)->orderBy('locale');
    }

    public function values(): HasMany
    {
        return $this->hasMany(CustomObjectValue::class)->orderBy('id');
    }

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_unique' => 'boolean',
            'is_localized' => 'boolean',
            'is_searchable' => 'boolean',
            'is_filterable' => 'boolean',
            'sort_order' => 'integer',
            'settings' => 'array',
            'validation_rules' => 'array',
        ];
    }
}
