# تقرير Stress Test — 100 مستخدم

## أدوات الاختبار
1. **Apache JMeter** (مطلوب من المادة) — `jmeter-instructions.md`
2. **Artisan** — `stress-report.json` في هذا المجلد

## شروط التشغيل
- Octane: `composer octane`
- Queue: `php artisan queue:work --queue=invoices,notifications,batches,default`
- للضغط: `PAYMENT_SIMULATION_DELAY_MS=0`

## نتائج JMeter
ضع ملف `.jtl` أو لقطة Aggregate Report في:
`03-stress-test/jmeter/results.jtl` (بعد تشغيل JMeter)

## Grafana
ضع لقطات CPU/RAM في `06-grafana/screenshots/`
