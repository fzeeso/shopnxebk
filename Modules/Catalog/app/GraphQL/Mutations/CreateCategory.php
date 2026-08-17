<?php

declare(strict_types=1);

namespace Modules\Catalog\GraphQL\Mutations;

use Modules\Catalog\GraphQL\CatalogGraphqlContext;
use Modules\Catalog\Services\CategoryManagementService;

final readonly class CreateCategory
{
    public function __construct(
        private CatalogGraphqlContext $context,
        private CategoryManagementService $categories,
    ) {}

    /** @param array{input: array<string, mixed>} $arguments @return array<string, mixed> */
    public function __invoke(mixed $root, array $arguments): array
    {
        $category = $this->categories->create($this->context->user(), $arguments['input']);

        return [
            'category' => $category,
            'translationRequest' => $category->getRelation('translationRequest'),
        ];
    }
}
