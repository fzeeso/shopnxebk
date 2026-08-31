<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Authentication\Models\User;
use Modules\Stores\Models\Concerns\StoreScoped;
use Modules\Stores\Models\Store;

#[Fillable([
    'store_id',
    'custom_object_type_id',
    'handle',
    'status',
    'sort_order',
    'created_by',
    'updated_by',
])]
final class CustomObjectEntry extends Model
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(CustomObjectEntryTranslation::class)->orderBy('locale');
    }

    public function values(): HasMany
    {
        return $this->hasMany(CustomObjectValue::class)->orderBy('custom_object_field_id')->orderBy('id');
    }

    public function references(): HasMany
    {
        return $this->hasMany(CustomObjectReference::class);
    }

    public function valueReferences(): HasMany
    {
        return $this->hasMany(CustomObjectValueReference::class);
    }

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }
}
