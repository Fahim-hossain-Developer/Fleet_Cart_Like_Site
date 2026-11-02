<?php

namespace App\Jobs;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderEvent;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendOrderUpdate implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 120;

    public function __construct(public int $eventId) {}

    public function uniqueId(): string
    {
        return (string) $this->eventId;
    }

    public function handle(): void
    {
        $event = OrderEvent::find($this->eventId);
        if (! $event || ! $event->notify || $event->sent_at) {
            return;
        }
        $order = Order::findOrFail($event->order_id);
        $event->increment('attempts');
        if ($order->guest_email) {
            Mail::raw($order->number."\n".$event->note, fn ($m) => $m->to($order->guest_email)->subject(__('orders.order_update').' '.$order->number));
        } else {
            Log::info('Local order update SMS pending provider', ['number' => $order->number, 'type' => $event->type]);
        }
        $event->update(['sent_at' => now()]);
    }
}
