<?php

namespace App\Jobs;

use App\Domain\Marketing\Models\TrackingConsent;
use App\Domain\Marketing\Models\TrackingEvent;
use App\Domain\Payments\Actions\ProviderHttp;
use App\Domain\Settings\Actions\CredentialVault;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class DeliverTrackingEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $eventId) {}

    public function handle(): void
    {
        $e = DB::transaction(function () {
            $e = TrackingEvent::lockForUpdate()->find($this->eventId);
            if (! $e || ! in_array($e->status, ['pending', 'retry'], true) || $e->attempts >= 3) {
                return null;
            }$e->update(['status' => 'sending', 'attempts' => $e->attempts + 1]);

            return $e;
        });
        if (! $e) {
            return;
        }
        try {
            $c = TrackingConsent::find($e->tracking_consent_id);
            $ids = store_setting('tracking_ids', []);
            $pixel = $ids['meta'] ?? '';
            $token = app(CredentialVault::class)->get('tracking.meta_token');
            if (! store_setting('tracking_enabled', false) || ! store_setting('tracking_capi', false) || ! $c || $c->revoked_at || $c->expires_at->isPast() || $e->occurred_at->lt(now()->subDays(7)) || ! preg_match('/^[0-9]{5,25}$/D', $pixel) || ! $token) {
                $e->update(['status' => 'skipped']);

                return;
            }
            $private = $c->private_data ?? [];
            if (empty($private['ip']) || ! filter_var($private['ip'], FILTER_VALIDATE_IP) || empty($private['agent'])) {
                $e->update(['status' => 'skipped', 'failure_code' => 'matching_data_missing']);

                return;
            }
            $data = $e->data;
            if (isset($data['value_minor'])) {
                $data['value'] = $data['value_minor'] / 100;
                unset($data['value_minor']);
            }
            $event = ['event_name' => $e->name, 'event_time' => $e->occurred_at->timestamp, 'event_id' => $e->event_id, 'action_source' => 'website', 'event_source_url' => $e->source_url, 'user_data' => ['client_ip_address' => $private['ip'], 'client_user_agent' => $private['agent']], 'custom_data' => $data];
            $response = app(ProviderHttp::class)->json('meta_capi', fn ($r) => $r->withToken($token)->post('https://graph.facebook.com/v26.0/'.$pixel.'/events', ['data' => [$event]]));
            if ((int) ($response['events_received'] ?? 0) !== 1) {
                throw new \RuntimeException('Acceptance missing');
            }
            $e->update(['status' => 'accepted', 'accepted_at' => now(), 'failure_code' => null]);
        } catch (\Throwable $error) {
            $e->update(['status' => $e->attempts < 3 ? 'retry' : 'needs_review', 'failure_code' => 'acceptance_uncertain']);
            Log::warning('Tracking acceptance pending', ['event_id' => $e->event_id]);
        }
    }
}
