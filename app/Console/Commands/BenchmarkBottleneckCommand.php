<?php

namespace App\Console\Commands;

use App\Support\Metrics\MetricsRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BenchmarkBottleneckCommand extends Command
{
    protected $signature = 'benchmark:bottlenecks';

    protected $description = 'Requirement 10: Summarize Prometheus-style metrics and highlight bottlenecks';

    public function handle(): int
    {
        $metrics = MetricsRegistry::all();

        $bottlenecks = [];

        foreach ($metrics['histograms'] as $name => $stats) {
            if (($stats['avg'] ?? 0) > 500) {
                $bottlenecks[] = [
                    'point' => $name,
                    'avg_ms' => $stats['avg'],
                    'max_ms' => $stats['max'],
                    'reason' => 'High average latency — candidate for cache/queue/async tuning',
                ];
            }
        }

        foreach ($metrics['counters'] as $name => $count) {
            if (str_contains($name, 'failure') && $count > 0) {
                $bottlenecks[] = [
                    'point' => $name,
                    'count' => $count,
                    'reason' => 'Failures detected — inspect logs and circuit breaker',
                ];
            }
        }

        $report = [
            'generated_at' => now()->toIso8601String(),
            'metrics' => $metrics,
            'bottlenecks' => $bottlenecks,
            'recommendations' => [
                'Enable Octane + Swoole workers for HTTP throughput',
                'Keep PAYMENT_SIMULATION_DELAY_MS low during stress tests',
                'Run dedicated queue workers per queue name',
                'Use CACHE_STORE=octane or redis for hot reads',
            ],
        ];

        $filename = 'bottlenecks-'.now()->format('Y-m-d_His').'.json';
        $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $storagePath = storage_path('app/benchmarks/'.$filename);
        $projectPath = base_path('docs/reports/04-benchmark/bottlenecks-report.json');

        File::ensureDirectoryExists(dirname($storagePath));
        File::ensureDirectoryExists(dirname($projectPath));
        File::put($storagePath, $json);
        File::put($projectPath, $json);

        $this->info('Bottleneck report: '.$storagePath);
        $this->info('Project copy: '.$projectPath);

        if ($bottlenecks === []) {
            $this->info('No critical bottlenecks detected from current metrics sample.');
        } else {
            $this->table(['point', 'detail', 'reason'], array_map(fn ($b) => [
                $b['point'],
                json_encode(array_diff_key($b, ['point' => 1, 'reason' => 1])),
                $b['reason'],
            ], $bottlenecks));
        }

        return self::SUCCESS;
    }
}
