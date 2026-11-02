<?php

namespace App\Jobs;

use App\Domain\Payments\Actions\PaymentEngine;
use App\Domain\Payments\Actions\RefundEngine;
use App\Domain\Payments\Models\GatewayAttempt;
use App\Domain\Payments\Models\GatewayRefund;
use App\Domain\Settings\Models\IntegrationEvent;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ProcessPaymentEvent implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 120;

    public function __construct(public int $eventId) {}

    public function uniqueId(): string
    {
        return (string) $this->eventId;
    }

    public function handle(): void
    {
        $event = IntegrationEvent::findOrFail($this->eventId);
        if ($event->status === 'processed') {
            return;
        }$event->increment('attempts');
        if (str_contains(strtolower($event->event_type), 'refund')) {
            $attempts = GatewayAttempt::where('driver', $event->driver)->where('mode', $event->mode)->pluck('id');
            $r = GatewayRefund::whereIn('gateway_attempt_id', $attempts)->where(fn ($q) => $q->where('provider_reference', $event->reference)->orWhere('merchant_reference', $event->reference))->first();
            if ($r) {
                $result = app(RefundEngine::class)->verify($r);
                $done = in_array($result->status, ['succeeded', 'failed', 'review_required']);
            } else {
                $done = false;
            }
        } else {
            $a = GatewayAttempt::where('driver', $event->driver)->where('mode', $event->mode)->where(fn ($q) => $q->where('merchant_reference', $event->reference)->orWhere('provider_reference', $event->reference)->orWhere('verified_transaction', $event->reference))->first();
            if ($a) {
                $result = app(PaymentEngine::class)->verify($a, $event->driver === 'paypal' && $event->event_type === 'CHECKOUT.ORDER.APPROVED');
                $done = in_array($result->status, ['succeeded', 'failed', 'cancelled', 'review_required', 'refunded_unapplied']);
            } else {
                $done = false;
            }
        }
        $event->update(['status' => $done ? 'processed' : 'received', 'processed_at' => $done ? now() : null]);
    }
}
