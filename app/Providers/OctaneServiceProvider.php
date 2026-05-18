<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Facades\Octane;

class OctaneServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (! class_exists(Octane::class)) {
            return;
        }
        Octane::tick('health-check', function () {
        })->seconds(30);
    }
}
