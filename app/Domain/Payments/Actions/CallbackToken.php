<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Payments\Models\GatewayAttempt;

final class CallbackToken
{
    public function token(GatewayAttempt $attempt, int $expires): string
    {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', 'payment-return|'.$attempt->uuid.'|'.$attempt->mode.'|'.$expires, config('app.key'), true)), '+/', '-_'), '=');
    }

    public function url(GatewayAttempt $attempt): string
    {
        $expires = now()->addHours(2)->timestamp;

        return route('integrations.payment.return', ['attempt' => $attempt->uuid, 'expires' => $expires, 'token' => $this->token($attempt, $expires)]);
    }

    public function valid(GatewayAttempt $attempt, int $expires, string $token): bool
    {
        return $expires >= now()->timestamp && hash_equals($this->token($attempt, $expires), $token);
    }
}
