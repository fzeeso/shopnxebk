<?php

declare(strict_types=1);

return [
    'queue_connection' => env('TRANSLATION_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'redis')),
    'queue' => env('TRANSLATION_QUEUE', 'translations'),
    'max_attempts' => (int) env('TRANSLATION_MAX_ATTEMPTS', 3),
    'recovery_batch_size' => (int) env('TRANSLATION_RECOVERY_BATCH_SIZE', 100),
    'recovery_after_minutes' => (int) env('TRANSLATION_RECOVERY_AFTER_MINUTES', 10),
];
