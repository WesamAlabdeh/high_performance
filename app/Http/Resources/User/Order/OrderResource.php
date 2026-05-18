<?php

namespace App\Http\Resources\User\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_status' => $this->order_status,
            'payment_status' => $this->payment_status,
            'payment_reference' => $this->payment_reference,
            'paid_at' => $this->paid_at,
            'products_cost' => $this->products_cost,
            'total' => $this->total,
            'user_notes' => $this->user_notes,
            'products' => OrderProductResource::collection($this->whenLoaded('orderProducts')),
            'invoice' => $this->whenLoaded('invoice'),
            'created_at' => $this->created_at,
        ];
    }
}
