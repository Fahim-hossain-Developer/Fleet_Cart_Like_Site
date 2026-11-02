<?php

namespace App\Domain\Payments\Gateways;

use App\Domain\Payments\Actions\IntegrationFailure;
use App\Domain\Payments\Actions\ProviderHttp;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\DTOs\RefundResult;
use App\Domain\Payments\Models\GatewayRefund;
use App\Domain\Settings\Actions\IntegrationConfiguration;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;

abstract class HttpGateway implements PaymentGateway
{
    protected string $driver;

    public function __construct(protected readonly string $mode) {}

    protected function credential(string $key): string
    {
        $value = app(IntegrationConfiguration::class)->credential($this->driver, $key, $this->mode);
        if (! $value) {
            throw new IntegrationFailure($this->driver, 'configuration_missing');
        }

        return $value;
    }

    /**
     * @param callable(PendingRequest):Response $callback
     * @return array<string,mixed> */
    protected function call(callable $callback): array
    {
        return app(ProviderHttp::class)->json($this->driver, $callback);
    }

    public function supportsRefunds(): bool
    {
        return false;
    }

    public function refund(GatewayRefund $refund): RefundResult
    {
        throw new IntegrationFailure($this->driver, 'refund_not_supported');
    }

    public function verifyRefund(GatewayRefund $refund): RefundResult
    {
        throw new IntegrationFailure($this->driver, 'refund_not_supported');
    }

    public function authenticateWebhook(Request $request): bool
    {
        return false;
    }

    public function webhook(Request $request): array
    {
        throw new IntegrationFailure($this->driver, 'webhook_protocol_pending');
    }

    protected function require(bool $condition): void
    {
        if (! $condition) {
            throw new IntegrationFailure($this->driver, 'invalid_response');
        }
    }
}
