<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class ApiException extends Exception
{
    public function __construct(
        string $message,
        private readonly string $error = 'INTERNAL_SERVER_ERROR',
        public int $statusCode = 500,
        public string $systemMessage = ''
    ) {
        parent::__construct($message, $statusCode);
    }

    public function response(): JsonResponse
    {
        return response()->json([
            'status' => 'fail',
            'error' => $this->error,
            'message' => $this->getMessage(),
        ], $this->statusCode);
    }
}
