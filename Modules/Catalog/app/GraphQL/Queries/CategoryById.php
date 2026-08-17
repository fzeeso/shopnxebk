<?php

declare(strict_types=1);

namespace Modules\Catalog\GraphQL\Queries;

use Modules\Catalog\GraphQL\CatalogGraphqlContext;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Services\CategoryManagementService;

final readonly class CategoryById
{
    public function __construct(
        private CatalogGraphqlContext $context,
        private CategoryManagementService $categories,
    ) {}

    /** @param array{id: string} $arguments */
    public function __invoke(mixed $root, array $arguments): Category
    {
        return $this->categories->show($this->context->user(), (string) $arguments['id']);
    }
}
