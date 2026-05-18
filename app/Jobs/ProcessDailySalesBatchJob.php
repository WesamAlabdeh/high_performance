<?php

namespace App\Jobs;

use App\Jobs\Concerns\ConfiguresQueueProfile;
use App\Models\DailySalesSnapshot;
use App\Models\OrderProduct;
use Carbon\Carbon;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Requirement 4: Batch processing — aggregates sales in chunks.
 */
class ProcessDailySalesBatchJob implements ShouldQueue
{
    use Batchable, ConfiguresQueueProfile, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public int $timeout;

    public int $backoff;

    public bool $failOnTimeout;

    public function __construct(
        public string $date,
        public int $offset,
        public int $limit
    ) {
        $this->configureQueueProfile('batch');
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $day = Carbon::parse($this->date)->toDateString();

        $rows = OrderProduct::query()
            ->select([
                'order_products.product_id',
                DB::raw('SUM(order_products.quantity) as units_sold'),
                DB::raw('SUM(order_products.purchase_price) as revenue'),
            ])
            ->join('orders', 'orders.id', '=', 'order_products.order_id')
            ->whereDate('orders.created_at', $day)
            ->where('orders.order_status', '!=', 'cancelled')
            ->groupBy('order_products.product_id')
            ->orderBy('order_products.product_id')
            ->offset($this->offset)
            ->limit($this->limit)
            ->get();

        foreach ($rows as $row) {
            DailySalesSnapshot::updateOrCreate(
                [
                    'snapshot_date' => $day,
                    'product_id' => $row->product_id,
                ],
                [
                    'units_sold' => (int) $row->units_sold,
                    'revenue' => round((float) $row->revenue, 2),
                ]
            );
        }
    }
}
