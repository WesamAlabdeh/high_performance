# مشروع مادة البرمجة المتوازية

Backend تجارة إلكترونية (Laravel + Octane) — تطبيق كامل لمتطلبات المادة العشرة.

## تشغيل سريع

```bash
composer install
cp .env.example .env
php artisan key:generate
docker compose up -d redis
php artisan migrate --seed
composer octane
```

نافذة ثانية:

```bash
php artisan queue:work --queue=invoices,notifications,batches,default
```

**حساب تجريبي:** `demo@highperformance.test` / `password`  
**Postman:** `postman/`  
**تقرير PDF:** افتح `docs/ARCHITECTURE_REPORT.html` → طباعة → حفظ PDF في `submission/pdf/`

---

## المتطلبات العشرة — أين في الكود؟

| # | المتطلب | الملف / الأمر |
|---|---------|---------------|
| 1 | Concurrent Access & Data Integrity | `InventoryService`, `concurrency:race-demo` |
| 2 | Resource Management | `ResourceCapacityService`, `CapacityControlMiddleware` |
| 3 | Async Queues | `GenerateOrderInvoiceJob`, `SendOrderNotificationJob` |
| 4 | Batch Processing | `ProcessDailySalesBatchJob`, `sales:process-daily` |
| 5 | Load Distribution | `LoadBalancerService`, `CircuitBreakerService` |
| 6 | Distributed Caching | `ProductCacheService` + Redis |
| 7 | Concurrency Control | Pessimistic + `OptimisticInventoryService` |
| 8 | ACID | `CreateOrderAction`, `SimulatedPaymentService` |
| 9 | Stress Testing | `benchmark:stress --users=100` |
| 10 | Benchmarking | `benchmark:bottlenecks`, `/metrics` |

---

## أوامر العرض العملي

```bash
php artisan concurrency:race-demo --attempts=30
php artisan concurrency:optimistic-demo --attempts=20
PAYMENT_SIMULATION_DELAY_MS=0 php artisan benchmark:stress --users=100 --checkout
php artisan benchmark:bottlenecks
php artisan submission:prepare
php artisan test
composer monitoring   # Prometheus :9090, Grafana :3000
```

---

## هيكل المشروع

```
app/Services/Concurrency/     ← مخزون (pessimistic + optimistic)
app/Services/Capacity/        ← حد checkout متزامن
app/Services/LoadBalancing/   ← circuit breaker + load balancer
app/Services/Cache/           ← كاش منتجات (Redis)
app/Aspects/ConcurrencyAspect.php  ← AOP
app/Jobs/                     ← طوابير
docs/ARCHITECTURE_REPORT.html ← تقرير للطباعة PDF
docs/TESTING_GUIDE.html       ← دليل الاختبار
submission/                   ← مخرجات التسليم
```

دليل تفصيلي: `docs/TESTING_GUIDE.html`
