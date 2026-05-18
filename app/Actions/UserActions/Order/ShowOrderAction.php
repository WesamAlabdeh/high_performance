<?php

namespace App\Actions\UserActions\Order;

use App\Actions\Base\BaseAction;
use App\Http\Resources\User\Order\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;

class ShowOrderAction extends BaseAction
{
    public function handle(int $userId, int $orderId): Order
    {
        return Order::with(['orderProducts', 'invoice', 'notifications'])
            ->where('user_id', $userId)
            ->findOrFail($orderId);
    }

    public function asController(ActionRequest $request, int $id): Order
    {
        return $this->handle($request->user()->id, $id);
    }

    public function jsonResponse(Order $order): JsonResponse
    {
        return $this->success(new OrderResource($order));
    }

    public function authorize(): bool
    {
        return true;
    }
}
