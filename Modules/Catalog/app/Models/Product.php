<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use App\Models\Brand;
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
    'brand_id',
    'vendor',
    'product_type',
    'fulfillment_type',
    'track_inventory',
    'status',
    'has_variants',
    'published_at',
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

    public function primaryCategoryPublicId(): ?string
    {
        return $this->categories->first(
            fn (Category $category): bool => (bool) $category->pivot?->is_primary,
        )?->public_id;
    }

    protected function casts(): array
    {
        return [
            'track_inventory' => 'boolean',
            'has_variants' => 'boolean',
            'published_at' => 'immutable_datetime',
        ];
    }
}
