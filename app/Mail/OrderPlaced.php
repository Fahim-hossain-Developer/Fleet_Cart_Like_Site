<?php

namespace App\Mail;

use App\Domain\Orders\Models\Order;
use Illuminate\Mail\Mailable;

class OrderPlaced extends Mailable
{
    public function __construct(public Order $order) {}

    public function build(): static
    {
        return $this->subject(__('checkout.order_placed').' '.$this->order->number)->view('emails.order-placed');
    }
}
