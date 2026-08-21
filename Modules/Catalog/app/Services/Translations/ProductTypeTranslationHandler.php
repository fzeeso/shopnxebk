<?php

declare(strict_types=1);

namespace Modules\Catalog\Services\Translations;

final readonly class ProductTypeTranslationHandler extends CatalogEntityTranslationHandler
{
    public function contentType(): string
    {
        return 'product_type';
    }

    protected function entityTable(): string
    {
        return 'product_types';
    }

    protected function translationTable(): string
    {
        return 'product_type_translations';
    }

    protected function foreignKey(): string
    {
        return 'product_type_id';
    }

    protected function fields(): array
    {
        return ['name', 'description'];
    }

    protected function titleField(): string
    {
        return 'name';
    }

    protected function contentDescription(): string
    {
        return 'ecommerce product-type name and description';
    }
}
