<?php

namespace App\Services\Concurrency;

use App\Aspects\ConcurrencyAspect;
use App\Exceptions\ApiException;
use App\Exceptions\Errors;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class OptimisticInventoryService
{
    private const MAX_RETRIES = 5;

    public function reserveStock(array $lines): array
    {
        return ConcurrencyAspect::around('inventory.optimistic_lock', function () use ($lines) {
            $productIds = array_keys($lines);
            sort($productIds);

            for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
                try {
                    return DB::transaction(function () use ($lines, $productIds) {
                        $products = Product::query()
                            ->whereIn('id', $productIds)
                            ->orderBy('id')
                            ->get()
                            ->keyBy('id');

                        foreach ($productIds as $productId) {
                            $qty = (int) ($lines[$productId] ?? 0);
                            $product = $products->get($productId);

                            if (! $product || $qty <= 0) {
                                Errors::ResourceNotFound('Product not found', "product #{$productId}");
                            }

                            if ($product->stock < $qty) {
                                Errors::InvalidOperation(
                                    "Insufficient stock for product #{$productId}",
                                    'stock below requested quantity'
                                );
                            }

                            $currentVersion = (int) $product->version;
                            $newStock = $product->stock - $qty;

                            $updated = Product::query()
                                ->whereKey($productId)
                                ->where('version', $currentVersion)
                                ->update([
                                    'stock' => $newStock,
                                    'version' => $currentVersion + 1,
                                ]);

                            if ($updated === 0) {
                                Errors::Conflict(
                                    "Concurrent update on product #{$productId}",
                                    'optimistic version mismatch'
                                );
                            }

                            $product->stock = $newStock;
                            $product->version = $currentVersion + 1;
                        }

                        return $products->all();
                    });
                } catch (ApiException $e) {
                    if ($e->errorCode() !== 'CONCURRENT_CONFLICT' || $attempt === self::MAX_RETRIES) {
                        throw $e;
                    }
                }
            }

            Errors::Conflict('Could not reserve stock after retries', 'optimistic lock exhausted');
        });
    }
}
