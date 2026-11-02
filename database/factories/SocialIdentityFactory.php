<?php

namespace Database\Factories;

use App\Domain\Customers\Models\SocialIdentity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<SocialIdentity> */
class SocialIdentityFactory extends Factory
{
    protected $model = SocialIdentity::class;

    public function definition(): array
    {
        $subject = Str::random(40);

        return ['user_id' => User::factory(), 'provider' => 'google', 'subject_hash' => hash('sha256', $subject), 'subject' => $subject];
    }
}
