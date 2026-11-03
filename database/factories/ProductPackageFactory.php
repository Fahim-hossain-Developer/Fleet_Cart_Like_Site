<?php

namespace Database\Factories;

use App\Domain\Content\Models\LandingPage;
use App\Domain\Content\Models\ProductPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductPackage> */
class ProductPackageFactory extends Factory
{
    protected $model = ProductPackage::class;

    public function definition(): array
    {
        return ['landing_page_id' => LandingPage::factory(), 'product_id' => fn (array $a) => LandingPage::findOrFail($a['landing_page_id'])->product_id, 'label' => ['en' => 'One piece', 'bn' => 'একটি'], 'role' => 'primary', 'qty' => 1, 'price' => 100000, 'is_active' => true];
    }
}
