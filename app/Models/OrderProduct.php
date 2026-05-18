<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderProduct extends BaseModel
{
    protected function casts(): array
    {
        return ['purchase_price' => 'decimal:2'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
