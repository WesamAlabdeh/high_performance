<?php

namespace App\Support\Queue;

use App\Enums\QueueName;
use Illuminate\Foundation\Bus\PendingDispatch;

final class QueueDispatcher
{
    /**
     * @param  object  $job
     */
    public static function dispatch(object $job, QueueName $queue): PendingDispatch
    {
        return dispatch($job)->onQueue($queue->value);
    }
}
