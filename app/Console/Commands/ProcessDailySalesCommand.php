<?php

namespace App\Console\Commands;

use App\Jobs\ProcessDailySalesBatchJob;
use App\Models\OrderProduct;
use Carbon\Carbon;
use Illuminate\Bus\Batch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class ProcessDailySalesCommand extends Command
{
    protected $signature = 'sales:process-daily {date? : Y-m-d}';

    protected $description = 'Process daily sales snapshots in background chunks';

    public function handle(): int
    {
        $date = $this->argument('date') ?? Carbon::yesterday()->toDateString();
        $chunk = config('high_performance.batch.chunk_size');

        $productCount = OrderProduct::query()
            ->join('orders', 'orders.id', '=', 'order_products.order_id')
            ->whereDate('orders.created_at', $date)
            ->distinct('order_products.product_id')
            ->count('order_products.product_id');

        $jobs = [];
        for ($offset = 0; $offset < $productCount; $offset += $chunk) {
            $jobs[] = new ProcessDailySalesBatchJob($date, $offset, $chunk);
        }

        if ($jobs === []) {
            $this->info('No sales data for '.$date);

            return self::SUCCESS;
        }

        Bus::batch($jobs)
            ->name('daily-sales-'.$date)
            ->onQueue(config('high_performance.queues.batches'))
            ->dispatch();

        $this->info('Dispatched '.count($jobs).' batch chunk(s) for '.$date);

        return self::SUCCESS;
    }
}
