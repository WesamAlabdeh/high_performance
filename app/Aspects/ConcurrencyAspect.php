<?php

namespace App\Aspects;

use App\Support\Metrics\MetricsRegistry;
use Closure;
use Throwable;

final class ConcurrencyAspect
{
    public static function before(string $point): void
    {
        MetricsRegistry::incrementTracked("aspect.{$point}.entered");
    }

    public static function after(string $point, float $startedAt, bool $success): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;
        MetricsRegistry::observeTracked("aspect.{$point}.duration_ms", $durationMs);

        if ($success) {
            MetricsRegistry::incrementTracked("aspect.{$point}.success");
        } else {
            MetricsRegistry::incrementTracked("aspect.{$point}.failure");
        }
    }

    public static function around(string $point, Closure $callback): mixed
    {
        self::before($point);
        $startedAt = microtime(true);

        try {
            $result = $callback();
            self::after($point, $startedAt, true);

            return $result;
        } catch (Throwable $e) {
            self::after($point, $startedAt, false);
            throw $e;
        }
    }
}
