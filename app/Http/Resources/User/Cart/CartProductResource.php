<?php

namespace App\Http\Resources\User\Cart;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'product' => $this->whenLoaded('product'),
        ];
    }
}
