<?php

namespace App\Http\Controllers;

use App\Support\Metrics\MetricsRegistry;
use Illuminate\Http\Response;

class MetricsController extends Controller
{
    public function prometheus(): Response
    {
        return response(MetricsRegistry::toPrometheus(), 200, [
            'Content-Type' => 'text/plain; version=0.0.4',
        ]);
    }

    public function json(): Response
    {
        return response()->json([
            'status' => 'success',
            'data' => MetricsRegistry::all(),
        ]);
    }
}
