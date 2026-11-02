<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\CatalogImport;
use App\Models\Admin;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CatalogImport> */
class CatalogImportFactory extends Factory
{
    protected $model = CatalogImport::class;

    public function definition(): array
    {
        return ['admin_id' => Admin::create(['name' => 'Importer', 'email' => fake()->unique()->safeEmail(), 'password' => fake()->password()])->id, 'path' => 'catalog-imports/example.csv'];
    }
}
