<?php

namespace Database\Seeders;

use App\Domain\Catalog\Actions\GenerateVariants;
use App\Domain\Catalog\Models\Attribute;
use App\Domain\Catalog\Models\AttributeSet;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductTab;
use App\Domain\Catalog\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::firstOrCreate(['slug' => 'everyday'], ['name' => ['en' => 'Everyday essentials', 'bn' => 'প্রতিদিনের প্রয়োজন'], 'is_active' => true]);
        $brand = Brand::firstOrCreate(['slug' => 'studio'], ['name' => ['en' => 'Studio collection', 'bn' => 'স্টুডিও সংগ্রহ'], 'is_active' => true]);
        $tag = Tag::firstOrCreate(['slug' => 'everyday'], ['name' => ['en' => 'Everyday', 'bn' => 'প্রতিদিন']]);
        $attribute = Attribute::firstOrCreate(['slug' => 'size'], ['name' => ['en' => 'Size', 'bn' => 'মাপ'], 'type' => 'text']);
        foreach (['S', 'M', 'L'] as $size) {
            $attribute->values()->firstOrCreate(['value' => $size], ['name' => ['en' => $size, 'bn' => $size]]);
        }
        $set = AttributeSet::firstOrCreate(['slug' => 'apparel'], ['name' => ['en' => 'Apparel', 'bn' => 'পোশাক']]);
        $set->attributes()->syncWithoutDetaching([$attribute->id]);
        $names = [['Linen day shirt', 'লিনেন শার্ট'], ['Everyday tote', 'প্রতিদিনের ব্যাগ'], ['Stoneware cup', 'পাথুরে কাপ'], ['Cotton notebook', 'সুতি নোটবুক'], ['Soft cotton scarf', 'সুতির ওড়না'], ['Desk organizer', 'ডেস্ক অর্গানাইজার']];
        foreach ($names as $i => [$en,$bn]) {
            $slug = Str::slug($en);
            $p = Product::firstOrCreate(['slug' => $slug], ['sku' => 'STUDIO-'.($i + 1), 'name' => ['en' => $en, 'bn' => $bn], 'short_desc' => ['en' => 'Made for the small moments of everyday life.', 'bn' => 'প্রতিদিনের ছোট মুহূর্তের জন্য তৈরি।'], 'description' => ['en' => '<p>A thoughtfully designed everyday essential. This original sample can be edited from the catalog.</p>', 'bn' => '<p>যত্নের সাথে তৈরি প্রতিদিনের প্রয়োজনীয় পণ্য। ক্যাটালগ থেকে এই নমুনাটি সম্পাদনা করা যাবে।</p>'], 'price' => ($i + 5) * 25000, 'special_price' => $i === 1 ? 100000 : null, 'qty' => $i === 3 ? 0 : 12, 'status' => 'active', 'type' => $i === 0 ? 'variable' : 'simple', 'brand_id' => $brand->id]);
            $p->categories()->syncWithoutDetaching([$category->id]);
            $p->tags()->syncWithoutDetaching([$tag->id]);
            if ($i === 0 && $p->wasRecentlyCreated) {
                $p->attributeValues()->syncWithoutDetaching($attribute->values->modelKeys());
                app(GenerateVariants::class)->execute($p);
                foreach ($p->variants as $v) {
                    if ($v->wasRecentlyCreated) {
                        continue;
                    }
                } $p->variants()->where('qty', 0)->update(['qty' => 8]);
            }
            if (! $p->hasMedia('images')) {
                $image = imagecreatetruecolor(640, 640);
                $bg = imagecolorallocate($image, 225 - $i * 10, 235 - $i * 6, 228 - $i * 3);
                imagefill($image, 0, 0, $bg);
                $ink = imagecolorallocate($image, 6, 95, 70);
                imagefilledellipse($image, 320, 300, 320, 320, imagecolorallocate($image, 205 - $i * 8, 222 - $i * 5, 210));
                imagestring($image, 5, 220, 300, 'STUDIO '.($i + 1), $ink);
                $path = tempnam(sys_get_temp_dir(), 'catalog-');
                imagejpeg($image, $path, 85);
                imagedestroy($image);
                $p->addMedia($path)->usingFileName($slug.'.jpg')->withCustomProperties(['alt' => $en, 'is_demo' => true])->toMediaCollection('images');
            }
        }
        ProductTab::firstOrCreate(['scope' => 'global', 'product_id' => null], ['title' => ['en' => 'Care & delivery', 'bn' => 'যত্ন ও ডেলিভারি'], 'body' => ['en' => '<p>Keep dry and handle with care. Delivery options appear during checkout when available.</p>', 'bn' => '<p>শুকনো রাখুন ও যত্নে ব্যবহার করুন। ডেলিভারি চালু হলে চেকআউটে তথ্য পাবেন।</p>'], 'is_active' => true, 'sort' => 1]);
    }
}
