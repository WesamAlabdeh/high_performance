<?php

namespace App\Services\LoadBalancing;

use App\Exceptions\Errors;
use Illuminate\Support\Facades\Cache;

/**
 * Requirement 5: Load distribution — circuit breaker pattern.
 */
class CircuitBreakerService
{
    public function guard(string $service): void
    {
        $state = Cache::get($this->stateKey($service));

        if ($state === 'open') {
            Errors::CircuitOpen();
        }
    }

    public function recordSuccess(string $service): void
    {
        Cache::forget($this->failureKey($service));
        Cache::put($this->stateKey($service), 'closed', config('high_performance.circuit_breaker.recovery_seconds'));
    }

    public function recordFailure(string $service): void
    {
        $config = config('high_performance.circuit_breaker');
        $failures = (int) Cache::get($this->failureKey($service), 0) + 1;
        Cache::put($this->failureKey($service), $failures, $config['window_seconds']);

        if ($failures >= $config['failure_threshold']) {
            Cache::put($this->stateKey($service), 'open', $config['recovery_seconds']);
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
