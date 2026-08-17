<?php

declare(strict_types=1);

namespace Modules\Catalog\GraphQL\Queries;

use Modules\Catalog\GraphQL\CatalogGraphqlContext;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Services\ProductManagementService;

final readonly class ProductById
{
    public function __construct(
        private CatalogGraphqlContext $context,
        private ProductManagementService $products,
    ) {}

    /** @param array{id: string} $arguments */
    public function __invoke(mixed $root, array $arguments): Product
    {
        return $this->products->show($this->context->user(), (string) $arguments['id']);
    }
}
