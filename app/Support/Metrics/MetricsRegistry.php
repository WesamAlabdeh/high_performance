<?php

namespace App\Support\Metrics;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Lightweight metrics store (Prometheus text exposition compatible).
 * Uses database/redis store — Octane table keys are too short for long metric names.
 */
final class MetricsRegistry
{
    private static function cache(): Repository
    {
        return Cache::store(config('high_performance.metrics.cache_store', 'database'));
    }

    private static function storageKey(string $prefix, string $name): string
    {
        $key = "{$prefix}:{$name}";

        return strlen($key) > 60 ? "{$prefix}:".md5($name) : $key;
    }

    public static function increment(string $name, int $by = 1): void
    {
        $key = self::storageKey('metrics:counter', $name);
        self::cache()->put($key, (int) self::cache()->get($key, 0) + $by, now()->addHours(6));
    }

    public static function observe(string $name, float $value): void
    {
        $key = self::storageKey('metrics:histogram', $name);
        $samples = self::cache()->get($key, []);
        $samples[] = $value;

        if (count($samples) > 5000) {
            $samples = array_slice($samples, -5000);
        }

        self::cache()->put($key, $samples, now()->addHours(6));
    }

    public static function gauge(string $name, float $value): void
    {
        self::cache()->put(self::storageKey('metrics:gauge', $name), $value, now()->addHours(6));
    }

    /**
     * @return array{counters: array<string, int>, gauges: array<string, float>, histograms: array<string, array{count: int, sum: float, avg: float, max: float}>}
     */
    public static function all(): array
    {
        $counters = [];
        $gauges = [];
        $histograms = [];
        $manifest = self::cache()->get('metrics:manifest', []);

        foreach ($manifest as $entry) {
            [$type, $name] = explode(':', $entry, 2);
            match ($type) {
                'counter' => $counters[$name] = (int) self::cache()->get(self::storageKey('metrics:counter', $name), 0),
                'gauge' => $gauges[$name] = (float) self::cache()->get(self::storageKey('metrics:gauge', $name), 0),
                'histogram' => $histograms[$name] = self::summarizeHistogram((array) self::cache()->get(self::storageKey('metrics:histogram', $name), [])),
                default => null,
            };
        }

        return compact('counters', 'gauges', 'histograms');
    }

    public static function track(string $type, string $name): void
    {
        $manifest = self::cache()->get('metrics:manifest', []);
        $entry = "{$type}:{$name}";
        if (! in_array($entry, $manifest, true)) {
            $manifest[] = $entry;
            self::cache()->put('metrics:manifest', $manifest, now()->addDay());
        }
    }

    public static function incrementTracked(string $name, int $by = 1): void
    {
        self::track('counter', $name);
        self::increment($name, $by);
    }

    public static function observeTracked(string $name, float $value): void
    {
        self::track('histogram', $name);
        self::observe($name, $value);
    }

    public static function gaugeTracked(string $name, float $value): void
    {
        self::track('gauge', $name);
        self::gauge($name, $value);
    }

    /**
     * @param  array<int, float>  $samples
     * @return array{count: int, sum: float, avg: float, max: float}
     */
    private static function summarizeHistogram(array $samples): array
    {
        if ($samples === []) {
            return ['count' => 0, 'sum' => 0.0, 'avg' => 0.0, 'max' => 0.0];
        }

        $sum = array_sum($samples);

        return [
            'count' => count($samples),
            'sum' => round($sum, 2),
            'avg' => round($sum / count($samples), 2),
            'max' => round(max($samples), 2),
        ];
    }

    public static function toPrometheus(): string
    {
        $lines = [];
        $data = self::all();

        foreach ($data['counters'] as $name => $value) {
            $metric = str_replace('.', '_', $name);
            $lines[] = "# TYPE {$metric} counter";
            $lines[] = "{$metric} {$value}";
        }

        foreach ($data['gauges'] as $name => $value) {
            $metric = str_replace('.', '_', $name);
            $lines[] = "# TYPE {$metric} gauge";
            $lines[] = "{$metric} {$value}";
        }

        foreach ($data['histograms'] as $name => $stats) {
            $metric = str_replace('.', '_', $name);
            $lines[] = "# TYPE {$metric} summary";
            $lines[] = "{$metric}_count {$stats['count']}";
            $lines[] = "{$metric}_sum {$stats['sum']}";
            $lines[] = "{$metric}_avg {$stats['avg']}";
            $lines[] = "{$metric}_max {$stats['max']}";
        }

        return implode("\n", $lines)."\n";
    }
}
