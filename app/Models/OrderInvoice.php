<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderInvoice extends BaseModel
{
    protected function casts(): array
    {
        return ['generated_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
