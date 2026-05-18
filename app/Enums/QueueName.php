<?php

namespace App\Enums;

enum QueueName: string
{
    case Default = 'default';
    case Invoices = 'invoices';
    case Notifications = 'notifications';
    case Batches = 'batches';
}
