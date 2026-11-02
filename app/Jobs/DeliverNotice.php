<?php

namespace App\Jobs;

use App\Domain\Customers\Actions\NoticePreferences;
use App\Domain\Customers\Actions\Sms;
use App\Domain\Customers\Models\NoticeDelivery;
use App\Domain\Customers\Models\StoreNotification;
use App\Domain\Marketing\Actions\SyncMarketingSms;
use App\Mail\StoreNoticeMail;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class DeliverNotice implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $deliveryId) {}

    public function handle(): void
    {
        $d = DB::transaction(function () {
            $d = NoticeDelivery::lockForUpdate()->find($this->deliveryId);
            if (! $d || $d->status !== 'pending' || $d->attempts >= 3) {
                return null;
            }$d->update(['status' => 'sending', 'attempts' => $d->attempts + 1]);

            return $d;
        });
        if (! $d) {
            return;
        }
        try {
            $n = StoreNotification::findOrFail($d->notification_id);
            $u = User::find($n->notifiable_id);
            $topic = $n->data['topic'];
            if (! $u || ! app(NoticePreferences::class)->enabled($u, $topic, $d->channel) || ($d->channel === 'email' && ! $u->email_verified_at) || ($d->channel === 'sms' && ! $u->phone_verified_at)) {
                $d->update(['status' => 'skipped']);

                return;
            }
            $body = __($n->data['key'], $n->data['params'], $u->locale ?: config('app.locale'))."\n".url($n->data['url']);
            if ($d->channel === 'email') {
                Mail::to($u->email)->send(new StoreNoticeMail($body, __('marketing.notification_subject', [], $u->locale ?: config('app.locale'))));
            } else {
                $sms = app(Sms::class)->queue($u->phone, $body);
                $d->update(['status' => 'queued_sms', 'sms_delivery_id' => $sms->id, 'failure_code' => null]);
                app(SyncMarketingSms::class)->execute();

                return;
            }
            $d->update(['status' => 'sent', 'sent_at' => now(), 'failure_code' => null]);
        } catch (\Throwable $e) {
            $d->update(['status' => 'unknown', 'failure_code' => 'delivery_unknown']);
            Log::warning('Notice delivery uncertain', ['delivery_id' => $d->id]);
        }
    }
}
