<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\OptionValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OptionValue> */
class OptionValueFactory extends Factory
{
    protected $model = OptionValue::class;

    public function definition(): array
    {
        return ['product_option_id' => ProductOptionFactory::new(), 'label' => ['en' => 'Standard', 'bn' => 'সাধারণ'], 'price' => 1000];
    }
}
