<?php

namespace Database\Factories;

use App\Domain\Content\Landing\LandingRules;
use App\Domain\Content\Models\LandingTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<LandingTemplate> */
class LandingTemplateFactory extends Factory
{
    protected $model = LandingTemplate::class;

    public function definition(): array
    {
        return ['slug' => 'template-'.strtolower(Str::random(12)), 'name' => ['en' => 'Original template', 'bn' => 'মূল টেমপ্লেট'], 'sections' => [['key' => 'hero', 'type' => 'hero', 'title' => ['en' => 'Original product information', 'bn' => 'মূল পণ্যের তথ্য'], 'body' => ['en' => '<p>Review the details and choose a package.</p>', 'bn' => '<p>তথ্য দেখে প্যাকেজ বেছে নিন।</p>'], 'config' => []], ['key' => 'order', 'type' => 'order_form', 'title' => ['en' => 'Place your order', 'bn' => 'অর্ডার দিন'], 'config' => []]], 'form_config' => app(LandingRules::class)->formDefaults()];
    }
}
