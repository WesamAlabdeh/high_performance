# مشروع مادة البرمجة المتوازية

Backend تجارة إلكترونية (Laravel) — التركيز على التزامن تحت ضغط عدد كبير من المستخدمين.

**تشغيل المشروع:**

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
composer octane
```

طابور المهام (نافذة ثانية):

```bash
php artisan queue:work --queue=invoices,notifications,batches,default
```

**حساب تجريبي:** `demo@highperformance.test` / `password`  
**Postman:** مجلد `postman/`

---

## الخطوة 0 — جهّز المشروع

```bash
php artisan migrate --seed
composer octane
```

نافذة ثانية: `php artisan queue:work --queue=invoices,notifications,batches,default`

تأكد المخزون فيه كمية:

```bash
php artisan tinker --execute="App\Models\Product::where('id',1)->update(['stock'=>50]);"
```

---

## أوامر مفيدة


| الأمر                                      | شو بيعمل             |
| ------------------------------------------ | -------------------- |
| `composer octane`                          | تشغيل السيرفر        |
| `php artisan concurrency:race-demo`        | تجربة race           |
| `php artisan benchmark:stress --users=100` | ضغط HTTP             |
| `composer monitoring`                      | Prometheus + Grafana |


---

## هيكل المشروع (للمراجعة)

```
app/Services/Concurrency/InventoryService.php   ← قفل مخزون
app/Services/Payment/SimulatedPaymentService.php ← دفع + رصيد
app/Jobs/                                       ← طوابير
app/Aspects/ConcurrencyAspect.php               ← مراقبة (AOP)
routes/api.php
postman/
submission/        
```

