<?php

declare(strict_types=1);

namespace Modules\Customers\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\Product;
use Modules\Customers\Enums\CustomerGroupDiscountAppliesTo;
use Modules\Customers\Enums\CustomerGroupDiscountTarget;
use Modules\Stores\Models\Concerns\StoreScoped;
use Modules\Stores\Models\Store;

#[Fillable([
    'store_id',
    'customer_group_id',
    'target_type',
    'category_id',
    'product_id',
    'discount_percentage',
    'applies_to',
    'discount_method',
])]
final class CustomerGroupDiscount extends Model
{
    use HasPublicId, StoreScoped;

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected function casts(): array
    {
        return [
            'target_type' => CustomerGroupDiscountTarget::class,
            'discount_percentage' => 'decimal:4',
            'applies_to' => CustomerGroupDiscountAppliesTo::class,
        ];
    }
}
