<?php

namespace App\Services\Payment;

use App\Aspects\ConcurrencyAspect;
use App\Exceptions\Errors;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Requirement 8: ACID — balance check + simulated bank delay inside one transaction.
 */
class SimulatedPaymentService
{
    public function charge(User $user, Order $order): Payment
    {
        return ConcurrencyAspect::around('payment.simulated_gateway', function () use ($user, $order) {
            $delayMs = (int) config('high_performance.payment.simulation_delay_ms', 1500);
            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }

            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ((float) $lockedUser->balance < (float) $order->total) {
                Errors::InvalidOperation(
                    'Insufficient wallet balance',
                    'balance lower than order total'
                );
            }

            $lockedUser->balance = round((float) $lockedUser->balance - (float) $order->total, 2);
            $lockedUser->version = (int) $lockedUser->version + 1;
            $lockedUser->save();

            $reference = 'PAY-'.Str::upper(Str::random(12));

            $payment = Payment::create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'amount' => $order->total,
                'status' => 'completed',
                'simulated_delay_ms' => $delayMs,
                'gateway_response' => json_encode([
                    'provider' => 'simulated_bank',
                    'reference' => $reference,
                    'latency_ms' => $delayMs,
                ]),
            ]);

            $order->update([
                'payment_status' => 'paid',
                'payment_reference' => $reference,
                'paid_at' => now(),
                'order_status' => 'confirmed',
            ]);

            return $payment;
        });
    }

    public function refund(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $user = User::query()->whereKey($order->user_id)->lockForUpdate()->firstOrFail();
            $user->balance = round((float) $user->balance + (float) $order->total, 2);
            $user->version = (int) $user->version + 1;
            $user->save();

            $order->update([
                'payment_status' => 'refunded',
                'order_status' => 'cancelled',
            ]);
        });
    }
}
