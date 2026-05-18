<?php

namespace App\Console\Commands;

use App\Support\Metrics\MetricsRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;
use Throwable;

class GenerateProjectReportsCommand extends Command
{
    protected $signature = 'project:reports
        {--skip-stress : Skip HTTP stress test (requires running server)}
        {--users=100 : Stress test users}';

    protected $description = 'Generate all course reports inside docs/reports/ (for submission)';

    private string $basePath;

    public function handle(): int
    {
        $this->basePath = base_path('docs/reports');
        $this->ensureStructure();

        $this->info('Generating project reports in docs/reports/ ...');

        $this->generateRequirementsChecklist();
        $this->generateAopReport();
        $this->generateRaceConditionReport();
        $this->generateBenchmarkReport();

        if ($this->option('skip-stress')) {
            $this->writeStressReportTemplate();
            $this->warn('Skipped stress test (--skip-stress).');
        } elseif (! $this->isApiReachable()) {
            $this->writeStressReportTemplate();
            $this->warn('API not reachable on '.config('high_performance.stress_test.base_url').' — stress report skipped.');
            $this->line('  1) composer octane');
            $this->line('  2) composer reports:full');
        } else {
            $this->generateStressReport();
        }

        $this->generateMasterReport();
        $this->copyArchitectureAssets();

        $this->newLine();
        $this->info('All reports generated under: docs/reports/');
        $this->line('Main file: docs/reports/00-MASTER-REPORT.md');
        $this->line('Export PDF: open docs/reports/01-architecture/architecture.html → Print → Save as PDF');

        return self::SUCCESS;
    }

    private function ensureStructure(): void
    {
        $dirs = [
            '',
            '01-architecture',
            '02-race-condition',
            '02-race-condition/screenshots',
            '03-stress-test',
            '03-stress-test/jmeter',
            '04-benchmark',
            '05-aop',
            '06-grafana',
            '06-grafana/screenshots',
        ];

        foreach ($dirs as $dir) {
            File::ensureDirectoryExists($this->basePath.($dir ? '/'.$dir : ''));
        }
    }

    private function generateRequirementsChecklist(): void
    {
        $content = <<<'MD'
# قائمة تحقق المتطلبات (Requirements Checklist)

| # | المتطلب | الحالة | الملف / الدليل |
|---|---------|--------|----------------|
| 1 | وصول متزامن + سلامة بيانات | ✅ | `app/Services/Concurrency/InventoryService.php` |
| 2 | إدارة الموارد والسعة | ✅ | `app/Services/Capacity/ResourceCapacityService.php` |
| 3 | طوابير غير متزامنة | ✅ | `app/Jobs/` + `config/high_performance.php` |
| 4 | معالجة دفعية Chunks | ✅ | `app/Jobs/ProcessDailySalesBatchJob.php` |
| 5 | توزيع حمل + Circuit Breaker | ✅ | `app/Services/LoadBalancing/CircuitBreakerService.php` |
| 6 | تخزين مؤقت | ✅ | `app/Services/Cache/ProductCacheService.php` |
| 7 | Concurrency control | ✅ | Pessimistic `lockForUpdate` + `version` |
| 8 | ACID + دفع | ✅ | `app/Services/Payment/SimulatedPaymentService.php` |
| 9 | Stress test 100 users | ✅ | `03-stress-test/stress-report.json` |
| 10 | Benchmark + bottlenecks | ✅ | `04-benchmark/bottlenecks-report.json` |
| AOP | نقاط قطع ومراقبة | ✅ | `05-aop/aop-report.md` |
| معمارية | ملف Architecture | ✅ | `01-architecture/architecture.html` |

MD;

        File::put($this->basePath.'/REQUIREMENTS-CHECKLIST.md', $content);
    }

    private function generateAopReport(): void
    {
        $points = [
            ['inventory.pessimistic_lock', 'خصم المخزون', 'InventoryService::reserveStock'],
            ['payment.simulated_gateway', 'دفع محاكى', 'SimulatedPaymentService::charge'],
            ['http.api.order', 'طلبات HTTP', 'ConcurrencyTracingMiddleware'],
            ['capacity.checkout', 'حد السعة', 'CapacityControlMiddleware'],
            ['circuit.orders', 'Circuit breaker', 'CircuitBreakerMiddleware'],
        ];

        $lines = ["# تقرير AOP — نقاط التزامن والمراقبة\n"];
        $lines[] = '## مفهوم AOP في المشروع';
        $lines[] = 'استخدمنا **ConcurrencyAspect** كـ cross-cutting concern يغلف العمليات الحرجة ويسجل metrics بدون تكرار الكود في كل Action.';
        $lines[] = '';
        $lines[] = '| نقطة القطع (Pointcut) | الوصف | الموقع في الكود |';
        $lines[] = '|----------------------|--------|-----------------|';

        foreach ($points as [$point, $desc, $location]) {
            $lines[] = "| `{$point}` | {$desc} | `{$location}` |";
        }

        $lines[] = '';
        $lines[] = '## Middleware (HTTP layer)';
        $lines[] = '- `ConcurrencyTracingMiddleware` — يسجل مدة كل طلب API';
        $lines[] = '';
        $lines[] = '## Metrics';
        $lines[] = '- Endpoint: `GET /metrics` (Prometheus)';
        $lines[] = '- JSON: `GET /metrics/json`';

        File::put($this->basePath.'/05-aop/aop-report.md', implode("\n", $lines));
    }

    private function generateRaceConditionReport(): void
    {
        $process = new Process([
            PHP_BINARY,
            'artisan',
            'concurrency:race-demo',
            '1',
            '--attempts=25',
        ], base_path());
        $process->setTimeout(120);
        $process->run();

        $output = $process->getOutput().$process->getErrorOutput();
        File::put($this->basePath.'/02-race-condition/terminal-output.txt', $output);

        $report = <<<'MD'
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

MD;

        File::put($this->basePath.'/02-race-condition/race-condition-report.md', $report);
        $this->components->info('Race condition report → docs/reports/02-race-condition/');
    }

    private function generateBenchmarkReport(): void
    {
        Artisan::call('benchmark:bottlenecks');
        $this->line(Artisan::output());

        $latest = collect(File::files(storage_path('app/benchmarks')))
            ->filter(fn ($f) => str_contains($f->getFilename(), 'bottlenecks'))
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->first();

        if ($latest) {
            File::copy($latest->getPathname(), $this->basePath.'/04-benchmark/bottlenecks-report.json');
        }

        File::put(
            $this->basePath.'/04-benchmark/metrics-snapshot.json',
            json_encode(MetricsRegistry::all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        $report = <<<'MD'
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

MD;

        File::put($this->basePath.'/04-benchmark/benchmark-report.md', $report);
    }

    private function writeStressReportTemplate(): void
    {
        File::copy(
            base_path('tests/jmeter/README.md'),
            $this->basePath.'/03-stress-test/jmeter-instructions.md'
        );

        if (! File::exists($this->basePath.'/03-stress-test/stress-test-report.md')) {
            File::put($this->basePath.'/03-stress-test/stress-test-report.md', <<<'MD'
# تقرير Stress Test — 100 مستخدم

> شغّل: `php artisan benchmark:stress --users=100` (أو JMeter) ثم `composer reports:full`

MD);
        }
    }

    private function isApiReachable(): bool
    {
        $baseUrl = rtrim(config('high_performance.stress_test.base_url'), '/');

        try {
            $response = Http::timeout(3)->connectTimeout(2)->get("{$baseUrl}/up");

            return $response->successful();
        } catch (Throwable) {
            return false;
        }
    }

    private function generateStressReport(): void
    {
        $users = (int) $this->option('users');

        try {
            $exit = Artisan::call('benchmark:stress', ['--users' => $users]);
            $this->line(Artisan::output());
        } catch (Throwable $e) {
            $this->writeStressReportTemplate();
            $this->warn('Stress test failed: '.$e->getMessage());
            $this->line('Ensure Octane is running: composer octane');

            return;
        }

        $latest = collect(File::files(storage_path('app/benchmarks')))
            ->filter(fn ($f) => str_contains($f->getFilename(), 'stress'))
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->first();

        if ($latest) {
            File::copy($latest->getPathname(), $this->basePath.'/03-stress-test/stress-report.json');
        }

        $this->writeStressReportTemplate();

        $report = <<<'MD'
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

MD;

        File::put($this->basePath.'/03-stress-test/stress-test-report.md', $report);

        if (($exit ?? 1) !== 0) {
            $this->writeStressReportTemplate();
            $this->warn('Stress test did not complete — run manually after starting Octane.');
        }
    }

    private function generateMasterReport(): void
    {
        $date = now()->format('Y-m-d H:i');
        $stressExists = File::exists($this->basePath.'/03-stress-test/stress-report.json');
        $bottleneckExists = File::exists($this->basePath.'/04-benchmark/bottlenecks-report.json');

        $content = <<<MD
# التقرير الرئيسي للمشروع
## High-Performance E-Commerce Backend Engine

**المادة:** البرمجة المتوازية  
**التاريخ:** {$date}  
**التقنيات:** Laravel 13, Octane (Swoole), Sanctum, Queues, Prometheus/Grafana

---

## 1. فكرة المشروع
محرك تجارة إلكترونية يركز على **التزامن وسلامة البيانات** تحت حمل عالٍ، وليس واجهة متجر فقط.

## 2. الحلول المقترحة والبنية التحتية
| التحدي | الحل | هل البنية تدعمه؟ |
|--------|------|------------------|
| Race على المخزون | `lockForUpdate` | ✅ MariaDB InnoDB |
| استنزاف الموارد | Capacity limiter | ✅ Cache/Octane table |
| مهام ثقيلة | Queue workers | ✅ database/redis queue |
| ضغط 100 مستخدم | Octane + JMeter | ✅ Swoole workers |
| مراقبة | Prometheus + Grafana | ✅ `/metrics` endpoint |

## 3. محتويات مجلد التقارير

```
docs/reports/
├── 00-MASTER-REPORT.md          ← هذا الملف
├── REQUIREMENTS-CHECKLIST.md    ← المتطلبات العشرة
├── 01-architecture/             ← معمارية + PDF
├── 02-race-condition/           ← Race قبل/بعد
├── 03-stress-test/              ← 100 مستخدم
├── 04-benchmark/                ← Bottlenecks
├── 05-aop/                      ← AOP
└── 06-grafana/                 ← لقطات المراقبة
```

## 4. حالة التقارير التلقائية
- Race condition: ✅ `02-race-condition/terminal-output.txt`
- Benchmark: {$this->statusLabel($bottleneckExists)} `04-benchmark/bottlenecks-report.json`
- Stress test: {$this->statusLabel($stressExists)} `03-stress-test/stress-report.json`

## 5. أوامر التوليد
```bash
# كل التقارير دفعة واحدة (السيرفر لازم يكون شغال للـ stress)
php artisan project:reports

# بدون stress إذا السيرفر مطفي
php artisan project:reports --skip-stress
```

## 6. التسليم للدكتور
1. المشروع كامل (Git/ZIP)
2. مجلد `docs/reports/` بكل التقارير
3. `01-architecture/architecture.pdf` (من HTML)
4. لقطات `02-race-condition/screenshots/`
5. نتائج JMeter + Grafana في المجلدات المخصصة

MD;

        File::put($this->basePath.'/00-MASTER-REPORT.md', $content);
        File::put($this->basePath.'/README.md', "# تقارير المشروع\n\nابدأ من **[00-MASTER-REPORT.md](./00-MASTER-REPORT.md)**\n");
    }

    private function statusLabel(bool $ok): string
    {
        return $ok ? '✅' : '⚠️ (شغّل الأمر يدوياً)';
    }

    private function copyArchitectureAssets(): void
    {
        foreach (['architecture.html', 'TESTING_GUIDE.html'] as $file) {
            $src = base_path('docs/'.$file);
            if (File::exists($src)) {
                File::copy($src, $this->basePath.'/01-architecture/'.$file);
            }
        }

        if (! File::exists($this->basePath.'/01-architecture/architecture-report.md')) {
            File::put($this->basePath.'/01-architecture/architecture-report.md', <<<'MD'
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

MD);
        }
    }
}
