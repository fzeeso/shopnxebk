<?php

declare(strict_types=1);

namespace Modules\Catalog\GraphQL\Mutations;

use Modules\Catalog\GraphQL\CatalogGraphqlContext;
use Modules\Catalog\Services\CategoryManagementService;

final readonly class DeleteCategory
{
    public function __construct(
        private CatalogGraphqlContext $context,
        private CategoryManagementService $categories,
    ) {}

    /** @param array{id: string} $arguments @return array{id: string, deleted: true} */
    public function __invoke(mixed $root, array $arguments): array
    {
        $id = (string) $arguments['id'];
        $this->categories->delete($this->context->user(), $id);

        return ['id' => $id, 'deleted' => true];
    }
}
