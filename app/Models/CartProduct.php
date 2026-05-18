<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartProduct extends BaseModel
{
    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2'];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
