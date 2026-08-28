<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use LogicException;
use Modules\Catalog\Contracts\ProductDetailSectionProvider;

final class ProductDetailSectionRegistry
{
    public const BUILT_IN_SECTIONS = [
        'images',
        'media',
        'custom_fields',
        'options',
        'variants',
        'shared_options',
        'modifier_groups',
        'modifiers',
    ];

    /** @var list<ProductDetailSectionProvider> */
    private array $providers;

    /** @param iterable<ProductDetailSectionProvider> $providers */
    public function __construct(iterable $providers)
    {
        $registered = [];
        foreach ($providers as $provider) {
            $key = $provider->key();
            if (! preg_match('/^[a-z][a-z0-9_]*$/', $key)) {
                throw new LogicException("Product Detail section key [{$key}] must use snake_case letters, numbers, and underscores.");
            }
            if ($key === 'product' || in_array($key, self::BUILT_IN_SECTIONS, true)) {
                throw new LogicException("Product Detail section key [{$key}] is reserved by Catalog.");
            }
            if (isset($registered[$key])) {
                throw new LogicException("Product Detail section key [{$key}] is registered more than once.");
            }

            $registered[$key] = $provider;
        }

        uasort($registered, static fn (ProductDetailSectionProvider $left, ProductDetailSectionProvider $right): int => [$left->priority(), $left->key()] <=> [$right->priority(), $right->key()]);
        $this->providers = array_values($registered);
    }

    /** @return list<ProductDetailSectionProvider> */
    public function all(): array
    {
        return $this->providers;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_map(
            static fn (ProductDetailSectionProvider $provider): string => $provider->key(),
            $this->providers,
        );
    }

    /** @return array<string, list<mixed>> */
    public function validationRules(): array
    {
        $rules = [];
        foreach ($this->providers as $provider) {
            $root = 'sections.'.$provider->key();
            $providerRules = $provider->rules();
            $rules[$root] = $providerRules[''] ?? ['sometimes', 'array'];
            unset($providerRules['']);

            foreach ($providerRules as $field => $fieldRules) {
                $rules[$root.'.'.$field] = $fieldRules;
            }
        }

        return $rules;
    }
}
