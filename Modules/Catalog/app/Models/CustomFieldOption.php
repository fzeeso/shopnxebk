<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Stores\Models\Concerns\StoreScoped;
use Modules\Stores\Models\Store;

#[Fillable(['store_id', 'definition_id', 'position'])]
final class CustomFieldOption extends Model
{
    use HasPublicId, StoreScoped;

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(CustomFieldDefinition::class, 'definition_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(CustomFieldOptionTranslation::class, 'option_id')->orderBy('locale');
    }

    public function multiSelectValues(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductCustomFieldValue::class,
            'product_custom_field_value_options',
            'option_id',
            'value_id',
        )->withPivot(['store_id', 'definition_id', 'created_at']);
    }

    public function singleSelectValues(): HasMany
    {
        return $this->hasMany(ProductCustomFieldValue::class, 'value_option_id');
    }

    public function definitionPublicId(): string
    {
        return (string) $this->definition->public_id;
    }

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }
}
