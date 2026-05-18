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

## التسليم: صور + PDF

كل شيء التسليم يحطه هون:

```
submission/
  images/     ← صور PNG (الخطوات تحت)
  pdf/        ← architecture.pdf
  sources/    ← architecture-for-print.html (لطباعة PDF)
```

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

## الصورة 1 — Race بدون قفل

```bash
php artisan concurrency:race-demo 1 --attempts=30
```

خذ screenshot للتيرمنال — لازم يبان سطر مثل:

`UNSAFE final stock: 7` (رقم مو 0)

احفظ: `submission/images/01-race-bila-qfl.png`

---

## الصورة 2 — Race مع lockForUpdate

نفس الأمر — خذ screenshot لسطر:

`SAFE final stock: 0`

احفظ: `submission/images/02-race-ma3-qfl.png`

---

## الصورة 3 — Postman (تسجيل دخول + رصيد)

1. استورد `postman/HighPerformance-User-APIs.postman_collection.json`
2. شغّل **Login (Demo User)**
3. screenshot للرد (فيها token)
4. احفظ: `submission/images/03-postman-login.png`
5. شغّل **GET wallet** — screenshot للرصيد
6. احفظ: `submission/images/04-postman-wallet.png`

---

## الصورة 4 — Postman (طلب كامل)

بالترتيب:

1. **Get Products**
2. **Update Cart** — `product_id: 1`, `quantity: 2`
3. **Create Order**

Screenshot لرد الطلب — لازم يبان `payment_status: paid`

احفظ: `submission/images/05-postman-order-paid.png`

Screenshot لنافذة الـ queue إذا طلع `GenerateOrderInvoiceJob DONE`:

`submission/images/06-queue-jobs.png`

---

## الصورة 5 — Stress test (100 مستخدم)

### طريقة JMeter (اللي ذكرها الأستاذ)

1. نزّل JMeter: https://jmeter.apache.org/download_jmeter.cgi
2. Thread Group: 100 users, ramp-up 10s
3. طلبات: Login → GET product (مع Bearer token)
4. شغّل الاختبار والـ Octane شغال
5. screenshot لـ **Aggregate Report**

احفظ: `submission/images/07-jmeter-100-users.png`

تفاصيل أكثر: `tests/jmeter/README.md`

### بديل سريع (بدون JMeter)

```bash
php artisan benchmark:stress --users=100
```

(Octane لازم يكون شغال.) إذا طلع فيه أخطاء كثيرة، اعتمد JMeter للتسليم.

---

## الصورة 6 — Grafana (موارد النظام)

```bash
composer monitoring
```

افتح http://localhost:3000 (admin / admin)

شغّل ضغط (JMeter أو `php artisan benchmark:stress --users=100`) وخذ screenshot للوحة.

احفظ: `submission/images/08-grafana-cpu-ram.png`

**بدون Docker:** Activity Monitor على الماك أثناء الضغط — screenshot يكفي إذا ما اشتغل Grafana.

---

## PDF المعمارية

1. افتح بالمتصفح: `submission/sources/architecture-for-print.html`
2. Cmd+P (طباعة)
3. Destination: **Save as PDF**
4. احفظ: `submission/pdf/architecture.pdf`

---

## PDF تقرير كامل (اختياري)

إذا بدك ملف واحد للتسليم:

1. افتح Word / Google Docs / Pages
2. رتّب بالترتيب:
   - صفحة عنوان + اسمك
   - architecture (أو ادمج architecture.pdf)
   - صورة 01 و 02 (race)
   - صور postman
   - jmeter
   - grafana
   - فقرة قصيرة: شو تعلمت
3. Export PDF → `submission/pdf/takrir-kamil.pdf`

---

## قائمة تحقق قبل ما تسلّم

```
submission/images/01-race-bila-qfl.png
submission/images/02-race-ma3-qfl.png
submission/images/03-postman-login.png
submission/images/04-postman-wallet.png
submission/images/05-postman-order-paid.png
submission/images/06-queue-jobs.png        (إن وجد)
submission/images/07-jmeter-100-users.png
submission/images/08-grafana-cpu-ram.png
submission/pdf/architecture.pdf
```

---

## أوامر مفيدة

| الأمر | شو بيعمل |
|--------|----------|
| `composer octane` | تشغيل السيرفر |
| `php artisan concurrency:race-demo` | تجربة race |
| `php artisan benchmark:stress --users=100` | ضغط HTTP |
| `composer monitoring` | Prometheus + Grafana |

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
