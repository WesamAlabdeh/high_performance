# تقرير Race Condition — قبل وبعد الحل

## المشكلة (بدون lock)
عند تشغيل عدة عمليات خصم مخزون **بالتوازي** بدون `lockForUpdate`، يقرأ أكثر من worker نفس قيمة `stock` ويكتب فوق بعض — النتيجة مخزون خاطئ (بيع زائد).

## الحل (مع lock)
`Product::lockForUpdate()` داخل `DB::transaction` يضمن أن عملية واحدة فقط تعدّل الصف في كل لحظة.

## مخرجات التجربة
راجع الملف: `terminal-output.txt` في هذا المجلد.

## لقطات الشاشة (أضفها للتسليم)
ضع صورتين في `screenshots/`:
1. `screenshots/01-unsafe-race.png` — مخرجات UNSAFE
2. `screenshots/02-safe-lock.png` — مخرجات SAFE

## أمر إعادة التجربة
```bash
php artisan concurrency:race-demo 1 --attempts=30
```
