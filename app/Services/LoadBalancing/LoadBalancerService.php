<?php

namespace App\Services\LoadBalancing;

use App\Aspects\ConcurrencyAspect;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class LoadBalancerService
{
    private function cache(): Repository
    {
        return Cache::store(config('high_performance.load_balancer.cache_store', 'database'));
    }

    public function selectInstance(string $pool = 'api'): string
    {
        return ConcurrencyAspect::around('load_balancer.select', function () use ($pool) {
            $instances = $this->healthyInstances($pool);

            if ($instances === []) {
                return config('high_performance.load_balancer.instances')[0]
                  ?? config('app.url');
            }

            $index = (int) $this->cache()->increment("lb:{$pool}:rr_index") % count($instances);

            return $instances[$index];
        });
    }

    public function healthyInstances(string $pool = 'api'): array
    {
        $configured = config('high_performance.load_balancer.instances', []);
        $healthy = [];

        foreach ($configured as $baseUrl) {
            if ($this->isHealthy($baseUrl)) {
                $healthy[] = rtrim($baseUrl, '/');
            }
        }

        return $healthy;
    }

    public function status(string $pool = 'api'): array
    {
        $configured = config('high_performance.load_balancer.instances', []);
        $instances = [];
        $healthy = [];

        foreach ($configured as $baseUrl) {
            $url = rtrim($baseUrl, '/');
            $check = $this->probe($url);
            $instances[] = [
                'url' => $url,
                'healthy' => $check['healthy'],
                'latency_ms' => $check['latency_ms'],
            ];

            if ($check['healthy']) {
                $healthy[] = $url;
            }
        }

        return [
            'pool' => $pool,
            'instances' => $instances,
            'healthy_count' => count($healthy),
            'total_count' => count($configured),
            'selected' => $healthy !== [] ? $this->selectInstance($pool) : null,
            'strategy' => config('high_performance.load_balancer.strategy', 'round_robin'),
        ];
    }

    private function isHealthy(string $baseUrl): bool
    {
        $cacheKey = 'lb:health:'.md5($baseUrl);
        $cached = $this->cache()->get($cacheKey);

        if (is_bool($cached)) {
            return $cached;
        }

        $result = $this->probe($baseUrl);
        $this->cache()->put($cacheKey, $result['healthy'], config('high_performance.load_balancer.health_ttl', 15));

        return $result['healthy'];
    }

    private function probe(string $baseUrl): array
    {
        $path = config('high_performance.load_balancer.health_path', '/up');
        $started = microtime(true);

        try {
            $response = Http::timeout(2)->get(rtrim($baseUrl, '/').$path);

            return [
                'healthy' => $response->successful(),
                'latency_ms' => round((microtime(true) - $started) * 1000, 2),
            ];
        } catch (\Throwable) {
            return ['healthy' => false, 'latency_ms' => null];
        }
    }
}
