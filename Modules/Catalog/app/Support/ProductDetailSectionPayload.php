<?php

declare(strict_types=1);

namespace Modules\Catalog\Support;

use InvalidArgumentException;

final readonly class ProductDetailSectionPayload
{
    public function __construct(
        public mixed $data,
        public int $total,
        public int $returned,
    ) {
        if ($this->total < 0 || $this->returned < 0 || $this->returned > $this->total) {
            throw new InvalidArgumentException('Product Detail section counts must be non-negative and returned cannot exceed total.');
        }
    }

    public static function empty(): self
    {
        return new self([], 0, 0);
    }

    /** @return array{total: int, returned: int, limit: int, truncated: bool} */
    public function meta(int $limit): array
    {
        return [
            'total' => $this->total,
            'returned' => $this->returned,
            'limit' => $limit,
            'truncated' => $this->total > $this->returned,
        ];
    }
}
