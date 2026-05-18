<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DecrementStockWorkerCommand extends Command
{
    protected $signature = 'internal:decrement-stock {product_id} {--safe}';

    protected $description = 'Internal worker for parallel race demo';

    public function handle(): int
    {
        $productId = (int) $this->argument('product_id');

        DB::transaction(function () use ($productId) {
            $query = Product::query()->whereKey($productId);

            $product = $this->option('safe')
                ? $query->lockForUpdate()->firstOrFail()
                : $query->firstOrFail();

            if ($product->stock > 0) {
                $product->stock -= 1;
                $product->save();
            }
        });

        return self::SUCCESS;
    }
}
