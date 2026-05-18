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
        'queue' => env('BATCH_QUEUE', 'batches'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue distribution (Requirement 5)
    |--------------------------------------------------------------------------
    */
    'queues' => [
        'default' => env('QUEUE_CONNECTION', 'database'),
        'notifications' => 'notifications',
        'invoices' => 'invoices',
        'batches' => 'batches',
    ],

];
