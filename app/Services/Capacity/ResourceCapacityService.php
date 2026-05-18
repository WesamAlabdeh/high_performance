<?php

namespace App\Services\Capacity;

use App\Exceptions\Errors;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Requirement 2: Resource management & capacity control.
 * Limits concurrent checkout operations using atomic cache counters.
 */
class ResourceCapacityService
{
    public function acquire(string $key): string
    {
        $config = config('high_performance.capacity');
        $max = $config['max_concurrent_checkouts'];
        $cacheKey = $config['checkout_key'].':'.$key;
        $current = (int) Cache::get($cacheKey, 0);

        if ($current >= $max) {
            Errors::CapacityExceeded();
        }

        $token = Str::uuid()->toString();
        $slots = Cache::get($cacheKey.'_slots', []);
        $slots[$token] = now()->timestamp;
        Cache::put($cacheKey.'_slots', $slots, $config['slot_ttl_seconds']);
        Cache::put($cacheKey, count($slots), $config['slot_ttl_seconds']);

        return $token;
    }

    public function release(string $key, string $token): void
    {
        $config = config('high_performance.capacity');
        $cacheKey = $config['checkout_key'].':'.$key;
        $slots = Cache::get($cacheKey.'_slots', []);

        unset($slots[$token]);
        Cache::put($cacheKey.'_slots', $slots, $config['slot_ttl_seconds']);
        Cache::put($cacheKey, count($slots), $config['slot_ttl_seconds']);
    }
}
