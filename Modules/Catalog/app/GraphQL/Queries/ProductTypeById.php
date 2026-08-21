<?php

declare(strict_types=1);

namespace Modules\Catalog\GraphQL\Queries;

use Modules\Catalog\GraphQL\CatalogGraphqlContext;
use Modules\Catalog\Models\ProductType;
use Modules\Catalog\Services\ProductTypeManagementService;

final readonly class ProductTypeById
{
    public function __construct(
        private CatalogGraphqlContext $context,
        private ProductTypeManagementService $productTypes,
    ) {}

    /** @param array{id: string} $arguments */
    public function __invoke(mixed $root, array $arguments): ProductType
    {
        return $this->productTypes->show($this->context->user(), (string) $arguments['id']);
    }
}
