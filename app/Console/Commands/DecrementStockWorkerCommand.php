<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DecrementStockWorkerCommand extends Command
{
    protected $signature = 'internal:decrement-stock {product_id} {--safe} {--optimistic}';

    protected $description = 'Internal worker for parallel race / locking demos';

    public function handle(): int
    {
        $productId = (int) $this->argument('product_id');

        if ($this->option('optimistic')) {
            $this->optimisticDecrement($productId);

            return self::SUCCESS;
        }

        DB::transaction(function () use ($productId) {
            $query = Product::query()->whereKey($productId);

            $product = $this->option('safe')
                ? $query->lockForUpdate()->firstOrFail()
                : $query->firstOrFail();

            if ($product->stock > 0) {
                $product->stock -= 1;
                $product->version = (int) $product->version + 1;
                $product->save();
            }
        });

        return self::SUCCESS;
    }

    private function optimisticDecrement(int $productId): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $product = Product::query()->whereKey($productId)->firstOrFail();

            if ($product->stock <= 0) {
                return;
            }

            $version = (int) $product->version;
            $updated = Product::query()
                ->whereKey($productId)
                ->where('version', $version)
                ->where('stock', '>', 0)
                ->update([
                    'stock' => $product->stock - 1,
                    'version' => $version + 1,
                ]);

            if ($updated === 1) {
                return;
            }
        }
    }
}
