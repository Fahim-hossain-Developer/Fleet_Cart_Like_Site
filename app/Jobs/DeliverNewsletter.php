<?php

namespace App\Jobs;

use App\Domain\Marketing\Models\NewsletterCampaign;
use App\Domain\Marketing\Models\NewsletterMessage;
use App\Domain\Marketing\Models\NewsletterSubscriber;
use App\Mail\NewsletterMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class DeliverNewsletter implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $messageId) {}

    public function handle(): void
    {
        $m = DB::transaction(function () {
            $m = NewsletterMessage::lockForUpdate()->find($this->messageId);
            if (! $m || $m->status !== 'pending' || $m->attempts >= 3) {
                return null;
            }$m->update(['status' => 'sending', 'attempts' => $m->attempts + 1]);

            return $m;
        });
        if (! $m) {
            return;
        }
        try {
            $s = NewsletterSubscriber::findOrFail($m->newsletter_subscriber_id);
            if ($m->generation !== $s->generation || ($m->expires_at && now()->gte($m->expires_at))) {
                $m->update(['status' => 'expired', 'payload' => null]);

                return;
            }
            $locale = in_array($s->locale, ['en', 'bn'], true) ? $s->locale : 'en';
            if ($m->kind === 'confirmation') {
                if ($s->status !== 'pending') {
                    $m->update(['status' => 'skipped', 'payload' => null]);

                    return;
                }$token = $m->payload['token'] ?? '';
                if (! is_string($token) || ! hash_equals((string) $s->confirmation_hash, hash('sha256', $token))) {
                    $m->update(['status' => 'skipped', 'payload' => null]);

                    return;
                }$body = __('marketing.newsletter_confirmation_body', [], $locale)."\n".route('newsletter.confirm', $token);
                $subject = __('marketing.newsletter_confirmation_subject', [], $locale);
                $unsub = null;
            } else {
                $campaign = NewsletterCampaign::findOrFail($m->newsletter_campaign_id);
                if ($s->status !== 'active' || ! $s->confirmed_at || ! $s->consented_at) {
                    $m->update(['status' => 'skipped']);

                    return;
                }if (! in_array($campaign->status, ['queued', 'sending'], true) || ($campaign->send_at && $campaign->send_at->isFuture())) {
                    $m->update(['status' => $campaign->status === 'paused' || $campaign->send_at?->isFuture() ? 'pending' : 'skipped', 'attempts' => max(0, $m->attempts - 1)]);

                    return;
                }
                $subject = $campaign->getTranslation('name', $locale);
                $body = $campaign->getTranslation('body', $locale)."\n\n".__('marketing.newsletter_unsubscribe', [], $locale).': '.route('newsletter.unsubscribe', $s->unsubscribe_token);
                $unsub = route('newsletter.one-click', $s->unsubscribe_token);
            }
            Mail::to($s->email)->send(new NewsletterMail($body, $subject, $unsub));
            $m->update(['status' => 'sent', 'sent_at' => now(), 'payload' => null, 'failure_code' => null]);
        } catch (\Throwable $e) {
            $m->update(['status' => 'unknown', 'failure_code' => 'transport_unknown']);
            Log::warning('Newsletter acceptance uncertain', ['message_id' => $m->id]);
        }
    }
}
