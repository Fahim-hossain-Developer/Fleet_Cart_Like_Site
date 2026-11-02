<?php

namespace Database\Factories;

use App\Domain\Cart\Models\Cart;
use App\Domain\Marketing\Models\CartRecoveryContact;
use App\Domain\Marketing\Models\CartRecoveryRestoration;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CartRecoveryRestoration> */
class CartRecoveryRestorationFactory extends Factory
{
    protected $model = CartRecoveryRestoration::class;

    public function definition(): array
    {
        return ['cart_recovery_contact_id' => CartRecoveryContact::factory(), 'generation' => 1, 'destination_cart_id' => Cart::factory()];
    }
}
