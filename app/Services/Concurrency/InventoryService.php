<?php

namespace App\Services\Concurrency;

use App\Aspects\ConcurrencyAspect;
use App\Exceptions\Errors;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Requirement 1: Concurrent access & data integrity.
 * Synchronization point: pessimistic row lock inside DB transaction.
 */
class InventoryService
{
    /**
     * @param  array<int, int>  $lines  product_id => quantity
     * @return array<int, Product>
     */
    public function reserveStock(array $lines): array
    {
        return ConcurrencyAspect::around('inventory.pessimistic_lock', fn () => DB::transaction(function () use ($lines) {
            $productIds = array_keys($lines);
            sort($productIds);

            $locked = Product::query()
                ->whereIn('id', $productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($productIds as $productId) {
                $qty = (int) ($lines[$productId] ?? 0);
                $product = $locked->get($productId);

                if (! $product || $qty <= 0) {
                    Errors::ResourceNotFound('Product not found', "product #{$productId}");
                }

                if ($product->stock < $qty) {
                    Errors::InvalidOperation(
                        "Insufficient stock for product #{$productId}",
                        'stock below requested quantity'
                    );
                }

                $product->stock -= $qty;
                $product->version = (int) $product->version + 1;
                $product->save();
            }

            return $locked->all();
        }));
    }

    public function releaseStock(array $lines): void
    {
        DB::transaction(function () use ($lines) {
            $productIds = array_keys($lines);
            sort($productIds);

            $products = Product::query()
                ->whereIn('id', $productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($productIds as $productId) {
                $qty = (int) ($lines[$productId] ?? 0);
                $product = $products->get($productId);

                if ($product && $qty > 0) {
                    $product->stock += $qty;
                    $product->version = (int) $product->version + 1;
                    $product->save();
                }
            }
        });
    }
}
