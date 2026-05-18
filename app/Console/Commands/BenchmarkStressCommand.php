<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class BenchmarkStressCommand extends Command
{
    protected $signature = 'benchmark:stress
        {--users=100 : Concurrent virtual users}
        {--url= : Base URL (defaults to STRESS_TEST_BASE_URL)}
        {--email=demo@highperformance.test}
        {--password=password}';

    protected $description = 'Requirement 9/10: HTTP stress + benchmark report (100 users by default)';

    public function handle(): int
    {
        $users = (int) $this->option('users');
        $baseUrl = rtrim($this->option('url') ?: config('high_performance.stress_test.base_url'), '/');
        $email = (string) $this->option('email');
        $password = (string) $this->option('password');

        $this->info("Authenticating at {$baseUrl}...");

        try {
            $login = Http::acceptJson()
                ->timeout(5)
                ->connectTimeout(3)
                ->post("{$baseUrl}/api/auth/login", compact('email', 'password'));
        } catch (ConnectionException $e) {
            $this->error("Cannot connect to {$baseUrl}");
            $this->line('Start the API first: composer octane  (or: php artisan serve)');
            $this->line('Then run: php artisan benchmark:stress --users='.$users);

            return self::FAILURE;
        }

        if (! $login->successful()) {
            $this->error('Login failed: '.$login->body());

            return self::FAILURE;
        }

        $token = $login->json('data.token');
        $this->info('Token acquired. Running stress mix...');

        $started = microtime(true);
        $responses = Http::pool(function ($pool) use ($users, $baseUrl, $token) {
            for ($i = 0; $i < $users; $i++) {
                $pool->as("products_{$i}")
                    ->withToken($token)
                    ->acceptJson()
                    ->get("{$baseUrl}/api/product");

                if ($i % 3 === 0) {
                    $pool->as("wallet_{$i}")
                        ->withToken($token)
                        ->acceptJson()
                        ->get("{$baseUrl}/api/wallet");
                }
            }
        });
        $duration = microtime(true) - $started;

        $stats = [
            'users' => $users,
            'total_requests' => count($responses),
            'duration_seconds' => round($duration, 3),
            'rps' => round(count($responses) / max($duration, 0.001), 2),
            'success' => 0,
            'failed' => 0,
            'status_codes' => [],
            'latencies_ms' => [],
        ];

        foreach ($responses as $response) {
            if ($response instanceof \Throwable) {
                $stats['failed']++;
                continue;
            }

            $code = $response->status();
            $stats['status_codes'][$code] = ($stats['status_codes'][$code] ?? 0) + 1;

            if ($response->successful()) {
                $stats['success']++;
            } else {
                $stats['failed']++;
            }

            $stats['latencies_ms'][] = $response->transferStats?->getTransferTime()
                ? round($response->transferStats->getTransferTime() * 1000, 2)
                : 0;
        }

        sort($stats['latencies_ms']);
        $count = count($stats['latencies_ms']) ?: 1;
        $stats['latency_p50_ms'] = $stats['latencies_ms'][(int) floor($count * 0.5)] ?? 0;
        $stats['latency_p95_ms'] = $stats['latencies_ms'][(int) floor($count * 0.95)] ?? 0;
        $stats['latency_max_ms'] = $stats['latencies_ms'][$count - 1] ?? 0;
        unset($stats['latencies_ms']);

        $filename = 'stress-'.now()->format('Y-m-d_His').'.json';
        $json = json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $storagePath = storage_path('app/benchmarks/'.$filename);
        $projectPath = base_path('docs/reports/03-stress-test/stress-report.json');

        File::ensureDirectoryExists(dirname($storagePath));
        File::ensureDirectoryExists(dirname($projectPath));
        File::put($storagePath, $json);
        File::put($projectPath, $json);

        $this->table(array_keys($stats), [array_map(fn ($v) => is_array($v) ? json_encode($v) : $v, $stats)]);
        $this->info("Report saved: {$storagePath}");
        $this->info("Project copy: {$projectPath}");
        $this->line('Use Grafana dashboard while running this test for CPU/RAM charts (see docs/TESTING_GUIDE.html).');

        return self::SUCCESS;
    }
}
