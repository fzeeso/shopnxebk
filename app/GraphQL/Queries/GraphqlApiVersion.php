<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

final class GraphqlApiVersion
{
    public function __invoke(): string
    {
        return '1.0';
    }
}
