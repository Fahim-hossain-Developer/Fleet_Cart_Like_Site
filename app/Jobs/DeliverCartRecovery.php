<?php

namespace App\Jobs;

use App\Domain\Cart\Models\Cart;
use App\Domain\Customers\Actions\Sms;
use App\Domain\Marketing\Actions\SyncMarketingSms;
use App\Domain\Marketing\Models\CartRecoveryContact;
use App\Domain\Marketing\Models\CartRecoveryMessage;
use App\Mail\NewsletterMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class DeliverCartRecovery implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $messageId) {}

    public function handle(): void
    {
        $m = DB::transaction(function () {
            $m = CartRecoveryMessage::lockForUpdate()->find($this->messageId);
            if (! $m || $m->status !== 'pending' || $m->attempts >= 3) {
                return null;
            }$m->update(['status' => 'sending', 'attempts' => $m->attempts + 1]);

            return $m;
        });
        if (! $m) {
            return;
        }
        try {
            $c = CartRecoveryContact::findOrFail($m->cart_recovery_contact_id);
            $cart = Cart::find($c->cart_id);
            if (! store_setting('recovery_enabled', false) || ! $cart || $c->stopped_at || $c->converted_at || $c->generation !== $m->generation || now()->gte($m->expires_at) || ! $cart->items()->where('saved', false)->exists()) {
                $m->update(['status' => 'skipped', 'payload' => null]);

                return;
            }$token = $m->payload['token'] ?? '';
            if (! is_string($token) || ! preg_match('/^[a-zA-Z0-9]{64}$/D', $token)) {
                $m->update(['status' => 'skipped', 'payload' => null]);

                return;
            }$locale = in_array($c->locale, ['en', 'bn'], true) ? $c->locale : 'en';
            if ($m->kind === 'confirmation') {
                if (! $c->email || $c->email_verified_at || ! hash_equals((string) $c->confirmation_hash, hash('sha256', $token))) {
                    $m->update(['status' => 'skipped', 'payload' => null]);

                    return;
                }$subject = __('marketing.recovery_confirm_subject', [], $locale);
                $body = __('marketing.recovery_confirm_body', [], $locale)."\n".route('recovery.confirm', $token);
                Mail::to($c->email)->send(new NewsletterMail($body, $subject));
            } else {
                $last = $c->last_activity_at;
                if ($cart->updated_at && (! $last || $cart->updated_at->gt($last))) {
                    $last = $cart->updated_at;
                }if (! $last || $last->gt(now()->subHours($m->stage))) {
                    $m->update(['status' => 'skipped', 'payload' => null]);

                    return;
                }
                if (empty($c->channels[$m->channel]) || ($m->channel === 'email' ? ! $c->email_verified_at : ! $c->phone_verified_at)) {
                    $m->update(['status' => 'skipped', 'payload' => null]);

                    return;
                }
                $body = __('marketing.recovery_message', [], $locale)."\n".route('recovery.restore', $token)."\n".__('marketing.recovery_stop', [], $locale).': '.route('recovery.stop', $c->stop_token);
                if ($m->channel === 'whatsapp') {
                    $m->update(['status' => 'ready_manual']);

                    return;
                }
                if ($m->channel === 'email') {
                    Mail::to($c->email)->send(new NewsletterMail($body, __('marketing.recovery_subject', [], $locale), route('recovery.one-click', $c->stop_token)));
                } else {
                    $sms = app(Sms::class)->queue($c->phone, $body);
                    $m->update(['status' => 'queued_sms', 'sms_delivery_id' => $sms->id, 'payload' => null, 'failure_code' => null]);
                    app(SyncMarketingSms::class)->execute();

                    return;
                }
            }$m->update(['status' => 'sent', 'sent_at' => now(), 'payload' => null, 'failure_code' => null]);
        } catch (\Throwable $e) {
            $m->update(['status' => 'unknown', 'failure_code' => 'transport_unknown']);
            Log::warning('Cart reminder acceptance uncertain', ['message_id' => $m->id]);
        }
    }
}
