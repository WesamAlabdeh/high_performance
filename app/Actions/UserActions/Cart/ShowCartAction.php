<?php

namespace App\Actions\UserActions\Cart;

use App\Actions\Base\BaseAction;
use App\Http\Resources\User\Cart\CartResource;
use App\Models\Cart;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;

class ShowCartAction extends BaseAction
{
    public function handle(int $userId): Cart
    {
        return Cart::with(['cartProducts.product'])
            ->where('user_id', $userId)
            ->firstOrFail();
    }

    public function asController(ActionRequest $request): Cart
    {
        return $this->handle($request->user()->id);
    }

    public function jsonResponse(Cart $cart): JsonResponse
    {
        return $this->success(new CartResource($cart));
    }

    public function authorize(): bool
    {
        return true;
    }
}
