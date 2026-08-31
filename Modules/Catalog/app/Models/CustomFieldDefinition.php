<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Stores\Models\Concerns\StoreScoped;
use Modules\Stores\Models\Store;

#[Fillable([
    'store_id',
    'product_type',
    'field_key',
    'field_type',
    'reference_object_type_id',
    'is_required',
    'is_filterable',
    'position',
])]
final class CustomFieldDefinition extends Model
{
    use HasPublicId, StoreScoped;

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function platformTaxonomyFields(): HasMany
    {
        return $this->hasMany(PlatformTaxonomyCustomField::class, 'custom_field_definition_id')
            ->orderBy('position')
            ->orderBy('id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(CustomFieldDefinitionTranslation::class, 'definition_id')->orderBy('locale');
    }

    public function options(): HasMany
    {
        return $this->hasMany(CustomFieldOption::class, 'definition_id')->orderBy('position')->orderBy('id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(ProductCustomFieldValue::class, 'definition_id');
    }

    public function referenceObjectType(): BelongsTo
    {
        return $this->belongsTo(CustomObjectType::class, 'reference_object_type_id');
    }

    public function objectReferences(): HasMany
    {
        return $this->hasMany(CustomObjectReference::class, 'custom_field_definition_id');
    }

    public function referenceObjectTypePublicId(): ?string
    {
        return $this->referenceObjectType?->public_id;
    }

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_filterable' => 'boolean',
            'position' => 'integer',
        ];
    }
}
