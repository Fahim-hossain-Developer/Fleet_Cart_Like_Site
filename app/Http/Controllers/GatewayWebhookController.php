<?php

namespace App\Http\Controllers;

use App\Domain\Payments\Actions\GatewayManager;
use App\Domain\Payments\Actions\IntegrationFailure;
use App\Domain\Settings\Models\IntegrationEvent;
use App\Jobs\ProcessPaymentEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class GatewayWebhookController extends Controller
{
    public function __invoke(Request $request, string $driver, string $mode): mixed
    {
        abort_if(app()->environment('production') && $mode !== 'live', 403);
        $gateway = app(GatewayManager::class)->driver($driver, $mode);
        if (! $gateway->authenticateWebhook($request)) {
            Log::warning('Payment webhook authenticity rejected', ['driver' => $driver, 'mode' => $mode, 'payload_hash' => hash('sha256', $request->getContent())]);
            abort(403);
        }
        try {
            $data = $gateway->webhook($request);
        } catch (IntegrationFailure $e) {
            return response()->json(['accepted' => false], 400);
        }
        foreach (['id', 'type', 'reference'] as $key) {
            if (strlen($data[$key]) > ($key === 'type' ? 100 : 191)) {
                return response()->json(['accepted' => false], 400);
            }
        }
        $key = hash('sha256', 'payment|'.$driver.'|'.$mode.'|'.$data['id']);
        IntegrationEvent::upsert([['dedupe_key' => $key, 'kind' => 'payment', 'driver' => $driver, 'mode' => $mode, 'external_id' => $data['id'], 'event_type' => $data['type'], 'reference' => $data['reference'], 'payload_hash' => hash('sha256', $request->getContent()), 'status' => 'received', 'attempts' => 0, 'created_at' => now(), 'updated_at' => now()]], ['dedupe_key'], ['updated_at']);
        $event = IntegrationEvent::where('dedupe_key', $key)->firstOrFail();
        if ($event->status !== 'processed') {
            ProcessPaymentEvent::dispatchAfterResponse($event->id);
        }

        return response()->json(['accepted' => true]);
    }
}
