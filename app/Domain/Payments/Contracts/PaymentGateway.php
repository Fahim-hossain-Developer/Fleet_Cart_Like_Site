<?php

namespace App\Domain\Payments\Contracts;

use App\Domain\Payments\DTOs\PaymentRedirect;
use App\Domain\Payments\DTOs\PaymentResult;
use App\Domain\Payments\DTOs\RefundResult;
use App\Domain\Payments\Models\GatewayAttempt;
use App\Domain\Payments\Models\GatewayRefund;
use Illuminate\Http\Request;

interface PaymentGateway
{
    /**
     * @return list<string> */
    public function currencies(): array;

    public function supportsRefunds(): bool;

    public function initiate(GatewayAttempt $attempt, string $returnUrl, string $webhookUrl): PaymentRedirect;

    public function verify(GatewayAttempt $attempt, bool $allowExecute = false): PaymentResult;

    public function refund(GatewayRefund $refund): RefundResult;

    public function verifyRefund(GatewayRefund $refund): RefundResult;

    public function authenticateWebhook(Request $request): bool;

    /**
     * @return array{id:string,type:string,reference:string} */
    public function webhook(Request $request): array;
}
