<?php

declare(strict_types=1);

namespace Modules\Catalog\GraphQL\Mutations;

use Modules\Catalog\GraphQL\CatalogGraphqlContext;
use Modules\Catalog\Services\ProductTypeManagementService;

final readonly class UpdateProductType
{
    public function __construct(
        private CatalogGraphqlContext $context,
        private ProductTypeManagementService $productTypes,
    ) {}

    /** @param array{id: string, input: array<string, mixed>} $arguments @return array<string, mixed> */
    public function __invoke(mixed $root, array $arguments): array
    {
        $productType = $this->productTypes->update(
            $this->context->user(),
            (string) $arguments['id'],
            $arguments['input'],
        );

        return [
            'productType' => $productType,
            'translationRequest' => $productType->getRelation('translationRequest'),
        ];
    }
}
