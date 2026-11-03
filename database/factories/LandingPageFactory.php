<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Product;
use App\Domain\Content\Landing\LandingRules;
use App\Domain\Content\Models\LandingPage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<LandingPage> */
class LandingPageFactory extends Factory
{
    protected $model = LandingPage::class;

    public function definition(): array
    {
        return ['product_id' => Product::factory(), 'slug' => 'landing-'.strtolower(Str::random(12)), 'title' => ['en' => 'Original landing page', 'bn' => 'মূল ল্যান্ডিং পাতা'], 'status' => 'published', 'sections' => [['key' => 'hero', 'type' => 'hero', 'title' => ['en' => 'Original product information', 'bn' => 'মূল পণ্যের তথ্য'], 'body' => ['en' => '<p>Review the details and choose a package.</p>', 'bn' => '<p>তথ্য দেখে প্যাকেজ বেছে নিন।</p>'], 'config' => []], ['key' => 'order', 'type' => 'order_form', 'title' => ['en' => 'Place your order', 'bn' => 'অর্ডার দিন'], 'config' => []]], 'form_config' => app(LandingRules::class)->formDefaults()];
    }
}
