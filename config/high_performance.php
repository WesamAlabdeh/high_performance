<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Resource capacity (Requirement 2)
    |--------------------------------------------------------------------------
    */
    'capacity' => [
        'max_concurrent_checkouts' => (int) env('MAX_CONCURRENT_CHECKOUTS', 30),
        'slot_ttl_seconds' => (int) env('CAPACITY_SLOT_TTL', 120),
        'checkout_key' => 'capacity:checkout',
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit breaker (Requirement 5)
    |--------------------------------------------------------------------------
    */
    'circuit_breaker' => [
        'failure_threshold' => (int) env('CIRCUIT_FAILURE_THRESHOLD', 5),
        'recovery_seconds' => (int) env('CIRCUIT_RECOVERY_SECONDS', 30),
        'window_seconds' => (int) env('CIRCUIT_WINDOW_SECONDS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Batch processing (Requirement 4)
    |--------------------------------------------------------------------------
    */
    'batch' => [
        'chunk_size' => (int) env('BATCH_CHUNK_SIZE', 100),
    ],

    'payment' => [
        'simulation_delay_ms' => (int) env('PAYMENT_SIMULATION_DELAY_MS', 1500),
        'initial_balance' => (float) env('USER_INITIAL_BALANCE', 10000),
    ],

    'cache' => [
        'product_ttl' => (int) env('PRODUCT_CACHE_TTL', 300),
        'product_store' => env('PRODUCT_CACHE_STORE', 'database'),
    ],

    'stress_test' => [
        'users' => (int) env('STRESS_TEST_USERS', 100),
        'base_url' => env('STRESS_TEST_BASE_URL', 'http://127.0.0.1:8000'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue job profiles — tries / timeout / backoff per workload
    |--------------------------------------------------------------------------
    */
    'job_profiles' => [
        'invoice' => [
            'tries' => (int) env('JOB_INVOICE_TRIES', 2),
            'timeout' => (int) env('JOB_INVOICE_TIMEOUT', 120),
            'backoff' => (int) env('JOB_INVOICE_BACKOFF', 10),
            'fail_on_timeout' => (bool) env('JOB_INVOICE_FAIL_ON_TIMEOUT', true),
        ],
        'notification' => [
            'tries' => (int) env('JOB_NOTIFICATION_TRIES', 2),
            'timeout' => (int) env('JOB_NOTIFICATION_TIMEOUT', 60),
            'backoff' => (int) env('JOB_NOTIFICATION_BACKOFF', 10),
            'fail_on_timeout' => (bool) env('JOB_NOTIFICATION_FAIL_ON_TIMEOUT', true),
        ],
        'batch' => [
            'tries' => (int) env('JOB_BATCH_TRIES', 2),
            'timeout' => (int) env('JOB_BATCH_TIMEOUT', 900),
            'backoff' => (int) env('JOB_BATCH_BACKOFF', 10),
            'fail_on_timeout' => (bool) env('JOB_BATCH_FAIL_ON_TIMEOUT', true),
        ],
    ],

];
