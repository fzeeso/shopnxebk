<?php

declare(strict_types=1);

return [
    /*
    | Reversible production-readiness switches. Features that can change
    | request behaviour default to disabled until they are exercised in an
    | AWS staging environment.
    */
    'store_lookup_cache' => [
        'enabled' => (bool) env('SCALABILITY_STORE_LOOKUP_CACHE_ENABLED', false),
        'ttl_seconds' => (int) env('SCALABILITY_STORE_LOOKUP_CACHE_TTL_SECONDS', 30),
        'prefix' => env('SCALABILITY_STORE_LOOKUP_CACHE_PREFIX', 'scalability:store-lookup'),
    ],

    'product_detail_reference_cache' => [
        'enabled' => (bool) env('SCALABILITY_PRODUCT_DETAIL_REFERENCE_CACHE_ENABLED', false),
        'ttl_seconds' => (int) env('SCALABILITY_PRODUCT_DETAIL_REFERENCE_CACHE_TTL_SECONDS', 300),
        'prefix' => env('SCALABILITY_PRODUCT_DETAIL_REFERENCE_CACHE_PREFIX', 'scalability:product-detail-references'),
    ],

    'rate_limits' => [
        'store_product_api' => [
            'enabled' => (bool) env('SCALABILITY_STORE_PRODUCT_RATE_LIMIT_ENABLED', false),
            'reads_per_minute' => (int) env('SCALABILITY_STORE_PRODUCT_READS_PER_MINUTE', 600),
            'writes_per_minute' => (int) env('SCALABILITY_STORE_PRODUCT_WRITES_PER_MINUTE', 120),
        ],
    ],

    'request_performance' => [
        'enabled' => (bool) env('SCALABILITY_REQUEST_PERFORMANCE_ENABLED', false),
        'slow_request_ms' => (int) env('SCALABILITY_SLOW_REQUEST_MS', 1000),
        'sample_rate' => (float) env('SCALABILITY_REQUEST_SAMPLE_RATE', 0.05),
        'server_timing_header' => (bool) env('SCALABILITY_SERVER_TIMING_HEADER_ENABLED', false),
    ],

    'database' => [
        'read_write_split_enabled' => (bool) env('DB_READ_WRITE_SPLIT_ENABLED', false),
        'sticky_reads' => (bool) env('DB_STICKY_READS', true),
    ],
];
