<?php

namespace App\Actions\UserActions\Order;

use App\Actions\Base\BaseAction;
use App\Enums\OrderStatusEnum;
use App\Exceptions\Errors;
use App\Http\Resources\User\Order\OrderResource;
use App\Jobs\GenerateOrderInvoiceJob;
use App\Jobs\SendOrderNotificationJob;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderNotification;
use App\Models\User;
use App\Services\Concurrency\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class CreateOrderAction extends BaseAction
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function handle(array $data, int $userId): Order
    {
        $order = DB::transaction(function () use ($data, $userId) {
            $user = User::with(['cart.cartProducts.product'])->findOrFail($userId);
            $cart = $user->cart;

            if (! $cart || $cart->cartProducts->isEmpty()) {
                Errors::InvalidOperation('Cart is empty', 'empty cart');
            }

            $lines = $cart->cartProducts
                ->mapWithKeys(fn ($line) => [$line->product_id => (int) $line->quantity])
                ->all();

            $this->inventory->reserveStock($lines);

            $order = Order::create([
                'user_id' => $userId,
                'order_status' => OrderStatusEnum::PENDING->value,
                'products_cost' => $cart->total_price,
                'total' => $cart->total_price,
                'user_notes' => $data['user_notes'] ?? null,
            ]);

            foreach ($cart->cartProducts as $line) {
                $order->orderProducts()->create([
                    'product_id' => $line->product_id,
                    'quantity' => $line->quantity,
                    'purchase_price' => round((float) $line->unit_price * (int) $line->quantity, 2),
                ]);
            }

            $cart->cartProducts()->delete();
            $cart->update(['total_price' => 0]);

            return $order->load('orderProducts');
        });

        $notification = OrderNotification::create([
            'order_id' => $order->id,
            'user_id' => $userId,
            'channel' => 'database',
            'status' => 'pending',
            'payload' => json_encode(['message' => 'Order #'.$order->id.' confirmed']),
        ]);

        GenerateOrderInvoiceJob::dispatch($order->id);
        SendOrderNotificationJob::dispatch($notification->id);

        return $order;
    }

    public function rules(): array
    {
        return [
            'user_notes' => 'nullable|string|max:500',
        ];
    }

    public function asController(ActionRequest $request): Order
    {
        return $this->handle($request->validated(), $request->user()->id);
    }

    public function jsonResponse(Order $order): JsonResponse
    {
        return $this->success(new OrderResource($order), 'Order placed. Invoice & notification queued.', 201);
    }

    public function authorize(): bool
    {
        return true;
    }
}
