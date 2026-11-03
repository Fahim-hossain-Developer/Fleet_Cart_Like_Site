<?php

namespace Database\Seeders;

use App\Domain\Content\Landing\LandingTemplates;
use Illuminate\Database\Seeder;

class LandingTemplateSeeder extends Seeder
{
    public function run(): void
    {
        app(LandingTemplates::class)->seed();
    }
}
