<?php

namespace App\Domain\Customers\Actions;

use App\Domain\Customers\Models\SmsDelivery;
use App\Domain\Payments\Actions\IntegrationFailure;
use App\Domain\Payments\Actions\ProviderHttp;
use App\Domain\Settings\Actions\IntegrationConfiguration;
use App\Jobs\SendSms;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class Sms
{
    public function queue(string $phone, string $message): SmsDelivery
    {
        $driver = store_setting('sms_driver', 'sms_log');
        $config = app(IntegrationConfiguration::class);
        abort_unless(in_array($driver, ['sms_log', 'sms_twilio', 'sms_custom'], true) && $config->ready($driver), 422, __('integrations.sms_unavailable'));
        $d = SmsDelivery::create(['uuid' => (string) Str::uuid(), 'receiver' => $phone, 'payload' => $message, 'driver' => $driver, 'mode' => $config->connection($driver)->mode, 'expires_at' => now()->addMinutes(10)]);
        DB::afterCommit(fn () => SendSms::dispatch($d->id));

        return $d;
    }

    public function deliver(int $id): void
    {
        $d = DB::transaction(function () use ($id) {
            $d = SmsDelivery::lockForUpdate()->find($id);
            if (! $d || $d->status !== 'pending' || $d->attempts >= 3 || now()->gte($d->expires_at)) {
                return null;
            }$d->update(['status' => 'sending', 'attempts' => $d->attempts + 1]);

            return $d;
        });
        if (! $d) {
            return;
        }
        try {
            $config = app(IntegrationConfiguration::class);
            $driver = $d->driver;
            if (app()->environment('production') && ($d->mode !== 'live' || $driver === 'sms_log')) {
                throw new IntegrationFailure($driver, 'environment_blocked');
            }
            $secret = fn (string $key) => $config->credential($driver, $key, $d->mode) ?? '';
            if ($driver === 'sms_log') {
                abort_unless(app()->environment('local', 'testing'), 403);
                Log::channel('single')->info('LOCAL SMS SIMULATION', ['delivery_id' => $d->id, 'message' => $d->payload]);
                $ref = 'LOG-'.$d->uuid;
            } elseif ($driver === 'sms_twilio') {
                $sid = $secret('account_sid');
                if (! preg_match('/^AC[a-fA-F0-9]{32}$/D', $sid)) {
                    throw new IntegrationFailure($driver, 'configuration_invalid');
                }
                $p = app(ProviderHttp::class)->request()->asForm()->withBasicAuth($sid, $secret('auth_token'));
                $data = app(ProviderHttp::class)->json($driver, fn ($http) => $p->post('https://api.twilio.com/2010-04-01/Accounts/'.$sid.'/Messages.json', ['To' => $d->receiver, 'From' => $secret('from'), 'Body' => $d->payload]));
                $ref = $data['sid'] ?? null;
                if (! is_string($ref) || ! preg_match('/^SM[a-fA-F0-9]{32}$/D', $ref)) {
                    throw new IntegrationFailure($driver, 'response_invalid');
                }
            } else {
                // Custom contract: HTTPS POST, Bearer key, configurable recipient/message field names; 2xx JSON accepted=true and string id.
                $c = $config->connection($driver);
                if ($c->mode !== $d->mode) {
                    throw new IntegrationFailure($driver, 'configuration_changed');
                }
                $p = app(ProviderHttp::class)->request()->withToken($secret('api_key'));
                if (($c->config['format'] ?? 'json') === 'form') {
                    $p = $p->asForm();
                }
                $body = [($c->config['recipient_field'] ?? 'to') => $d->receiver, ($c->config['message_field'] ?? 'message') => $d->payload];
                if (! empty($c->config['sender'])) {
                    $body['sender'] = $c->config['sender'];
                }
                $data = app(ProviderHttp::class)->json($driver, fn ($http) => $p->post($c->config['endpoint'], $body));
                $ref = $data['id'] ?? null;
                if (($data['accepted'] ?? false) !== true || ! is_string($ref) || strlen($ref) > 191) {
                    throw new IntegrationFailure($driver, 'response_invalid');
                }
            }
            $d->update(['status' => 'sent', 'provider_reference' => $ref, 'sent_at' => now(), 'receiver' => null, 'payload' => null, 'failure_code' => null]);
        } catch (\Throwable $e) {
            $d->update(['status' => 'unknown', 'failure_code' => 'delivery_unknown']);
            Log::warning('SMS acceptance unknown', ['delivery_id' => $d->id]);
        }
    }
}
