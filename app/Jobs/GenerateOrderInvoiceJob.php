<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Requirement 3: Async queue — invoice generation off the HTTP request path.
 */
class GenerateOrderInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $orderId)
    {
        $this->onQueue(config('high_performance.queues.invoices'));
    }

    public function handle(): void
    {
        $order = Order::with('orderProducts.product')->findOrFail($this->orderId);

        $invoiceNumber = 'INV-'.str_pad((string) $order->id, 8, '0', STR_PAD_LEFT);

        OrderInvoice::updateOrCreate(
            ['order_id' => $order->id],
            [
                'invoice_number' => $invoiceNumber,
                'status' => 'generated',
                'file_path' => 'invoices/'.$invoiceNumber.'.json',
                'generated_at' => now(),
            ]
        );

        $payload = [
            'order_id' => $order->id,
            'total' => $order->total,
            'lines' => $order->orderProducts->map(fn ($line) => [
                'product' => $line->product?->name,
                'qty' => $line->quantity,
                'price' => $line->purchase_price,
            ]),
        ];

        $relativePath = 'invoices/'.$invoiceNumber.'.json';
        $path = storage_path('app/'.$relativePath);
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
