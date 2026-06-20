<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;

class DemonstrateOptimisticLockCommand extends Command
{
    protected $signature = 'concurrency:optimistic-demo {product_id=1} {--attempts=20}';

    protected $description = 'Requirement 7: Compare pessimistic vs optimistic locking under parallel load';

    public function handle(): int
    {
        $productId = (int) $this->argument('product_id');
        $attempts = (int) $this->option('attempts');
        $startStock = 10;

        $product = Product::findOrFail($productId);
        $product->update(['stock' => $startStock, 'version' => 0]);

        $this->info("Stock={$startStock}. Running {$attempts} parallel OPTIMISTIC decrements...");
        $this->runParallelWorkers($productId, $attempts, 'optimistic');
        $optimisticStock = (int) Product::find($productId)->stock;
        $this->info("OPTIMISTIC final stock: {$optimisticStock} (expected ".max(0, $startStock - $attempts).')');

        $product->update(['stock' => $startStock, 'version' => 0]);
        $this->info("Stock reset. Running {$attempts} parallel PESSIMISTIC decrements...");
        $this->runParallelWorkers($productId, $attempts, 'pessimistic');
        $pessimisticStock = (int) Product::find($productId)->stock;
        $this->info("PESSIMISTIC final stock: {$pessimisticStock}");

        return self::SUCCESS;
    }

    private function runParallelWorkers(int $productId, int $attempts, string $mode): void
    {
        $php = PHP_BINARY;
        $artisan = base_path('artisan');
        $flag = $mode === 'optimistic' ? ' --optimistic' : ' --safe';

        $pool = Process::pool(function (Pool $pool) use ($php, $artisan, $productId, $attempts, $flag) {
            for ($i = 0; $i < $attempts; $i++) {
                $pool->command("{$php} {$artisan} internal:decrement-stock {$productId}{$flag}");
            }
        })->start();

        $pool->wait();
    }
}
