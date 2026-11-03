<?php

namespace Database\Factories;

use App\Domain\Checkout\Models\LandingOrderClaim;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<LandingOrderClaim> */
class LandingOrderClaimFactory extends Factory
{
    protected $model = LandingOrderClaim::class;

    public function definition(): array
    {
        return ['identity_hash' => hash('sha256', Str::random(40)), 'expires_at' => now()->addMinutes(10)];
    }
}
