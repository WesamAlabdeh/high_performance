<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends BaseModel
{
    protected static array $filtersArray = [
        'is_active' => 'equal',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
