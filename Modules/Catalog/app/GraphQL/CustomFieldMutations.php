<?php

declare(strict_types=1);

namespace Modules\Catalog\GraphQL;

use Modules\Catalog\Services\CustomFieldManagementService;

final readonly class CustomFieldMutations
{
    public function __construct(
        private CatalogGraphqlContext $context,
        private CustomFieldManagementService $customFields,
    ) {}

    /** @param array{input: array<string, mixed>} $arguments @return array<string, mixed> */
    public function createDefinition(mixed $root, array $arguments): array
    {
        return ['customField' => $this->customFields->createDefinition($this->context->user(), $arguments['input'])];
    }

    /** @param array{id: string, input: array<string, mixed>} $arguments @return array<string, mixed> */
    public function updateDefinition(mixed $root, array $arguments): array
    {
        return ['customField' => $this->customFields->updateDefinition(
            $this->context->user(),
            (string) $arguments['id'],
            $arguments['input'],
        )];
    }

    /** @param array{id: string} $arguments @return array{id: string, deleted: true} */
    public function deleteDefinition(mixed $root, array $arguments): array
    {
        $id = (string) $arguments['id'];
        $this->customFields->deleteDefinition($this->context->user(), $id);

        return ['id' => $id, 'deleted' => true];
    }

    /** @param array{definitionId: string, input: array<string, mixed>} $arguments @return array<string, mixed> */
    public function createOption(mixed $root, array $arguments): array
    {
        return ['option' => $this->customFields->createOption(
            $this->context->user(),
            (string) $arguments['definitionId'],
            $arguments['input'],
        )];
    }

    /** @param array{definitionId: string, id: string, input: array<string, mixed>} $arguments @return array<string, mixed> */
    public function updateOption(mixed $root, array $arguments): array
    {
        return ['option' => $this->customFields->updateOption(
            $this->context->user(),
            (string) $arguments['definitionId'],
            (string) $arguments['id'],
            $arguments['input'],
        )];
    }

    /** @param array{definitionId: string, id: string} $arguments @return array{id: string, deleted: true} */
    public function deleteOption(mixed $root, array $arguments): array
    {
        $id = (string) $arguments['id'];
        $this->customFields->deleteOption(
            $this->context->user(),
            (string) $arguments['definitionId'],
            $id,
        );

        return ['id' => $id, 'deleted' => true];
    }

    /** @param array{productId: string, definitionId: string, variantId?: string|null, input: array<string, mixed>} $arguments @return array<string, mixed> */
    public function setValue(mixed $root, array $arguments): array
    {
        return ['value' => $this->customFields->setValue(
            $this->context->user(),
            (string) $arguments['productId'],
            (string) $arguments['definitionId'],
            $arguments['input'],
            isset($arguments['variantId']) ? (string) $arguments['variantId'] : null,
        )];
    }

    /** @param array{productId: string, definitionId: string, variantId?: string|null} $arguments @return array{id: string, deleted: true} */
    public function deleteValue(mixed $root, array $arguments): array
    {
        $value = $this->customFields->showValue(
            $this->context->user(),
            (string) $arguments['productId'],
            (string) $arguments['definitionId'],
            isset($arguments['variantId']) ? (string) $arguments['variantId'] : null,
        );
        $id = (string) $value->public_id;
        $this->customFields->deleteValue(
            $this->context->user(),
            (string) $arguments['productId'],
            (string) $arguments['definitionId'],
            isset($arguments['variantId']) ? (string) $arguments['variantId'] : null,
        );

        return ['id' => $id, 'deleted' => true];
    }
}
