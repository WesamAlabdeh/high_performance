<?php

namespace App\Jobs;

use App\Jobs\Concerns\ConfiguresQueueProfile;
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
    use ConfiguresQueueProfile, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public int $timeout;

    public int $backoff;

    public bool $failOnTimeout;

    public function __construct(public int $notificationId)
    {
        $this->configureQueueProfile('notification');
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
