<?php

namespace Database\Factories;

use App\Domain\Content\Models\ContactMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContactMessage> */
class ContactMessageFactory extends Factory
{
    protected $model = ContactMessage::class;

    public function definition(): array
    {
        return ['name' => fake()->name(), 'email' => fake()->safeEmail(), 'message' => 'A question about this store.', 'consented_at' => now(), 'is_closed' => false];
    }
}
