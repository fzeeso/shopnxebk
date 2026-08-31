<?php

declare(strict_types=1);

namespace App\Support\Idempotency;

use Illuminate\Http\JsonResponse;
use RuntimeException;

final class UncacheableIdempotencyResponse extends RuntimeException
{
    public function __construct(public readonly JsonResponse $response)
    {
        parent::__construct('The response must not consume the idempotency key.');
    }
}
