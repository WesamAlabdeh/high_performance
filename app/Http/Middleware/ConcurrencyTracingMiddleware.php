<?php

namespace App\Http\Middleware;

use App\Aspects\ConcurrencyAspect;
use App\Support\Metrics\MetricsRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * AOP entry point for HTTP layer — traces every API request.
 */
class ConcurrencyTracingMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $point = 'http.'.str_replace('/', '.', trim($request->path(), '/'));

        return ConcurrencyAspect::around($point, function () use ($request, $next, $point) {
            $started = microtime(true);
            $response = $next($request);
            $durationMs = (microtime(true) - $started) * 1000;

            MetricsRegistry::gaugeTracked('http.last_request_duration_ms', $durationMs);
            MetricsRegistry::incrementTracked("http.status.{$response->getStatusCode()}");

            if ($response->getStatusCode() >= 500) {
                MetricsRegistry::incrementTracked('http.errors.total');
            }

            $response->headers->set('X-Concurrency-Trace', $point);

            return $response;
        });
    }
}
