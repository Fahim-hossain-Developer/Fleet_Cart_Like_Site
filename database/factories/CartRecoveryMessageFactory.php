<?php

namespace Database\Factories;

use App\Domain\Marketing\Models\CartRecoveryContact;
use App\Domain\Marketing\Models\CartRecoveryMessage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CartRecoveryMessage> */
class CartRecoveryMessageFactory extends Factory
{
    protected $model = CartRecoveryMessage::class;

    public function definition(): array
    {
        return ['cart_recovery_contact_id' => CartRecoveryContact::factory(), 'kind' => 'reminder', 'channel' => 'email', 'stage' => 1, 'generation' => 1, 'payload' => ['token' => Str::random(64)], 'snapshot' => [], 'expires_at' => now()->addDays(7)];
    }
}
