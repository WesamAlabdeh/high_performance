<?php

namespace App\Utils;

use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Log;

class Logger
{
    public static function LogException(\Throwable $ex): void
    {
        Log::error('Message : '.$ex->getMessage());
        if ($ex instanceof ApiException) {
            Log::error('System message: '.$ex->systemMessage);
        }
        Log::error('Line : '.$ex->getLine());
        Log::error('File : '.$ex->getFile());
    }
}
