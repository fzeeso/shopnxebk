<?php

declare(strict_types=1);

namespace Modules\Customers\Infrastructure\Catalog;

use Illuminate\Database\Eloquent\Model;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\Product;
use Modules\Customers\Contracts\CatalogTargetResolver;
use Modules\Customers\Data\CatalogTargetReference;
use Modules\Customers\Enums\CustomerGroupDiscountTarget;
use Modules\Stores\Models\Store;

final class EloquentCatalogTargetResolver implements CatalogTargetResolver
{
    public function resolve(
        Store $store,
        CustomerGroupDiscountTarget $type,
        string $publicId,
    ): CatalogTargetReference {
        $model = match ($type) {
            CustomerGroupDiscountTarget::Category => Category::query()
                ->where('store_id', $store->getKey())
                ->where('public_id', $publicId)
                ->firstOrFail(),
            CustomerGroupDiscountTarget::Product => Product::query()
                ->where('store_id', $store->getKey())
                ->where('public_id', $publicId)
                ->firstOrFail(),
        };

        /** @var Model&object{public_id: string} $model */
        return new CatalogTargetReference(
            id: (int) $model->getKey(),
            publicId: (string) $model->getAttribute('public_id'),
            type: $type,
        );
    }
}
