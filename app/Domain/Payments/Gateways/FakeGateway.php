<?php

namespace App\Domain\Payments\Gateways;

use App\Domain\Payments\Actions\IntegrationFailure;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\DTOs\PaymentRedirect;
use App\Domain\Payments\DTOs\PaymentResult;
use App\Domain\Payments\DTOs\RefundResult;
use App\Domain\Payments\DTOs\RemoteState;
use App\Domain\Payments\Models\GatewayAttempt;
use App\Domain\Payments\Models\GatewayRefund;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

final class FakeGateway implements PaymentGateway
{
    private function local(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new IntegrationFailure('fake', 'local_only');
        }
    }

    public function supportsRefunds(): bool
    {
        return true;
    }

    public function currencies(): array
    {
        return ['BDT', 'USD', 'EUR', 'GBP'];
    }

    public function initiate(GatewayAttempt $attempt, string $returnUrl, string $webhookUrl): PaymentRedirect
    {
        $this->local();

        return new PaymentRedirect('FAKE-'.$attempt->uuid, URL::temporarySignedRoute('payments.fake', now()->addMinutes(15), ['attempt' => $attempt->uuid], false));
    }

    public function verify(GatewayAttempt $attempt, bool $allowExecute = false): PaymentResult
    {
        $this->local();
        $attempt->refresh();
        $state = match ($attempt->provider_state) {
            'paid' => RemoteState::Paid,'failed' => RemoteState::Failed,'cancelled' => RemoteState::Cancelled,default => RemoteState::Pending
        };

        return new PaymentResult($state, $attempt->merchant_reference, 'FAKE-TXN-'.$attempt->uuid, $attempt->amount, $attempt->currency, true);
    }

    public function refund(GatewayRefund $refund): RefundResult
    {
        $this->local();

        return new RefundResult(RemoteState::Paid, 'FAKE-REFUND-'.$refund->uuid, $refund->amount, $refund->currency);
    }

    public function verifyRefund(GatewayRefund $refund): RefundResult
    {
        return $this->refund($refund);
    }

    public function authenticateWebhook(Request $request): bool
    {
        return false;
    }

    public function webhook(Request $request): array
    {
        throw new IntegrationFailure('fake', 'webhook_disabled');
    }
}
