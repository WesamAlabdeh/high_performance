<?php

namespace App\Http\Resources\User\Cart;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'total_price' => $this->total_price,
            'items' => CartProductResource::collection($this->whenLoaded('cartProducts')),
        ];
    }
}
