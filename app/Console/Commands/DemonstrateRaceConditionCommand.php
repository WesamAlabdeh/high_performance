<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;

class DemonstrateRaceConditionCommand extends Command
{
    protected $signature = 'concurrency:race-demo {product_id=1} {--attempts=30}';

    protected $description = 'Parallel race demo: unsafe vs lockForUpdate (screenshot for PDF report)';

    public function handle(): int
    {
        $productId = (int) $this->argument('product_id');
        $attempts = (int) $this->option('attempts');
        $startStock = 10;

        $product = Product::findOrFail($productId);
        $product->update(['stock' => $startStock]);

        $this->info("Stock reset to {$startStock}. Running {$attempts} PARALLEL unsafe decrements...");
        $this->runParallelWorkers($productId, $attempts, safe: false);
        $unsafeStock = (int) Product::find($productId)->stock;
        $this->warn("UNSAFE final stock: {$unsafeStock} (expected ".max(0, $startStock - $attempts).')');

        $product->update(['stock' => $startStock]);
        $this->info("Stock reset. Running {$attempts} PARALLEL safe decrements (lockForUpdate)...");
        $this->runParallelWorkers($productId, $attempts, safe: true);
        $safeStock = (int) Product::find($productId)->stock;
        $this->info("SAFE final stock: {$safeStock}");

        return self::SUCCESS;
    }

    private function runParallelWorkers(int $productId, int $attempts, bool $safe): void
    {
        $php = PHP_BINARY;
        $artisan = base_path('artisan');
        $flag = $safe ? ' --safe' : '';

        $pool = Process::pool(function (Pool $pool) use ($php, $artisan, $productId, $attempts, $flag) {
            for ($i = 0; $i < $attempts; $i++) {
                $pool->command("{$php} {$artisan} internal:decrement-stock {$productId}{$flag}");
            }
        })->start();

        $pool->wait();
    }
}
