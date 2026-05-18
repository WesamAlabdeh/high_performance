<?php

namespace App\Services\Cache;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\Cache;

/**
 * Requirement 6: Cache product reads as arrays (safe under Octane; no serialized Eloquent graphs).
 */
class ProductCacheService
{
    private function store(): \Illuminate\Contracts\Cache\Repository
    {
        return Cache::store(config('high_performance.cache.product_store', 'database'));
    }

    public function paginateActive(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $page = max(1, (int) request()->get('page', 1));
        $key = 'products:list:'.md5(json_encode($filters).':'.$perPage.':'.$page);
        $ttl = config('high_performance.cache.product_ttl', 300);

        $cached = $this->store()->get($key);
        if (is_array($cached)) {
            return $this->paginatorFromPayload($cached);
        }

        $paginator = Product::filters($filters)
            ->where('is_active', true)
            ->with('category')
            ->paginate($perPage);

        $this->store()->put($key, $this->paginatorToPayload($paginator), $ttl);

        return $paginator;
    }

    public function findActive(int $id): Product
    {
        $key = "products:show:{$id}";
        $ttl = config('high_performance.cache.product_ttl', 300);

        $cached = $this->store()->get($key);
        if (is_array($cached)) {
            return (new Product)->newFromBuilder($cached)->loadMissing('category');
        }

        $product = Product::with('category')->where('is_active', true)->findOrFail($id);
        $this->store()->put($key, $product->getAttributes(), $ttl);

        return $product;
    }

    public function flush(?int $productId = null): void
    {
        if ($productId) {
            $this->store()->forget("products:show:{$productId}");
        }
    }

    /** @return array{items: list<array<string, mixed>>, total: int, per_page: int, current_page: int}> */
    private function paginatorToPayload(LengthAwarePaginator $paginator): array
    {
        return [
            'items' => collect($paginator->items())->map(fn (Product $p) => $p->getAttributes())->all(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
        ];
    }

    /** @param array{items: list<array<string, mixed>>, total: int, per_page: int, current_page: int}> $payload */
    private function paginatorFromPayload(array $payload): LengthAwarePaginator
    {
        $items = collect($payload['items'])->map(
            fn (array $attrs) => (new Product)->newFromBuilder($attrs)
        );

        return new Paginator(
            $items,
            $payload['total'],
            $payload['per_page'],
            $payload['current_page'],
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }
}
