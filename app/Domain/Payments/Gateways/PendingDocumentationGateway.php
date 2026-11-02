<?php

namespace App\Domain\Payments\Gateways;

use App\Domain\Payments\Actions\IntegrationFailure;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\DTOs\PaymentRedirect;
use App\Domain\Payments\DTOs\PaymentResult;
use App\Domain\Payments\DTOs\RefundResult;
use App\Domain\Payments\Models\GatewayAttempt;
use App\Domain\Payments\Models\GatewayRefund;
use Illuminate\Http\Request;

final class PendingDocumentationGateway implements PaymentGateway
{
    public function __construct(private readonly string $driver) {}

    private function unavailable(): never
    {
        throw new IntegrationFailure($this->driver, 'documentation_required');
    }

    public function supportsRefunds(): bool
    {
        return false;
    }

    public function currencies(): array
    {
        return [];
    }

    public function initiate(GatewayAttempt $attempt, string $returnUrl, string $webhookUrl): PaymentRedirect
    {
        $this->unavailable();
    }

    public function verify(GatewayAttempt $attempt, bool $allowExecute = false): PaymentResult
    {
        $this->unavailable();
    }

    public function refund(GatewayRefund $refund): RefundResult
    {
        $this->unavailable();
    }

    public function verifyRefund(GatewayRefund $refund): RefundResult
    {
        $this->unavailable();
    }

    public function authenticateWebhook(Request $request): bool
    {
        return false;
    }

    public function webhook(Request $request): array
    {
        $this->unavailable();
    }
    // TODO(api-docs): implement only after official merchant protocol review. Saving configuration does not enable it.
}
