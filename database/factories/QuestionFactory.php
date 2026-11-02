<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Product;
use App\Domain\Marketing\Models\Question;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Question> */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    public function definition(): array
    {
        return ['product_id' => Product::factory(), 'user_id' => User::factory(), 'display_name' => 'Customer', 'question' => 'Is this item suitable for everyday use?'];
    }
}
