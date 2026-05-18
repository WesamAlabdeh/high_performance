<?php

namespace App\Support\Metrics;

use Illuminate\Support\Facades\Cache;

/**
 * Lightweight metrics store (Prometheus text exposition compatible).
 * Shared via cache driver (octane/redis/database).
 */
final class MetricsRegistry
{
    public static function increment(string $name, int $by = 1): void
    {
        $key = "metrics:counter:{$name}";
        Cache::put($key, (int) Cache::get($key, 0) + $by, now()->addHours(6));
    }

    public static function observe(string $name, float $value): void
    {
        $key = "metrics:histogram:{$name}";
        $samples = Cache::get($key, []);
        $samples[] = $value;

        if (count($samples) > 5000) {
            $samples = array_slice($samples, -5000);
        }

        Cache::put($key, $samples, now()->addHours(6));
    }

    public static function gauge(string $name, float $value): void
    {
        Cache::put("metrics:gauge:{$name}", $value, now()->addHours(6));
    }

    /**
     * @return array{counters: array<string, int>, gauges: array<string, float>, histograms: array<string, array{count: int, sum: float, avg: float, max: float}>}
     */
    public static function all(): array
    {
        $store = Cache::getStore();
        $counters = [];
        $gauges = [];
        $histograms = [];

        if (! method_exists($store, 'getRedis')) {
            return compact('counters', 'gauges', 'histograms');
        }

        // Fallback: scan known metric keys from our naming convention is hard without Redis keys().
        // For university demo we persist a manifest list.
        $manifest = Cache::get('metrics:manifest', []);

        foreach ($manifest as $entry) {
            [$type, $name] = explode(':', $entry, 2);
            match ($type) {
                'counter' => $counters[$name] = (int) Cache::get("metrics:counter:{$name}", 0),
                'gauge' => $gauges[$name] = (float) Cache::get("metrics:gauge:{$name}", 0),
                'histogram' => $histograms[$name] = self::summarizeHistogram((array) Cache::get("metrics:histogram:{$name}", [])),
                default => null,
            };
        }

        return compact('counters', 'gauges', 'histograms');
    }

    public static function track(string $type, string $name): void
    {
        $manifest = Cache::get('metrics:manifest', []);
        $entry = "{$type}:{$name}";
        if (! in_array($entry, $manifest, true)) {
            $manifest[] = $entry;
            Cache::put('metrics:manifest', $manifest, now()->addDay());
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
