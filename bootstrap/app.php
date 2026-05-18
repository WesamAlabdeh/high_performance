<?php

use App\Exceptions\ApiException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::prefix('admin')->middleware('api')->group(__DIR__.'/../routes/admin.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(append: [
            \App\Http\Middleware\ForceJsonResponse::class,
        ]);

        $middleware->alias([
            'capacity.control' => \App\Http\Middleware\CapacityControlMiddleware::class,
            'circuit.breaker' => \App\Http\Middleware\CircuitBreakerMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $exception, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            $errorResponse = static fn (string $error, string $message, int $statusCode) => response()->json([
                'status' => 'fail',
                'error' => $error,
                'message' => $message,
            ], $statusCode);

            return match (true) {
                $exception instanceof AuthenticationException => $errorResponse(
                    'NOT_AUTHENTICATED',
                    'You are not authenticated',
                    401
                ),
                $exception instanceof ThrottleRequestsException => $errorResponse(
                    'TOO_MANY_ATTEMPTS',
                    'Too many attempts. Please try again later.',
                    429
                ),
                $exception instanceof ApiException => $exception->response(),
                $exception instanceof ValidationException => $errorResponse(
                    'VALIDATION_ERROR',
                    $exception->validator->errors()->first() ?: 'Validation failed',
                    422
                ),
                $exception instanceof NotFoundHttpException,
                $exception instanceof RouteNotFoundException => $errorResponse(
                    'RESOURCE_NOT_FOUND',
                    'Resource not found',
                    404
                ),
                $exception instanceof HttpException && $exception->getStatusCode() === 403 => $errorResponse(
                    'FORBIDDEN',
                    $exception->getMessage() ?: 'Forbidden',
                    403
                ),
                default => $errorResponse(
                    'INTERNAL_SERVER_ERROR',
                    config('app.debug') ? $exception->getMessage() : 'Something went wrong',
                    500
                ),
            };
        });
    })->create();
