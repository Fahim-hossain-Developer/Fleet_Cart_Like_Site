<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Orders\Models\Order;

final class OrderNumber
{
    public function generate(): string
    {
        do {
            $number = (string) store_setting('checkout_order_prefix', 'ORD').'-'.now()->format('ymd').'-'.strtoupper(bin2hex(random_bytes(8)));
        } while (Order::where('number', $number)->exists());

        return $number;
    }
}
