<?php

namespace Database\Factories;

use App\Domain\Cart\Models\Cart;
use App\Domain\Checkout\Models\CheckoutPhoneChallenge;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<CheckoutPhoneChallenge> */
class CheckoutPhoneChallengeFactory extends Factory
{
    protected $model = CheckoutPhoneChallenge::class;

    public function definition(): array
    {
        return ['uuid' => (string) Str::uuid(), 'cart_id' => Cart::factory(), 'phone_hash' => hash('sha256', Str::random(20)), 'code_hash' => Hash::make('123456'), 'expires_at' => now()->addMinutes(10)];
    }
}
