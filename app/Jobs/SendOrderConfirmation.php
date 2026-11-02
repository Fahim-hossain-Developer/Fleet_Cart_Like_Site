<?php

namespace App\Jobs;

use App\Domain\Customers\Actions\NoticePreferences;
use App\Domain\Customers\Actions\PublishNotice;
use App\Domain\Orders\Models\Order;
use App\Mail\OrderPlaced;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendOrderConfirmation implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $orderId) {}

    public function handle(): void
    {
        $order = Order::findOrFail($this->orderId);
        $delivery = DB::table('order_notifications')->where('order_id', $order->id)->first();
        $owner = $order->user_id ? User::find($order->user_id) : null;
        if ($owner) {
            app(PublishNotice::class)->execute($owner, 'orders', 'marketing.notice_order', ['number' => $order->number], route('account.order', $order->number, false), 'order-created:'.$order->id, true, ['sms']);
        }
        if (! $delivery || $delivery->sent_at) {
            return;
        }
        DB::table('order_notifications')->where('id', $delivery->id)->increment('attempts');
        if ($order->guest_email && (! $owner || app(NoticePreferences::class)->enabled($owner, 'orders', 'email'))) {
            Mail::to($order->guest_email)->send(new OrderPlaced($order));
        } else {
            Log::info('Local SMS order confirmation', ['number' => $order->number]);
        }
        $admins = Admin::where('is_active', true)->get()->filter(fn ($a) => $a->hasPermissionTo('orders.manage', 'admin'));
        foreach ($admins as $admin) {
            Mail::to($admin->email)->send(new OrderPlaced($order));
        }
        DB::table('order_notifications')->where('id', $delivery->id)->update(['sent_at' => now(), 'updated_at' => now()]);
    }
}
