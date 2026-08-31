<?php

declare(strict_types=1);

namespace App\Support\Idempotency;

use RuntimeException;

final class IdempotencyResponseRejected extends RuntimeException {}
