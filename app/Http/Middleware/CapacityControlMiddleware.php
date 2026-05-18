<?php

namespace App\Http\Middleware;

use App\Services\Capacity\ResourceCapacityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CapacityControlMiddleware
{
    public function __construct(private readonly ResourceCapacityService $capacity) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->capacity->acquire('checkout');

        try {
            $response = $next($request);
        } finally {
            $this->capacity->release('checkout', $token);
        }

        return $response;
    }
}
