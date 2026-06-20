<?php

namespace App\Services\Capacity;

use App\Exceptions\Errors;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ResourceCapacityService
{
    private function cache(): Repository
    {
        return Cache::store(config('high_performance.capacity.cache_store', 'database'));
    }

    public function acquire(string $key): string
    {
        $config = config('high_performance.capacity');
        $cacheKey = $config['checkout_key'].':'.$key;
        $lock = $this->cache()->lock($cacheKey.':mutex', 10);

        try {
            return $lock->block(5, function () use ($config, $cacheKey) {
                $max = $config['max_concurrent_checkouts'];
                $slots = $this->cache()->get($cacheKey.'_slots', []);

                if (count($slots) >= $max) {
                    Errors::CapacityExceeded();
                }

                $token = Str::uuid()->toString();
                $slots[$token] = now()->timestamp;
                $this->cache()->put($cacheKey.'_slots', $slots, $config['slot_ttl_seconds']);
                $this->cache()->put($cacheKey, count($slots), $config['slot_ttl_seconds']);

                return $token;
            }) ?? throw new LockTimeoutException('Capacity lock timeout');
        } catch (LockTimeoutException) {
            Errors::CapacityExceeded('System is busy. Please retry shortly.', 'capacity lock timeout');
        }
    }

    public function release(string $key, string $token): void
    {
        $config = config('high_performance.capacity');
        $cacheKey = $config['checkout_key'].':'.$key;
        $lock = $this->cache()->lock($cacheKey.':mutex', 10);

        $lock->block(5, function () use ($config, $cacheKey, $token) {
            $slots = $this->cache()->get($cacheKey.'_slots', []);
            unset($slots[$token]);
            $this->cache()->put($cacheKey.'_slots', $slots, $config['slot_ttl_seconds']);
            $this->cache()->put($cacheKey, count($slots), $config['slot_ttl_seconds']);
        });
    }

    public function currentCount(string $key): int
    {
        $cacheKey = config('high_performance.capacity.checkout_key').':'.$key;

        return count($this->cache()->get($cacheKey.'_slots', []));
    }
}
