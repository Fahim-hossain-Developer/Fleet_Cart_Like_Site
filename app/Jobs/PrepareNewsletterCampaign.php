<?php

namespace App\Jobs;

use App\Domain\Marketing\Models\NewsletterCampaign;
use App\Domain\Marketing\Models\NewsletterMessage;
use App\Domain\Marketing\Models\NewsletterSubscriber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

final class PrepareNewsletterCampaign implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $campaignId) {}

    public function handle(): void
    {
        $c = DB::transaction(function () {
            $c = NewsletterCampaign::lockForUpdate()->find($this->campaignId);
            if (! $c || ! in_array($c->status, ['queued', 'sending'], true) || ($c->send_at && $c->send_at->isFuture())) {
                return null;
            }$c->update(['status' => 'sending', 'audience_at' => $c->audience_at ?? now()]);

            return $c;
        });
        if (! $c) {
            return;
        }
        if (! $c->prepared_at) {
            $complete = NewsletterSubscriber::where('status', 'active')->whereNotNull('confirmed_at')->whereNotNull('consented_at')->where('confirmed_at', '<=', $c->audience_at)->orderBy('id')->chunkById(100, function ($subscribers) use ($c) {
                if (NewsletterCampaign::find($c->id)?->status !== 'sending') {
                    return false;
                }$rows = [];
                foreach ($subscribers as $s) {
                    $rows[] = ['newsletter_campaign_id' => $c->id, 'newsletter_subscriber_id' => $s->id, 'kind' => 'campaign', 'generation' => $s->generation, 'status' => 'pending', 'attempts' => 0, 'expires_at' => now()->addDays(7), 'created_at' => now(), 'updated_at' => now()];
                }if ($rows) {
                    NewsletterMessage::upsert($rows, ['newsletter_campaign_id', 'newsletter_subscriber_id'], ['updated_at']);
                }

                return true;
            });
            if (! $complete) {
                return;
            }$c->update(['prepared_at' => now()]);
        }
        foreach (NewsletterMessage::where('newsletter_campaign_id', $c->id)->where('status', 'pending')->pluck('id') as $id) {
            DeliverNewsletter::dispatch((int) $id);
        }
    }
}
