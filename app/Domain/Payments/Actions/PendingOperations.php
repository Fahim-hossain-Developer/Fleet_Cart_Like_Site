<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\GatewayAttempt;
use App\Domain\Payments\Models\GatewayRefund;
use App\Domain\Shipping\Models\CourierBooking;
use Illuminate\Validation\ValidationException;

final class PendingOperations
{
    public const PAYMENTS = ['created', 'initializing', 'initiated', 'unknown', 'verifying', 'review_required'];

    public const REFUNDS = ['created', 'initializing', 'pending', 'unknown', 'review_required'];

    public function mutable(Order $order): void
    {
        if (CourierBooking::where('order_id', $order->id)->whereIn('status', ['created', 'initializing', 'unknown'])->exists() || GatewayAttempt::where('order_id', $order->id)->whereIn('status', self::PAYMENTS)->exists()) {
            throw ValidationException::withMessages(['order' => __('payments.pending_operation')]);
        }
    }

    public function refundFree(Order $order, ?int $except = null): void
    {
        if (GatewayRefund::where('order_id', $order->id)->whereIn('status', self::REFUNDS)->when($except, fn ($q) => $q->where('id', '!=', $except))->exists()) {
            throw ValidationException::withMessages(['order' => __('payments.pending_refund')]);
        }
    }
}
