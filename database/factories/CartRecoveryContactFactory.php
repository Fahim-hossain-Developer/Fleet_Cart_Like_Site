<?php

namespace Database\Factories;

use App\Domain\Cart\Models\Cart;
use App\Domain\Marketing\Models\CartRecoveryContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CartRecoveryContact> */
class CartRecoveryContactFactory extends Factory
{
    protected $model = CartRecoveryContact::class;

    public function definition(): array
    {
        return ['cart_id' => Cart::factory(), 'email' => 'shopper@example.test', 'email_hash' => hash_hmac('sha256', 'shopper@example.test', config('app.key')), 'channels' => ['email' => true], 'generation' => 1, 'consented_at' => now(), 'last_activity_at' => now()->subHours(2), 'email_verified_at' => now()];
    }
}
