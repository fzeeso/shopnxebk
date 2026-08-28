<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\Media;
use App\Models\ProductVariantMedia;
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
    'sku',
    'barcode',
    'price_amount_minor',
    'compare_at_price_amount_minor',
    'msrp_amount_minor',
    'cost_per_item_amount_minor',
    'currency_code',
    'inventory_qty',
    'inventory_policy',
    'weight_grams',
    'height',
    'width',
    'depth',
    'dimension_unit',
    'requires_shipping',
    'taxable',
    'call_for_price',
    'image_id',
    'position',
])]
final class ProductVariant extends Model
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

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'variant_id');
    }

    public function preferredImage(): BelongsTo
    {
        return $this->belongsTo(ProductImage::class, 'image_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(ProductVariantTranslation::class, 'variant_id')->orderBy('locale');
    }

    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductOptionValue::class,
            'variant_option_values',
            'variant_id',
            'option_value_id',
        )->withPivot(['store_id', 'product_id']);
    }

    public function customFieldValues(): HasMany
    {
        return $this->hasMany(ProductCustomFieldValue::class, 'variant_id')->orderBy('definition_id')->orderBy('id');
    }

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'product_variant_media')
            ->using(ProductVariantMedia::class)
            ->withPivot(['id', 'store_id', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    protected function casts(): array
    {
        return [
            'price_amount_minor' => 'integer',
            'compare_at_price_amount_minor' => 'integer',
            'msrp_amount_minor' => 'integer',
            'cost_per_item_amount_minor' => 'integer',
            'inventory_qty' => 'integer',
            'weight_grams' => 'integer',
            'height' => 'decimal:4',
            'width' => 'decimal:4',
            'depth' => 'decimal:4',
            'requires_shipping' => 'boolean',
            'taxable' => 'boolean',
            'call_for_price' => 'boolean',
            'position' => 'integer',
        ];
    }
}
