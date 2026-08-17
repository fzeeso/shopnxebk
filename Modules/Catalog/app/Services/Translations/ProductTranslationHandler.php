<?php

declare(strict_types=1);

namespace Modules\Catalog\Services\Translations;

final readonly class ProductTranslationHandler extends CatalogEntityTranslationHandler
{
    public function contentType(): string
    {
        return 'product';
    }

    protected function entityTable(): string
    {
        return 'products';
    }

    protected function translationTable(): string
    {
        return 'product_translations';
    }

    protected function foreignKey(): string
    {
        return 'product_id';
    }

    protected function fields(): array
    {
        return ['title', 'description', 'seo_title', 'seo_description'];
    }

    protected function titleField(): string
    {
        return 'title';
    }

    protected function contentDescription(): string
    {
        return 'ecommerce product description and SEO metadata';
    }
}
