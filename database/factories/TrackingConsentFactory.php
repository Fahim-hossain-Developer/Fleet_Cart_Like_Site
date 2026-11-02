<?php

namespace Database\Factories;

use App\Domain\Marketing\Models\TrackingConsent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<TrackingConsent> */
class TrackingConsentFactory extends Factory
{
    protected $model = TrackingConsent::class;

    public function definition(): array
    {
        $subject = Str::random(40);

        return ['uuid' => (string) Str::uuid(), 'private_data' => ['ip' => '192.0.2.1', 'agent' => 'Test Browser'], 'consented_at' => now(), 'expires_at' => now()->addDay()];
    }
}
