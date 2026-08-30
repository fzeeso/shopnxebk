<?php

declare(strict_types=1);

namespace Modules\Customers\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Catalog\Models\Category;
use Modules\Customers\Enums\CustomerGroupCategoryAccess;
use Modules\Stores\Models\Concerns\StoreScoped;
use Modules\Stores\Models\Store;

#[Fillable([
    'store_id',
    'code',
    'default_discount_percentage',
    'discount_method',
    'is_default',
    'category_access_type',
])]
final class CustomerGroup extends Model
{
    use HasPublicId, StoreScoped;

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(CustomerGroupTranslation::class)->orderBy('language_id');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'customer_group_categories')
            ->withPivot('store_id')
            ->withTimestamps()
            ->orderBy('categories.id');
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(CustomerGroupDiscount::class)
            ->orderBy('target_type')
            ->orderBy('id');
    }

    protected function casts(): array
    {
        return [
            'default_discount_percentage' => 'decimal:4',
            'is_default' => 'boolean',
            'category_access_type' => CustomerGroupCategoryAccess::class,
        ];
    }
}
