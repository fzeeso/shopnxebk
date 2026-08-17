<?php

declare(strict_types=1);

namespace Modules\Catalog\GraphQL\Mutations;

use Modules\Catalog\GraphQL\CatalogGraphqlContext;
use Modules\Catalog\Services\ProductManagementService;

final readonly class DeleteProduct
{
    public function __construct(
        private CatalogGraphqlContext $context,
        private ProductManagementService $products,
    ) {}

    /** @param array{id: string} $arguments @return array{id: string, deleted: true} */
    public function __invoke(mixed $root, array $arguments): array
    {
        $id = (string) $arguments['id'];
        $this->products->delete($this->context->user(), $id);

        return ['id' => $id, 'deleted' => true];
    }
}
