<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\ProductRelation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductRelation> */
class ProductRelationFactory extends Factory
{
    protected $model = ProductRelation::class;

    public function definition(): array
    {
        return ['product_id' => ProductFactory::new(), 'related_id' => ProductFactory::new(), 'type' => 'related'];
    }
}
