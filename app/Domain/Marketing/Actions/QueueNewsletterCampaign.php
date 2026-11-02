<?php

namespace App\Domain\Marketing\Actions;

use App\Domain\Marketing\Models\NewsletterCampaign;
use App\Domain\Marketing\Models\NewsletterSubscriber;
use App\Domain\Orders\Actions\OrderAccess;
use App\Jobs\PrepareNewsletterCampaign;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class QueueNewsletterCampaign
{
    public function execute(NewsletterCampaign $campaign): void
    {
        $a = app(OrderAccess::class)->staff('marketing.manage');
        DB::transaction(function () use ($campaign, $a) {
            $c = NewsletterCampaign::lockForUpdate()->findOrFail($campaign->id);
            abort_unless(in_array($c->status, ['draft', 'paused'], true), 422);
            validator(['name' => $c->getTranslations('name'), 'body' => $c->getTranslations('body')], ['name.en' => 'required|string|max:200', 'body.en' => 'required|string|max:20000', 'name.bn' => 'nullable|string|max:200', 'body.bn' => 'nullable|string|max:20000'])->validate();
            abort_unless(NewsletterSubscriber::where('status', 'active')->whereNotNull('confirmed_at')->exists(), 422, __('marketing.no_subscribers'));
            $c->update(['status' => 'queued', 'admin_id' => $a->id, 'send_at' => $c->send_at ?? now()]);
            activity()->causedBy($a)->performedOn($c)->log('newsletter_queued');
            DB::afterCommit(function () use ($c) {
                try {
                    PrepareNewsletterCampaign::dispatch($c->id);
                } catch (\Throwable $e) {
                    Log::warning('Newsletter campaign pending preparation', ['campaign_id' => $c->id]);
                }
            });
        });
    }

    public function pause(NewsletterCampaign $campaign): void
    {
        $a = app(OrderAccess::class)->staff('marketing.manage');
        NewsletterCampaign::whereKey($campaign->id)->whereIn('status', ['queued', 'sending'])->update(['status' => 'paused']);
        activity()->causedBy($a)->performedOn($campaign)->log('newsletter_paused');
    }
}
