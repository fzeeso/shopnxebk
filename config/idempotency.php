<?php

declare(strict_types=1);

return [
    /*
    | The global switch is deliberately off until the additive migration has
    | been reviewed and explicitly executed in an authorized environment.
    */
    'enabled' => (bool) env('IDEMPOTENCY_ENABLED', false),

    'contract_version' => 1,
    'fingerprint_version' => 1,
    'hmac_key' => env('IDEMPOTENCY_HMAC_KEY'),
    'maximum_response_bytes' => (int) env('IDEMPOTENCY_MAXIMUM_RESPONSE_BYTES', 1048576),

    'default_policy' => [
        'mode' => 'supported',
        'ttl_seconds' => (int) env('IDEMPOTENCY_DEFAULT_TTL_SECONDS', 86400),
    ],

    'tier_a_policy' => [
        'mode' => env('IDEMPOTENCY_TIER_A_MODE', 'supported'),
        'ttl_seconds' => (int) env('IDEMPOTENCY_TIER_A_TTL_SECONDS', 259200),
    ],

    'operations' => [
        'api.v1.stores.store' => 'tier_a_policy',
        'api.v1.platform.stores.store' => 'tier_a_policy',
        'api.v1.platform.merchants.store' => 'tier_a_policy',
        'api.v1.store.users.store' => 'tier_a_policy',
    ],

    'pruning' => [
        'batch_size' => (int) env('IDEMPOTENCY_PRUNE_BATCH_SIZE', 1000),
        'maximum_batches' => (int) env('IDEMPOTENCY_PRUNE_MAXIMUM_BATCHES', 10),
    ],
];
