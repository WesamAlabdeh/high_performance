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

    protected $description = 'Requirement 9: 100 concurrent users on all API operations';

    public function handle(): int
    {
        $users = (int) $this->option('users');
        $baseUrl = rtrim($this->option('url') ?: config('high_performance.stress_test.base_url'), '/');
        $email = (string) $this->option('email');
        $password = (string) $this->option('password');

        $operations = [
            'auth_login' => 'POST /api/auth/login',
            'product_list' => 'GET /api/product',
            'product_show' => 'GET /api/product/1',
            'wallet' => 'GET /api/wallet',
            'cart_show' => 'GET /api/cart',
            'cart_update' => 'POST /api/cart',
            'orders_list' => 'GET /api/order',
            'order_create' => 'POST /api/order',
            'lb_status' => 'GET /api/admin/load-balancer/status',
            'batch_daily' => 'POST /api/admin/batch/daily-sales',
        ];

        $stats = [
            'users' => $users,
            'operations_per_user' => count($operations),
            'total_requests' => 0,
            'duration_seconds' => 0,
            'rps' => 0,
            'success' => 0,
            'failed' => 0,
            'status_codes' => [],
            'by_operation' => array_fill_keys(array_keys($operations), ['success' => 0, 'failed' => 0]),
            'latency_p50_ms' => 0,
            'latency_p95_ms' => 0,
            'latency_max_ms' => 0,
            'operations' => $operations,
        ];

        $latencies = [];
        $started = microtime(true);

        $this->info("Phase 1: {$users} concurrent logins...");
        try {
            $loginResponses = Http::pool(function ($pool) use ($users, $baseUrl, $email, $password) {
                for ($i = 0; $i < $users; $i++) {
                    $pool->as("auth_login_{$i}")
                        ->acceptJson()
                        ->timeout(30)
                        ->post("{$baseUrl}/api/auth/login", compact('email', 'password'));
                }
            });
            $this->collectResponses($loginResponses, $stats, $latencies);
        } catch (ConnectionException) {
            $this->error("Cannot connect to {$baseUrl}");
            $this->line('Start the API first: composer octane');

            return self::FAILURE;
        }

        $this->info('Phase 2: fresh token + authenticated operations...');
        try {
            $login = Http::acceptJson()
                ->timeout(10)
                ->post("{$baseUrl}/api/auth/login", compact('email', 'password'));
        } catch (ConnectionException) {
            $this->error('Login failed after phase 1');

            return self::FAILURE;
        }

        if (! $login->successful()) {
            $this->error('Login failed: '.$login->body());

            return self::FAILURE;
        }

        $token = $login->json('data.token');

        $authResponses = Http::pool(function ($pool) use ($users, $baseUrl, $token) {
            for ($i = 0; $i < $users; $i++) {
                $pool->as("product_list_{$i}")
                    ->withToken($token)->acceptJson()->timeout(30)
                    ->get("{$baseUrl}/api/product");

                $pool->as("product_show_{$i}")
                    ->withToken($token)->acceptJson()->timeout(30)
                    ->get("{$baseUrl}/api/product/1");

                $pool->as("wallet_{$i}")
                    ->withToken($token)->acceptJson()->timeout(30)
                    ->get("{$baseUrl}/api/wallet");

                $pool->as("cart_show_{$i}")
                    ->withToken($token)->acceptJson()->timeout(30)
                    ->get("{$baseUrl}/api/cart");

                $pool->as("cart_update_{$i}")
                    ->withToken($token)->acceptJson()->timeout(30)
                    ->post("{$baseUrl}/api/cart", ['product_id' => 1, 'quantity' => 1]);

                $pool->as("orders_list_{$i}")
                    ->withToken($token)->acceptJson()->timeout(30)
                    ->get("{$baseUrl}/api/order");

                $pool->as("order_create_{$i}")
                    ->withToken($token)->acceptJson()->timeout(30)
                    ->post("{$baseUrl}/api/order", ['user_notes' => "stress-{$i}"]);

                $pool->as("lb_status_{$i}")
                    ->withToken($token)->acceptJson()->timeout(30)
                    ->get("{$baseUrl}/api/admin/load-balancer/status");

                $pool->as("batch_daily_{$i}")
                    ->withToken($token)->acceptJson()->timeout(30)
                    ->post("{$baseUrl}/api/admin/batch/daily-sales");
            }
        });

        $this->collectResponses($authResponses, $stats, $latencies);

        $duration = microtime(true) - $started;
        $stats['duration_seconds'] = round($duration, 3);
        $stats['rps'] = round($stats['total_requests'] / max($duration, 0.001), 2);

        sort($latencies);
        $count = count($latencies) ?: 1;
        $stats['latency_p50_ms'] = $latencies[(int) floor($count * 0.5)] ?? 0;
        $stats['latency_p95_ms'] = $latencies[(int) floor($count * 0.95)] ?? 0;
        $stats['latency_max_ms'] = $latencies[$count - 1] ?? 0;

        $filename = 'stress-'.now()->format('Y-m-d_His').'.json';
        $json = json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $storagePath = storage_path('app/benchmarks/'.$filename);
        $projectPath = base_path('docs/reports/03-stress-test/stress-report.json');

        File::ensureDirectoryExists(dirname($storagePath));
        File::ensureDirectoryExists(dirname($projectPath));
        File::put($storagePath, $json);
        File::put($projectPath, $json);

        $this->table(
            ['metric', 'value'],
            collect($stats)->except(['by_operation', 'operations', 'status_codes'])->map(fn ($v, $k) => [$k, is_array($v) ? json_encode($v) : $v])->values()->all()
        );

        $this->newLine();
        $this->info('By operation:');
        foreach ($stats['by_operation'] as $op => $counts) {
            $this->line("  {$operations[$op]} → success: {$counts['success']}, failed: {$counts['failed']}");
        }

        $this->newLine();
        $this->info("Report saved: {$storagePath}");
        $this->info("Project copy: {$projectPath}");

        return self::SUCCESS;
    }

    private function collectResponses(array $responses, array &$stats, array &$latencies): void
    {
        foreach ($responses as $key => $response) {
            $stats['total_requests']++;
            $operation = preg_replace('/_\d+$/', '', (string) $key);

            if ($response instanceof \Throwable) {
                $stats['failed']++;
                if (isset($stats['by_operation'][$operation])) {
                    $stats['by_operation'][$operation]['failed']++;
                }

                continue;
            }

            $code = $response->status();
            $stats['status_codes'][$code] = ($stats['status_codes'][$code] ?? 0) + 1;

            if ($response->successful()) {
                $stats['success']++;
                if (isset($stats['by_operation'][$operation])) {
                    $stats['by_operation'][$operation]['success']++;
                }
            } else {
                $stats['failed']++;
                if (isset($stats['by_operation'][$operation])) {
                    $stats['by_operation'][$operation]['failed']++;
                }
            }

            $latencies[] = $response->transferStats?->getTransferTime()
                ? round($response->transferStats->getTransferTime() * 1000, 2)
                : 0;
        }
    }
}
