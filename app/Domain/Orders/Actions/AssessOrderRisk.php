<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Orders\Models\Order;
use Illuminate\Support\Facades\DB;

final class AssessOrderRisk
{
    /** @return list<string> */
    public function flags(Order $order): array
    {
        $flags = [];
        if (DB::table('phone_blacklist')->where('phone', $order->phone)->exists()) {
            $flags[] = 'blacklisted_phone';
        }
        if (Order::where('phone', $order->phone)->where('status', 'cancelled')->where('id', '!=', $order->id)->count() >= 3) {
            $flags[] = 'repeat_cancel';
        }
        if ($order->ip_hash && Order::where('ip_hash', $order->ip_hash)->where('placed_at', '>=', now()->subMinutes(10))->count() >= 3) {
            $flags[] = 'ip_burst';
        }
        if ($order->payment_method === 'cod' && $order->total >= (int) store_setting('orders_high_value_cod', 1000000)) {
            $flags[] = 'high_value_cod';
        }

        return $flags;
    }
}
