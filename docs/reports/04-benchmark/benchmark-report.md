# تقرير Benchmark و Bottleneck Analysis

## الملفات
- `bottlenecks-report.json` — تحليل تلقائي للاختناقات
- `metrics-snapshot.json` — لقطة metrics من التطبيق

## الاختناقات الشائعة وحلولها
| الاختناق | الحل المطبق |
|----------|-------------|
| HTTP بطيء | Laravel Octane + Swoole |
| قراءة منتجات متكررة | ProductCacheService |
| دفع بطيء | Queue للفواتير والإشعارات |
| ضغط عالي | Capacity middleware + Circuit breaker |

## إعادة التوليد
```bash
php artisan benchmark:bottlenecks
```
