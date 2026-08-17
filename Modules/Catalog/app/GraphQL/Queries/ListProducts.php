<?php

declare(strict_types=1);

namespace Modules\Catalog\GraphQL\Queries;

use Modules\Catalog\GraphQL\CatalogGraphqlContext;
use Modules\Catalog\GraphQL\CatalogPage;
use Modules\Catalog\Services\ProductManagementService;

final readonly class ListProducts
{
    public function __construct(
        private CatalogGraphqlContext $context,
        private ProductManagementService $products,
    ) {}

    /** @param array<string, mixed> $arguments @return array<string, mixed> */
    public function __invoke(mixed $root, array $arguments): array
    {
        return CatalogPage::from($this->products->list($this->context->user(), $arguments));
    }
}
