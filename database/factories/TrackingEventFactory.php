<?php

namespace Database\Factories;

use App\Domain\Marketing\Models\TrackingConsent;
use App\Domain\Marketing\Models\TrackingEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<TrackingEvent> */
class TrackingEventFactory extends Factory
{
    protected $model = TrackingEvent::class;

    public function definition(): array
    {
        $subject = Str::random(40);

        return ['tracking_consent_id' => TrackingConsent::factory(), 'event_id' => (string) Str::uuid(), 'name' => 'ViewContent', 'source_key' => 'test:'.Str::uuid(), 'data' => ['currency' => 'BDT', 'value_minor' => 10000], 'source_url' => url('/shop'), 'occurred_at' => now(), 'status' => 'pending', 'attempts' => 0];
    }
}
