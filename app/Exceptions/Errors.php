<?php

namespace App\Exceptions;

class Errors
{
    public static function ResourceNotFound(string $message = 'Resource Not Found', string $systemMessage = 'Resource Not Found'): void
    {
        throw new ApiException($message, 'RESOURCE_NOT_FOUND', 404, $systemMessage);
    }

    public static function InvalidCredentials(string $message = 'invalid credentials', string $systemMessage = 'invalid credentials'): void
    {
        throw new ApiException($message, 'INVALID_CREDENTIALS', 400, $systemMessage);
    }

    public static function InvalidOperation(string $message = 'Invalid Operation', string $systemMessage = 'Invalid Operation'): void
    {
        throw new ApiException($message, 'INVALID_OPERATION', 400, $systemMessage);
    }

    public static function CapacityExceeded(string $message = 'System is busy. Please retry shortly.', string $systemMessage = 'capacity exceeded'): void
    {
        throw new ApiException($message, 'CAPACITY_EXCEEDED', 503, $systemMessage);
    }

    public static function CircuitOpen(string $message = 'Service temporarily unavailable.', string $systemMessage = 'circuit breaker open'): void
    {
        throw new ApiException($message, 'CIRCUIT_OPEN', 503, $systemMessage);
    }

    public static function Conflict(string $message = 'Concurrent update conflict', string $systemMessage = 'optimistic lock conflict'): void
    {
        throw new ApiException($message, 'CONCURRENT_CONFLICT', 409, $systemMessage);
    }
}
