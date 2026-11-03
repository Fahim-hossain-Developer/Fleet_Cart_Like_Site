<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(LandingTemplateSeeder::class);
        $this->call(ThemePresetSeeder::class);
        $this->call([RolesAndPermissionsSeeder::class, CheckoutSeeder::class, ContentSeeder::class]);
    }
}
