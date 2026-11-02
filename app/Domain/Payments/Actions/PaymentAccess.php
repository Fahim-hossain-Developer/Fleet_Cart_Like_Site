<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Orders\Actions\OrderAccess;
use App\Domain\Orders\Models\Order;
use App\Models\Admin;

final class PaymentAccess
{
    public function customer(Order $order): void
    {
        $owner = $order->user_id && auth('web')->id() === $order->user_id;
        $receipt = in_array($order->id, (array) session('placed_orders', []), true);
        abort_unless($owner || $receipt, 403);
    }

    public function staff(string $permission = 'payments.manage'): Admin
    {
        return app(OrderAccess::class)->staff($permission);
    }
}
