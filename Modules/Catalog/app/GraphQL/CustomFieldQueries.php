<?php

declare(strict_types=1);

namespace Modules\Catalog\GraphQL;

use Modules\Catalog\Models\CustomFieldDefinition;
use Modules\Catalog\Models\ProductCustomFieldValue;
use Modules\Catalog\Services\CustomFieldManagementService;

final readonly class CustomFieldQueries
{
    public function __construct(
        private CatalogGraphqlContext $context,
        private CustomFieldManagementService $customFields,
    ) {}

    /** @param array<string, mixed> $arguments @return array<string, mixed> */
    public function definitions(mixed $root, array $arguments): array
    {
        return CatalogPage::from($this->customFields->listDefinitions($this->context->user(), $arguments));
    }

    /** @param array{id: string} $arguments */
    public function definition(mixed $root, array $arguments): CustomFieldDefinition
    {
        return $this->customFields->showDefinition($this->context->user(), (string) $arguments['id']);
    }

    /** @param array{productId: string, variantId?: string|null} $arguments @return list<ProductCustomFieldValue> */
    public function values(mixed $root, array $arguments): array
    {
        return $this->customFields->listValues(
            $this->context->user(),
            (string) $arguments['productId'],
            isset($arguments['variantId']) ? (string) $arguments['variantId'] : null,
        );
    }

    /** @param array{productId: string, definitionId: string, variantId?: string|null} $arguments */
    public function value(mixed $root, array $arguments): ProductCustomFieldValue
    {
        return $this->customFields->showValue(
            $this->context->user(),
            (string) $arguments['productId'],
            (string) $arguments['definitionId'],
            isset($arguments['variantId']) ? (string) $arguments['variantId'] : null,
        );
    }
}
