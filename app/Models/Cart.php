<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends BaseModel
{
    protected function casts(): array
    {
        return ['total_price' => 'decimal:2'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cartProducts(): HasMany
    {
        return $this->hasMany(CartProduct::class);
    }

    public function recalculateTotal(): void
    {
        $total = $this->cartProducts()
            ->get()
            ->sum(fn (CartProduct $line) => (float) $line->unit_price * (int) $line->quantity);

        $this->update(['total_price' => round($total, 2)]);
    }
}
