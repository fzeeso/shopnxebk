<?php

declare(strict_types=1);

namespace Modules\Catalog\GraphQL;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class CatalogPage
{
    /** @return array{data: array<int, mixed>, paginatorInfo: array<string, int|null>} */
    public static function from(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => $paginator->items(),
            'paginatorInfo' => [
                'count' => $paginator->count(),
                'currentPage' => $paginator->currentPage(),
                'firstItem' => $paginator->firstItem(),
                'lastItem' => $paginator->lastItem(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
