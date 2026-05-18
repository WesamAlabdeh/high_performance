# مخرجات التسليم — كل التقارير داخل المشروع

**المجلد الرئيسي:** [`reports/`](./reports/)

| الملف | الغرض |
|-------|--------|
| [`reports/00-MASTER-REPORT.md`](./reports/00-MASTER-REPORT.md) | التقرير الرئيسي — ابدأ من هنا |
| [`reports/REQUIREMENTS-CHECKLIST.md`](./reports/REQUIREMENTS-CHECKLIST.md) | المتطلبات العشرة + AOP |
| [`reports/01-architecture/`](./reports/01-architecture/) | معمارية + `architecture.html` → PDF |
| [`reports/02-race-condition/`](./reports/02-race-condition/) | Race condition قبل/بعد |
| [`reports/03-stress-test/`](./reports/03-stress-test/) | Stress 100 users |
| [`reports/04-benchmark/`](./reports/04-benchmark/) | Benchmark + bottlenecks |
| [`reports/05-aop/`](./reports/05-aop/) | تقرير AOP |
| [`reports/06-grafana/`](./reports/06-grafana/) | لقطات Grafana |

## توليد / تحديث كل التقارير

```bash
composer reports
# أو مع stress test (السيرفر لازم يكون شغال):
composer reports:full
```

## Postman

- [`HighPerformance-User-APIs.postman_collection.json`](./HighPerformance-User-APIs.postman_collection.json)
- [`HighPerformance-Admin-APIs.postman_collection.json`](./HighPerformance-Admin-APIs.postman_collection.json)
