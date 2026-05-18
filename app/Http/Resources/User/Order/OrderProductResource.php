<?php

namespace App\Http\Resources\User\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,
            'purchase_price' => $this->purchase_price,
        ];
    }
}
