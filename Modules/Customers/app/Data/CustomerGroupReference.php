<?php

declare(strict_types=1);

namespace Modules\Customers\Data;

final readonly class CustomerGroupReference
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $code,
    ) {}
}
