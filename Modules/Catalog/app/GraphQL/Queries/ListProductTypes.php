<?php

declare(strict_types=1);

namespace Modules\Catalog\GraphQL\Queries;

use Modules\Catalog\GraphQL\CatalogGraphqlContext;
use Modules\Catalog\GraphQL\CatalogPage;
use Modules\Catalog\Services\ProductTypeManagementService;

final readonly class ListProductTypes
{
    public function __construct(
        private CatalogGraphqlContext $context,
        private ProductTypeManagementService $productTypes,
    ) {}

    /** @param array<string, mixed> $arguments @return array<string, mixed> */
    public function __invoke(mixed $root, array $arguments): array
    {
        return CatalogPage::from($this->productTypes->list($this->context->user(), $arguments));
    }
}
