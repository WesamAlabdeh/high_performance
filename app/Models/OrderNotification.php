<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderNotification extends BaseModel
{
    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
