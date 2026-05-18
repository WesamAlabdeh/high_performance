<?php

namespace App\Services\Cache;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

/**
 * Requirement 6: Distributed / shared cache for hot read paths.
 */
class ProductCacheService
{
    public function paginateActive(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $key = 'products:list:'.md5(json_encode($filters).':'.$perPage.':'.request()->get('page', 1));

        return Cache::remember($key, config('high_performance.cache.product_ttl', 300), function () use ($filters, $perPage) {
            return Product::filters($filters)
                ->where('is_active', true)
                ->with('category')
                ->paginate($perPage);
        });
    }

    public function findActive(int $id): Product
    {
        $key = "products:show:{$id}";

        return Cache::remember($key, config('high_performance.cache.product_ttl', 300), function () use ($id) {
            return Product::with('category')->where('is_active', true)->findOrFail($id);
        });
    }

    public function flush(?int $productId = null): void
    {
        if ($productId) {
            Cache::forget("products:show:{$productId}");
        }

    }
}
