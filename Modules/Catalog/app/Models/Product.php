<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use App\Models\Brand;
use App\Models\Concerns\HasPublicId;
use App\Models\Media;
use App\Models\ProductMedia;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Stores\Models\Concerns\StoreScoped;
use Modules\Stores\Models\Store;

#[Fillable([
    'store_id',
    'brand_id',
    'platform_taxonomy_node_id',
    'vendor',
    'product_type_id',
    'fulfillment_type',
    'track_inventory',
    'status',
    'has_variants',
    'published_at',
    'sku',
    'downloadfile',
    'availability',
    'price',
    'costprice',
    'retailprice',
    'msrpprice',
    'saleprice',
    'calculatedprice',
    'sortorder',
    'is_featured',
    'currentinv',
    'lowinv',
    'warranty',
    'weight',
    'width',
    'height',
    'proddepth',
    'fixedshippingcost',
    'freeshipping',
    'ratingtotal',
    'numratings',
    'numsold',
    'numviews',
    'allowpurchases',
    'hideprice',
    'is_login_for_price',
    'is_global_search',
    'condition',
    'showcondition',
    'pre_order',
    'releasedate',
    'releasedateremove',
    'minqty',
    'maxqty',
    'tax_class_id',
    'show_related_product',
    'prodpoints',
    'reviews_on',
    'upc',
    'hs_code',
    'gtin',
    'mpn',
    'bpn',
])]
final class Product extends Model
{
    use HasPublicId, StoreScoped;

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function platformTaxonomyNode(): BelongsTo
    {
        return $this->belongsTo(PlatformTaxonomyNode::class, 'platform_taxonomy_node_id');
    }

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(ProductTranslation::class)->orderBy('locale');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'product_categories')
            ->withPivot(['store_id', 'sort_order', 'is_primary'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position')->orderBy('id');
    }

    public function modifierGroups(): HasMany
    {
        return $this->hasMany(ProductModifierGroup::class)->orderBy('sort_order')->orderBy('id');
    }

    public function modifierAssignments(): HasMany
    {
        return $this->hasMany(ProductModifierAssignment::class)->orderBy('sort_order')->orderBy('id');
    }

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'product_media')
            ->using(ProductMedia::class)
            ->withPivot(['id', 'store_id', 'sort_order', 'is_primary'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function primaryMedia(): BelongsToMany
    {
        return $this->media()->wherePivot('is_primary', true);
    }

    public function statusValue(): string
    {
        return (string) $this->status;
    }

    public function fulfillmentTypeValue(): string
    {
        return (string) $this->fulfillment_type;
    }

    public function brandPublicId(): ?string
    {
        return $this->brand?->public_id;
    }

    public function platformTaxonomyNodePublicId(): ?string
    {
        return $this->platformTaxonomyNode?->public_id;
    }

    public function productTypePublicId(): ?string
    {
        return $this->productType?->public_id;
    }

    public function primaryCategoryPublicId(): ?string
    {
        return $this->categories->first(
            fn (Category $category): bool => (bool) $category->pivot?->is_primary,
        )?->public_id;
    }

    protected function casts(): array
    {
        return [
            'platform_taxonomy_node_id' => 'integer',
            'product_type_id' => 'integer',
            'track_inventory' => 'boolean',
            'has_variants' => 'boolean',
            'published_at' => 'immutable_datetime',
            'price' => 'decimal:4',
            'costprice' => 'decimal:4',
            'retailprice' => 'decimal:4',
            'msrpprice' => 'decimal:4',
            'saleprice' => 'decimal:4',
            'calculatedprice' => 'decimal:4',
            'sortorder' => 'integer',
            'is_featured' => 'integer',
            'currentinv' => 'integer',
            'lowinv' => 'integer',
            'weight' => 'decimal:4',
            'width' => 'decimal:4',
            'height' => 'decimal:4',
            'proddepth' => 'decimal:4',
            'fixedshippingcost' => 'decimal:4',
            'freeshipping' => 'integer',
            'ratingtotal' => 'integer',
            'numratings' => 'integer',
            'numsold' => 'integer',
            'numviews' => 'integer',
            'allowpurchases' => 'integer',
            'hideprice' => 'integer',
            'is_login_for_price' => 'integer',
            'is_global_search' => 'integer',
            'showcondition' => 'integer',
            'pre_order' => 'integer',
            'releasedate' => 'immutable_datetime',
            'releasedateremove' => 'integer',
            'minqty' => 'integer',
            'maxqty' => 'integer',
            'tax_class_id' => 'integer',
            'show_related_product' => 'integer',
            'prodpoints' => 'integer',
            'reviews_on' => 'integer',
        ];
    }
}
