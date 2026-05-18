<?php

namespace App\Http\Middleware;

use App\Services\LoadBalancing\CircuitBreakerService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CircuitBreakerMiddleware
{
    public function __construct(private readonly CircuitBreakerService $circuitBreaker) {}

    public function handle(Request $request, Closure $next, string $service = 'orders'): Response
    {
        $this->circuitBreaker->guard($service);

        try {
            $response = $next($request);

            if ($response->getStatusCode() >= 500) {
                $this->circuitBreaker->recordFailure($service);
            } else {
                $this->circuitBreaker->recordSuccess($service);
            }

            return $response;
        } catch (\Throwable $e) {
            $this->circuitBreaker->recordFailure($service);
            throw $e;
        }
    }
}
