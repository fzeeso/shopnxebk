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

#[Fillable([
    'store_id',
    'product_id',
    'variant_id',
    'definition_id',
    'value_number',
    'value_boolean',
    'value_date',
    'value_option_id',
])]
final class ProductCustomFieldValue extends Model
{
    use HasPublicId, StoreScoped;

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(CustomFieldDefinition::class, 'definition_id');
    }

    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(CustomFieldOption::class, 'value_option_id');
    }

    public function selectedOptions(): BelongsToMany
    {
        return $this->belongsToMany(
            CustomFieldOption::class,
            'product_custom_field_value_options',
            'value_id',
            'option_id',
        )->withPivot(['store_id', 'definition_id', 'created_at'])
            ->orderBy('position')
            ->orderBy('id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(ProductCustomFieldValueTranslation::class, 'value_id')->orderBy('locale');
    }

    public function productPublicId(): string
    {
        return (string) $this->product->public_id;
    }

    public function variantPublicId(): ?string
    {
        return $this->variant?->public_id;
    }

    public function definitionPublicId(): string
    {
        return (string) $this->definition->public_id;
    }

    public function selectedOptionPublicId(): ?string
    {
        return $this->selectedOption?->public_id;
    }

    public function valueDateString(): ?string
    {
        return $this->value_date?->format('Y-m-d');
    }

    protected function casts(): array
    {
        return [
            'value_number' => 'decimal:4',
            'value_boolean' => 'boolean',
            'value_date' => 'immutable_date',
        ];
    }
}
