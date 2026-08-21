<?php

declare(strict_types=1);

namespace Modules\Catalog\GraphQL\Mutations;

use Modules\Catalog\GraphQL\CatalogGraphqlContext;
use Modules\Catalog\Services\ProductTypeManagementService;

final readonly class CreateProductType
{
    public function __construct(
        private CatalogGraphqlContext $context,
        private ProductTypeManagementService $productTypes,
    ) {}

    /** @param array{input: array<string, mixed>} $arguments @return array<string, mixed> */
    public function __invoke(mixed $root, array $arguments): array
    {
        $productType = $this->productTypes->create($this->context->user(), $arguments['input']);

        return [
            'productType' => $productType,
            'translationRequest' => $productType->getRelation('translationRequest'),
        ];
    }
}
