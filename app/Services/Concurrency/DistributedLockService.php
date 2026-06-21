<?php

namespace App\Services\Concurrency;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

class DistributedLockService
{
    public function store(): Repository
    {
        return Cache::store(config('high_performance.distributed_lock.store', 'redis'));
    }

    public function run(string $key, callable $callback, int $ttlSeconds = 10, int $waitSeconds = 5): mixed
    {
        $lock = $this->store()->lock($key, $ttlSeconds);
        $result = $lock->block($waitSeconds, $callback);

        if ($result === false) {
            throw new LockTimeoutException("Could not acquire lock: {$key}");
        }

        return $result;
    }

    public function tryRun(string $key, callable $callback, int $ttlSeconds = 10): mixed
    {
        $lock = $this->store()->lock($key, $ttlSeconds);

        if (! $lock->get()) {
            return null;
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }
}
