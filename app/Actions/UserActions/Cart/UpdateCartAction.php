<?php

namespace App\Actions\UserActions\Cart;

use App\Actions\Base\BaseAction;
use App\Exceptions\Errors;
use App\Http\Resources\User\Cart\CartResource;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class UpdateCartAction extends BaseAction
{
    public function handle(array $data, int $userId): Cart
    {
        return DB::transaction(function () use ($data, $userId) {
            $cart = Cart::where('user_id', $userId)->firstOrFail();
            $product = Product::where('is_active', true)->findOrFail($data['product_id']);

            if ((int) $data['quantity'] > $product->stock) {
                Errors::InvalidOperation('Quantity exceeds available stock', 'stock validation');
            }

            if ((int) $data['quantity'] === 0) {
                $cart->cartProducts()->where('product_id', $product->id)->delete();
            } else {
                $cart->cartProducts()->updateOrCreate(
                    ['product_id' => $product->id],
                    [
                        'quantity' => $data['quantity'],
                        'unit_price' => $product->price,
                    ]
                );
            }

            $cart->recalculateTotal();

            return $cart->load('cartProducts.product');
        });
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:0',
        ];
    }

    public function asController(ActionRequest $request): Cart
    {
        return $this->handle($request->validated(), $request->user()->id);
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
