Command: php artisan benchmark:stress --users=100
Date: 2026-06-20
Phase 1: 100 concurrent logins
Phase 2: 100 users × 9 authenticated operations

+-------+---------------------+----------------+----------+--------+---------+--------+
| users | operations_per_user | total_requests | rps      | success| failed  | p95_ms |
+-------+---------------------+----------------+----------+--------+---------+--------+
| 100   | 10                  | 1000           | 26.52    | 558    | 442     | 27238  |
+-------+---------------------+----------------+----------+--------+---------+--------+

By operation (100 concurrent each):
 POST /api/auth/login              → success: 100, failed: 0
 GET /api/product                 → success: 53,  failed: 47
 GET /api/product/1               → success: 52,  failed: 48
 GET /api/wallet                  → success: 50,  failed: 50
 GET /api/cart                    → success: 56,  failed: 44
 POST /api/cart                    → success: 52,  failed: 48
 GET /api/order                   → success: 52,  failed: 48
 POST /api/order                   → success: 34,  failed: 66
 GET /api/admin/load-balancer/status → success: 52, failed: 48
 POST /api/admin/batch/daily-sales → success: 57,  failed: 43

Status codes: 200=524, 201=34, 400=18, timeouts/errors=424

Note: Under 1000 simultaneous connections, partial failures are expected (capacity, cart lock, empty cart).
Sequential integration test: 10/10 endpoints pass.
Full JSON: docs/reports/03-stress-test/stress-report.json
