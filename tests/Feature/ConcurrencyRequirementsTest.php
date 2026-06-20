<?php

namespace Tests\Feature;

use App\Exceptions\ApiException;
use App\Models\Cart;
use App\Models\CartProduct;
use App\Models\Product;
use App\Models\User;
use App\Services\Cache\ProductCacheService;
use App\Services\Capacity\ResourceCapacityService;
use App\Services\Concurrency\InventoryService;
use App\Services\Concurrency\OptimisticInventoryService;
use App\Services\LoadBalancing\CircuitBreakerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ConcurrencyRequirementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pessimistic_inventory_prevents_overselling(): void
    {
        $product = Product::factory()->create(['stock' => 5, 'version' => 0]);
        $service = app(InventoryService::class);

        $service->reserveStock([$product->id => 3]);
        $product->refresh();
        $this->assertSame(2, $product->stock);

        $this->expectException(ApiException::class);
        $service->reserveStock([$product->id => 5]);
    }

    public function test_optimistic_inventory_reserves_with_version_check(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'version' => 0]);
        $service = app(OptimisticInventoryService::class);

        $service->reserveStock([$product->id => 4]);
        $product->refresh();

        $this->assertSame(6, $product->stock);
        $this->assertSame(1, $product->version);
    }

    public function test_checkout_is_atomic_payment_stock_and_cart(): void
    {
        config(['high_performance.payment.simulation_delay_ms' => 0]);

        $user = User::factory()->create(['balance' => 500]);
        $cart = Cart::create(['user_id' => $user->id, 'total_price' => 0]);
        $product = Product::factory()->create(['price' => 100, 'stock' => 10, 'is_active' => true]);

        CartProduct::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 100,
        ]);
        $cart->recalculateTotal();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/order', ['user_notes' => 'acid test']);

        $response->assertCreated();
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'payment_status' => 'paid',
        ]);
        $this->assertDatabaseHas('payments', ['user_id' => $user->id, 'status' => 'completed']);
        $this->assertSame(0, $cart->fresh()->cartProducts()->count());
        $this->assertSame(8, $product->fresh()->stock);
        $this->assertEquals(300.0, (float) $user->fresh()->balance);
    }

    public function test_capacity_middleware_limits_concurrent_checkouts(): void
    {
        config(['high_performance.capacity.max_concurrent_checkouts' => 1]);
        Cache::store('database')->flush();

        $user = User::factory()->create(['balance' => 10000]);
        Cart::create(['user_id' => $user->id, 'total_price' => 0]);
        Product::factory()->create(['id' => 1, 'price' => 10, 'stock' => 100, 'is_active' => true]);

        $capacity = app(ResourceCapacityService::class);
        $token = $capacity->acquire('checkout');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/cart', ['product_id' => 1, 'quantity' => 1])
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/order')
            ->assertStatus(503)
            ->assertJsonPath('error', 'CAPACITY_EXCEEDED');

        $capacity->release('checkout', $token);
    }

    public function test_circuit_breaker_opens_after_failures(): void
    {
        Cache::store('database')->flush();
        $breaker = app(CircuitBreakerService::class);

        for ($i = 0; $i < 5; $i++) {
            $breaker->recordFailure('orders');
        }

        $this->expectException(ApiException::class);
        $breaker->guard('orders');
    }

    public function test_product_cache_service_returns_cached_pages(): void
    {
        config(['high_performance.cache.product_store' => 'array']);
        Product::factory()->count(2)->create(['is_active' => true]);

        $service = app(ProductCacheService::class);
        $page1 = $service->paginateActive([], 10);
        $page2 = $service->paginateActive([], 10);

        $this->assertCount(2, $page1->items());
        $this->assertSame($page1->total(), $page2->total());
    }
}
