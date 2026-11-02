<?php

namespace App\Domain\Content\Actions;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Content\Models\Banner;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\Slider;
use App\Domain\Marketing\Models\FlashSaleItem;
use Illuminate\Validation\ValidationException;

final class SectionCatalog
{
    public const TYPES = ['hero_slider', 'category_grid', 'product_carousel', 'banner_row', 'brand_logos', 'testimonials', 'blog_posts', 'features', 'newsletter', 'rich_text', 'video', 'countdown', 'product_tabs', 'shop_by_brand', 'shop_by_price', 'social_grid', 'delivery_payment', 'comparison', 'image_hotspots', 'video_gallery', 'store_locations'];

    /** @param array<string,mixed> $config */
    public function validate(string $type, array $config): void
    {
        validator(['type' => $type, 'c' => $config], ['type' => 'required|in:'.implode(',', self::TYPES), 'c' => 'array:source,category_id,tag_id,slider_id,product_ids,limit,items,video_url,ends_at,image,minimum,maximum', 'c.source' => 'nullable|in:new,sale,best_seller,featured,category,tag,flash', 'c.category_id' => 'nullable|integer|exists:categories,id', 'c.tag_id' => 'nullable|integer|exists:tags,id', 'c.slider_id' => 'nullable|integer|exists:sliders,id', 'c.product_ids' => 'nullable|array|max:24', 'c.product_ids.*' => 'integer|distinct|exists:products,id', 'c.limit' => 'nullable|integer|min:1|max:24', 'c.minimum' => 'nullable|integer|min:0|max:100000000000', 'c.maximum' => 'nullable|integer|min:0|max:100000000000', 'c.ends_at' => 'nullable|date', 'c.image' => 'nullable|string|max:512', 'c.video_url' => 'nullable|string|max:2048', 'c.items' => 'nullable|array|max:24', 'c.items.*' => 'array:title,body,image,url,product_id,video_url,price,x,y', 'c.items.*.title' => 'nullable|array:en,bn', 'c.items.*.title.*' => 'nullable|string|max:200', 'c.items.*.body' => 'nullable|array:en,bn', 'c.items.*.body.*' => 'nullable|string|max:2000', 'c.items.*.product_id' => 'nullable|integer|exists:products,id', 'c.items.*.x' => 'nullable|integer|min:0|max:100', 'c.items.*.y' => 'nullable|integer|min:0|max:100', 'c.items.*.price' => 'nullable|integer|min:0|max:100000000000'])->validate();
        if (! SafeContent::image($config['image'] ?? null) || (! empty($config['video_url']) && ! SafeContent::video($config['video_url']))) {
            $this->fail();
        }
        foreach ($config['items'] ?? [] as $item) {
            if (! SafeContent::link($item['url'] ?? null) || ! SafeContent::image($item['image'] ?? null) || (! empty($item['video_url']) && ! SafeContent::video($item['video_url']))) {
                $this->fail();
            }
        }
    }

    private function fail(): never
    {
        throw ValidationException::withMessages(['config' => __('content.invalid_content')]);
    }

    /** @param array<string,mixed> $config
     * @return array<string,mixed> */
    public function data(string $type, array $config): array
    {
        $limit = min(24, max(1, (int) ($config['limit'] ?? 8)));
        $result = ['products' => collect(), 'categories' => collect(), 'brands' => collect(), 'posts' => collect(), 'banners' => collect(), 'slides' => collect()];
        if (in_array($type, ['product_carousel', 'product_tabs', 'image_hotspots'], true)) {
            $q = Product::published()->with(['variants', 'media']);
            if (! empty($config['product_ids'])) {
                $q->whereIn('id', $config['product_ids']);
            }
            if (($config['source'] ?? 'new') === 'category' && ! empty($config['category_id'])) {
                $q->whereHas('categories', fn ($q) => $q->whereKey($config['category_id']));
            }
            if (($config['source'] ?? 'new') === 'tag' && ! empty($config['tag_id'])) {
                $q->whereHas('tags', fn ($q) => $q->whereKey($config['tag_id']));
            }
            if (($config['source'] ?? 'new') === 'featured') {
                $q->where('is_featured', true);
            }
            if (($config['source'] ?? 'new') === 'sale') {
                $q->whereNotNull('special_price')->where(fn ($q) => $q->whereNull('special_from')->orWhere('special_from', '<=', now()))->where(fn ($q) => $q->whereNull('special_to')->orWhere('special_to', '>', now()));
            }
            if (($config['source'] ?? 'new') === 'best_seller') {
                $q->whereIn('id', function ($q) {
                    $q->select('order_items.product_id')->from('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')->where('orders.placed_at', '>=', now()->subDays(30))->whereIn('orders.status', ['delivered', 'completed'])->where('orders.payment_status', 'paid')->where(fn ($q) => $q->whereNull('orders.payment_environment')->orWhere('orders.payment_environment', 'live'));
                });
            }
            if (($config['source'] ?? 'new') === 'flash') {
                $q->whereIn('id', FlashSaleItem::where('is_active', true)->whereColumn('sold', '<', 'quota')->whereHas('sale', fn ($s) => $s->where('is_active', true)->where('starts_at', '<=', now())->where('ends_at', '>', now()))->select('product_id'));
            }
            $result['products'] = $q->latest('id')->limit($limit)->get();
        }
        if ($type === 'category_grid') {
            $result['categories'] = Category::where('is_active', true)->orderBy('sort')->limit($limit)->get();
        }
        if (in_array($type, ['brand_logos', 'shop_by_brand'], true)) {
            $result['brands'] = Brand::where('is_active', true)->limit($limit)->get();
        }
        if ($type === 'blog_posts') {
            $result['posts'] = Post::published()->where(fn ($q) => $q->whereNull('post_category_id')->orWhereHas('category', fn ($q) => $q->published()))->latest('published_at')->limit($limit)->get();
        }
        if ($type === 'banner_row') {
            $result['banners'] = Banner::published()->where('position', 'home')->orderBy('sort')->limit(4)->get();
        }
        if ($type === 'hero_slider') {
            $slider = Slider::published()->when($config['slider_id'] ?? null, fn ($q, $id) => $q->whereKey($id))->orderBy('sort')->first();
            $result['slides'] = $slider ? $slider->slides()->published()->limit(12)->get() : collect();
        }

        return $result;
    }
}
