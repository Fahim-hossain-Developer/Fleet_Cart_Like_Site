<?php

namespace Database\Factories;

use App\Domain\Content\Models\Redirect;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Redirect> */
class RedirectFactory extends Factory
{
    protected $model = Redirect::class;

    public function definition(): array
    {
        $subject = Str::random(40);

        return ['from' => '/old-'.Str::random(12), 'to' => '/shop', 'code' => 301];
    }
}
