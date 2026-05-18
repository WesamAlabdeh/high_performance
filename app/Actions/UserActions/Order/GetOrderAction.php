<?php

namespace App\Actions\UserActions\Order;

use App\Actions\Base\BaseAction;
use App\Http\Resources\User\Order\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;

class GetOrderAction extends BaseAction
{
    public function handle(int $userId): mixed
    {
        return Order::with(['orderProducts', 'invoice'])
            ->where('user_id', $userId)
            ->latest()
            ->paginate();
    }

    public function asController(ActionRequest $request): mixed
    {
        return $this->handle($request->user()->id);
    }

    public function jsonResponse(mixed $orders): JsonResponse
    {
        return $this->success(OrderResource::collection($orders));
    }

    public function authorize(): bool
    {
        return true;
    }
}
