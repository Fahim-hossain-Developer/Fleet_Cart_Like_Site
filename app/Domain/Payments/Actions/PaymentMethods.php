<?php

namespace App\Domain\Payments\Actions;

final class PaymentMethods
{
    /** @return array<string,array<string,mixed>> */
    public function current(): array
    {
        $methods = store_setting('checkout_payment_methods', config('checkout.payment_methods'));
        foreach (app(GatewayManager::class)->available(config('checkout.base_currency')) as $driver => $label) {
            $methods[$driver] = ['enabled' => true, 'instructions' => __('payments.provider_instruction', ['provider' => $label])];
        }

        return $methods;
    }
}
