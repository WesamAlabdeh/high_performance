<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailySalesSnapshot extends BaseModel
{
    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'revenue' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
