<?php

namespace App\Jobs;

use App\Models\OrderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Requirement 3: Async queue — notifications off the HTTP request path.
 */
class SendOrderNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $notificationId)
    {
        $this->onQueue(config('high_performance.queues.notifications'));
    }

    public function handle(): void
    {
        $notification = OrderNotification::findOrFail($this->notificationId);

        Log::info('Order notification dispatched', [
            'order_id' => $notification->order_id,
            'channel' => $notification->channel,
            'payload' => $notification->payload,
        ]);

        $notification->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }
}
