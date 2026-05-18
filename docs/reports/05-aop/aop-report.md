# تقرير AOP — نقاط التزامن والمراقبة

## مفهوم AOP في المشروع
استخدمنا **ConcurrencyAspect** كـ cross-cutting concern يغلف العمليات الحرجة ويسجل metrics بدون تكرار الكود في كل Action.

| نقطة القطع (Pointcut) | الوصف | الموقع في الكود |
|----------------------|--------|-----------------|
| `inventory.pessimistic_lock` | خصم المخزون | `InventoryService::reserveStock` |
| `payment.simulated_gateway` | دفع محاكى | `SimulatedPaymentService::charge` |
| `http.api.order` | طلبات HTTP | `ConcurrencyTracingMiddleware` |
| `capacity.checkout` | حد السعة | `CapacityControlMiddleware` |
| `circuit.orders` | Circuit breaker | `CircuitBreakerMiddleware` |

## Middleware (HTTP layer)
- `ConcurrencyTracingMiddleware` — يسجل مدة كل طلب API

## Metrics
- Endpoint: `GET /metrics` (Prometheus)
- JSON: `GET /metrics/json`