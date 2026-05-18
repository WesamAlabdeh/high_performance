# تقرير المعمارية (Architecture)

## التصدير PDF
افتح `architecture.html` في المتصفح → طباعة → حفظ باسم `architecture.pdf` في نفس المجلد.

## المكونات
- **Octane (Swoole):** عمال HTTP طويلو العمر
- **Actions:** طبقة API
- **Services:** منطق التزامن والدفع
- **Jobs:** فواتير، إشعارات، دفعات
- **Metrics:** Prometheus على `/metrics`

راجع `architecture.html` للمخطط الكامل.
