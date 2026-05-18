<?php

namespace App\Actions\UserActions\Order;

use App\Actions\Base\BaseAction;
use App\Enums\OrderStatusEnum;
use App\Enums\QueueName;
use App\Exceptions\Errors;
use App\Http\Resources\User\Order\OrderResource;
use App\Jobs\GenerateOrderInvoiceJob;
use App\Jobs\SendOrderNotificationJob;
use App\Models\Order;
use App\Models\OrderNotification;
use App\Models\User;
use App\Services\Concurrency\InventoryService;
use App\Services\Payment\SimulatedPaymentService;
use App\Support\Metrics\MetricsRegistry;
use App\Support\Queue\QueueDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class CreateOrderAction extends BaseAction
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly SimulatedPaymentService $payment,
    ) {}

    public function handle(array $data, int $userId): Order
    {
        $order = DB::transaction(function () use ($data, $userId) {
            $user = User::with(['cart.cartProducts.product'])->lockForUpdate()->findOrFail($userId);
            $cart = $user->cart;

            if (! $cart || $cart->cartProducts->isEmpty()) {
                Errors::InvalidOperation('Cart is empty', 'empty cart');
            }

            $total = (float) $cart->total_price;

            if ((float) $user->balance < $total) {
                MetricsRegistry::incrementTracked('order.checkout.insufficient_balance');
                Errors::InvalidOperation('Insufficient wallet balance', 'balance check failed');
            }

            $lines = $cart->cartProducts
                ->mapWithKeys(fn ($line) => [$line->product_id => (int) $line->quantity])
                ->all();

            $this->inventory->reserveStock($lines);

            $order = Order::create([
                'user_id' => $userId,
                'order_status' => OrderStatusEnum::PENDING->value,
                'payment_status' => 'processing',
                'products_cost' => $total,
                'total' => $total,
                'user_notes' => $data['user_notes'] ?? null,
            ]);

            foreach ($cart->cartProducts as $line) {
                $order->orderProducts()->create([
                    'product_id' => $line->product_id,
                    'quantity' => $line->quantity,
                    'purchase_price' => round((float) $line->unit_price * (int) $line->quantity, 2),
                ]);
            }

            $this->payment->charge($user, $order);

            $cart->cartProducts()->delete();
            $cart->update(['total_price' => 0]);

            MetricsRegistry::incrementTracked('order.checkout.success');

            return $order->load(['orderProducts', 'payments']);
        });

        $notification = OrderNotification::create([
            'order_id' => $order->id,
            'user_id' => $userId,
            'channel' => 'database',
            'status' => 'pending',
            'payload' => json_encode(['message' => 'Order #'.$order->id.' confirmed']),
        ]);

        QueueDispatcher::dispatch(new GenerateOrderInvoiceJob($order->id), QueueName::Invoices);
        QueueDispatcher::dispatch(new SendOrderNotificationJob($notification->id), QueueName::Notifications);

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
        return $this->success(new OrderResource($order), 'Order placed with simulated payment.', 201);
    }

    public function authorize(): bool
    {
        return true;
    }
}
