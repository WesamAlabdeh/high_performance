<?php

namespace App\Services\LoadBalancing;

use App\Exceptions\Errors;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Requirement 5: Load distribution — circuit breaker pattern.
 */
class CircuitBreakerService
{
    private function cache(): Repository
    {
        return Cache::store(config('high_performance.circuit_breaker.cache_store', 'database'));
    }

    public function guard(string $service): void
    {
        $state = $this->cache()->get($this->stateKey($service));

        if ($state === 'open') {
            Errors::CircuitOpen();
        }
    }

    public function recordSuccess(string $service): void
    {
        $this->cache()->forget($this->failureKey($service));
        $this->cache()->put($this->stateKey($service), 'closed', config('high_performance.circuit_breaker.recovery_seconds'));
    }

    public function recordFailure(string $service): void
    {
        $config = config('high_performance.circuit_breaker');
        $failures = (int) $this->cache()->get($this->failureKey($service), 0) + 1;
        $this->cache()->put($this->failureKey($service), $failures, $config['window_seconds']);

        if ($failures >= $config['failure_threshold']) {
            $this->cache()->put($this->stateKey($service), 'open', $config['recovery_seconds']);
        }
    }

    private function stateKey(string $service): string
    {
        return "circuit:{$service}:state";
    }

    private function failureKey(string $service): string
    {
        return "circuit:{$service}:failures";
    }
}
