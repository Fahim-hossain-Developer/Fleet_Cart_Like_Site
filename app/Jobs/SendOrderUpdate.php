<?php

namespace App\Jobs;

use App\Domain\Customers\Actions\NoticePreferences;
use App\Domain\Customers\Actions\PublishNotice;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderEvent;
use App\Models\User;
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
        if (! $event || ! $event->notify) {
            return;
        }
        $order = Order::findOrFail($event->order_id);
        $noticeOwner = $order->user_id ? User::find($order->user_id) : null;
        if ($noticeOwner) {
            app(PublishNotice::class)->execute($noticeOwner, 'orders', 'marketing.notice_order', ['number' => $order->number], route('account.order', $order->number, false), 'order-event:'.$event->id, true, ['sms']);
        }
        if ($event->sent_at) {
            return;
        }
        $event->increment('attempts');
        $owner = $order->user_id ? User::find($order->user_id) : null;
        if ($order->guest_email && (! $owner || app(NoticePreferences::class)->enabled($owner, 'orders', 'email'))) {
            Mail::raw($order->number."\n".$event->note, fn ($m) => $m->to($order->guest_email)->subject(__('orders.order_update').' '.$order->number));
        } else {
            Log::info('Local order update SMS pending provider', ['number' => $order->number, 'type' => $event->type]);
        }
        $event->update(['sent_at' => now()]);
    }
}
