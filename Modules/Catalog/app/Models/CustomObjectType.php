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

#[Fillable(['store_id', 'handle', 'status', 'is_system', 'created_by', 'updated_by'])]
final class CustomObjectType extends Model
{
    use HasPublicId, SoftDeletes, StoreScoped;

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
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
        return $this->hasMany(CustomObjectTypeTranslation::class)->orderBy('locale');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(CustomObjectField::class)->orderBy('sort_order')->orderBy('id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(CustomObjectEntry::class)->orderBy('sort_order')->orderBy('id');
    }

    public function referencingFields(): HasMany
    {
        return $this->hasMany(CustomObjectField::class, 'reference_object_type_id');
    }

    public function customFieldDefinitions(): HasMany
    {
        return $this->hasMany(CustomFieldDefinition::class, 'reference_object_type_id');
    }

    public function references(): HasMany
    {
        return $this->hasMany(CustomObjectReference::class);
    }

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }
}
