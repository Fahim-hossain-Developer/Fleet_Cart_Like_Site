<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Gateways\AamarPayGateway;
use App\Domain\Payments\Gateways\BkashGateway;
use App\Domain\Payments\Gateways\FakeGateway;
use App\Domain\Payments\Gateways\PaypalGateway;
use App\Domain\Payments\Gateways\PendingDocumentationGateway;
use App\Domain\Payments\Gateways\SslCommerzGateway;
use App\Domain\Payments\Gateways\StripeGateway;
use App\Domain\Settings\Actions\IntegrationConfiguration;

final class GatewayManager
{
    public function driver(string $driver, string $mode = 'sandbox'): PaymentGateway
    {
        if (! in_array($mode, ['sandbox', 'live'], true)) {
            throw new IntegrationFailure($driver, 'invalid_mode');
        }

        return match ($driver) {
            'fake' => new FakeGateway,'stripe' => new StripeGateway($mode),'paypal' => new PaypalGateway($mode),'sslcommerz' => new SslCommerzGateway($mode),'bkash' => new BkashGateway($mode),'aamarpay' => new AamarPayGateway($mode),'nagad' => new PendingDocumentationGateway($driver),default => throw new IntegrationFailure($driver, 'unknown_provider')
        };
    }

    /**
     * @return array<string,string> */
    public function available(string $currency, int $amount = 0): array
    {
        $available = [];
        $config = app(IntegrationConfiguration::class);
        foreach (IntegrationConfiguration::PROVIDERS as $name => $meta) {
            if ($meta['kind'] !== 'payment' || ! $config->ready($name)) {
                continue;
            }$c = $config->connection($name);
            if (! in_array($currency, $this->driver($name, $c->mode)->currencies(), true)) {
                continue;
            }if ($name === 'sslcommerz' && $amount && ($amount < 1000 || $amount > 50000000)) {
                continue;
            }if ($amount && ($amount < (int) ($c->config['minimum'] ?? 1) || $amount > (int) ($c->config['maximum'] ?? 100000000000))) {
                continue;
            }$available[$name] = __('integrations.provider_'.$name);
        }

        return $available;
    }

    public function redirectAllowed(string $driver, string $url): bool
    {
        if ($driver === 'fake') {
            return app()->environment('local', 'testing') && str_starts_with($url, '/payments/fake/');
        }
        $parts = parse_url($url);
        if (! $parts || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || (isset($parts['port']) && $parts['port'] !== 443)) {
            return false;
        }
        $host = strtolower($parts['host'] ?? '');
        $domains = match ($driver) {
            'stripe' => ['checkout.stripe.com'],'paypal' => ['paypal.com'],'sslcommerz' => ['sslcommerz.com'],'bkash' => ['bkash.com', 'bka.sh'],'aamarpay' => ['aamarpay.com'],default => []
        };
        foreach ($domains as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }
}
