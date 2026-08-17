<?php

declare(strict_types=1);

namespace Modules\Catalog\GraphQL\Mutations;

use Modules\Catalog\GraphQL\CatalogGraphqlContext;
use Modules\Catalog\Services\ProductManagementService;

final readonly class UpdateProduct
{
    public function __construct(
        private CatalogGraphqlContext $context,
        private ProductManagementService $products,
    ) {}

    /** @param array{id: string, input: array<string, mixed>} $arguments @return array<string, mixed> */
    public function __invoke(mixed $root, array $arguments): array
    {
        $product = $this->products->update(
            $this->context->user(),
            (string) $arguments['id'],
            $arguments['input'],
        );

        return [
            'product' => $product,
            'translationRequest' => $product->getRelation('translationRequest'),
        ];
    }
}
