<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class PrepareSubmissionCommand extends Command
{
    protected $signature = 'submission:prepare {--skip-benchmarks : Skip stress/bottleneck commands}';

    protected $description = 'Collect outputs for submission/ folder (reports, API samples, race demo)';

    public function handle(): int
    {
        $outputs = base_path('submission/sources/outputs');
        File::ensureDirectoryExists($outputs);
        File::ensureDirectoryExists(base_path('submission/pdf'));

        $this->info('Generating race condition demo output...');
        if (! Product::query()->exists()) {
            $this->warn('Database empty — run: php artisan migrate --seed');
        } else {
            Artisan::call('concurrency:race-demo', ['product_id' => 1, '--attempts' => 30]);
            File::put($outputs.'/race-demo.txt', $this->formatHeader('concurrency:race-demo').Artisan::output());

            $this->info('Generating optimistic locking demo output...');
            Artisan::call('concurrency:optimistic-demo', ['product_id' => 1, '--attempts' => 20]);
            File::put($outputs.'/optimistic-demo.txt', $this->formatHeader('concurrency:optimistic-demo').Artisan::output());
        }

        if (! $this->option('skip-benchmarks')) {
            $this->warn('Benchmarks require Octane running — use --skip-benchmarks if server is offline.');
            Artisan::call('benchmark:bottlenecks');
            File::copy(
                base_path('docs/reports/04-benchmark/bottlenecks-report.json'),
                $outputs.'/bottlenecks-report.json'
            );
        }

        $this->info('Copying architecture report to submission/pdf/ ...');
        $html = base_path('docs/ARCHITECTURE_REPORT.html');
        if (File::exists($html)) {
            File::copy($html, base_path('submission/pdf/ARCHITECTURE_REPORT.html'));
            $this->line('Print ARCHITECTURE_REPORT.html to PDF from your browser (Cmd+P).');
        }

        $this->info('Submission artifacts ready under submission/');

        return self::SUCCESS;
    }

    private function formatHeader(string $command): string
    {
        return "Command: php artisan {$command}\nDate: ".now()->toDateString()."\n\n";
    }
}
