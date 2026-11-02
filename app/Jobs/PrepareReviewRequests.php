<?php

namespace App\Jobs;

use App\Domain\Customers\Actions\PublishNotice;
use App\Domain\Marketing\Models\ReviewRequest;
use App\Domain\Orders\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

final class PrepareReviewRequests implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        if (! store_setting('reviews_request_enabled', false)) {
            return;
        }$days = max(1, min(30, (int) store_setting('reviews_request_days', 3)));
        Order::whereIn('status', ['delivered', 'completed'])->where('fulfillment_status', 'fulfilled')->whereColumn('paid_amount', '>=', 'total')->whereNotNull('user_id')->where(fn ($q) => $q->whereNull('payment_environment')->orWhere('payment_environment', 'live'))->whereHas('events', fn ($q) => $q->where('type', 'delivered')->where('created_at', '<=', now()->subDays($days))->where('created_at', '>=', now()->subDays($days + 7)))->orderBy('id')->chunkById(100, function ($orders) {
            foreach ($orders as $order) {
                DB::transaction(function () use ($order) {
                    $o = Order::lockForUpdate()->findOrFail($order->id);
                    $u = User::find($o->user_id);
                    if (! $u || $u->is_blocked) {
                        return;
                    }$r = ReviewRequest::firstOrCreate(['order_id' => $o->id]);
                    if ($r->sent_at || $r->attempts >= 3) {
                        return;
                    }$r->increment('attempts');
                    app(PublishNotice::class)->execute($u, 'reviews', 'marketing.notice_review_request', ['number' => $o->number], route('account.order', $o->number, false), 'review-request:'.$o->id);
                    $r->update(['sent_at' => now()]);
                });
            }
        });
    }
}
