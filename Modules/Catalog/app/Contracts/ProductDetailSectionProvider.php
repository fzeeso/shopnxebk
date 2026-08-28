<?php

declare(strict_types=1);

namespace Modules\Catalog\Contracts;

use Modules\Authentication\Models\User;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Support\ProductDetailReferenceMap;
use Modules\Catalog\Support\ProductDetailSectionPayload;
use Modules\Stores\Models\Store;

interface ProductDetailSectionProvider
{
    public function key(): string;

    public function priority(): int;

    /**
     * Validation rules relative to the section root.
     *
     * Use an empty key to replace the default `sometimes|array` root rule.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array;

    public function bootstrap(User $user, Store $store, int $limit): ProductDetailSectionPayload;

    public function read(User $user, Store $store, Product $product, int $limit): ProductDetailSectionPayload;

    /** @param array<string, mixed> $command */
    public function save(
        User $user,
        Store $store,
        Product $product,
        array $command,
        ProductDetailReferenceMap $references,
    ): void;
}
