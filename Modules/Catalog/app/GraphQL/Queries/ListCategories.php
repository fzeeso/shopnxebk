<?php

declare(strict_types=1);

namespace Modules\Catalog\GraphQL\Queries;

use Modules\Catalog\GraphQL\CatalogGraphqlContext;
use Modules\Catalog\GraphQL\CatalogPage;
use Modules\Catalog\Services\CategoryManagementService;

final readonly class ListCategories
{
    public function __construct(
        private CatalogGraphqlContext $context,
        private CategoryManagementService $categories,
    ) {}

    /** @param array<string, mixed> $arguments @return array<string, mixed> */
    public function __invoke(mixed $root, array $arguments): array
    {
        return CatalogPage::from($this->categories->list($this->context->user(), $arguments));
    }
}
