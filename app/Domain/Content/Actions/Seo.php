<?php

namespace App\Domain\Content\Actions;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Content\Models\FaqEntry;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\Post;
use App\Domain\Marketing\Actions\FlashPricing;

final class Seo
{
    /** @return array<string,mixed> */
    public function data(): array
    {
        $route = request()->route()?->getName();
        $public = app(StorefrontOverlay::class)->allowed(request());
        $locale = app()->getLocale();
        $settings = store_setting('seo_defaults', []);
        $title = $settings['title'][$locale] ?? store_setting('name');
        $description = $settings['description'][$locale] ?? __('store.description');
        $canonical = url()->current();
        $image = null;
        $schema = [];
        $type = 'website';
        $record = null;
        if ($route === 'product') {
            $record = Product::published()->with(['media', 'variants'])->where('slug', request()->route('slug'))->first();
        }
        if ($route === 'page') {
            $record = Page::published()->where('slug', request()->route('slug'))->first();
        }
        if ($route === 'blog.post') {
            $record = Post::published()->where(fn ($q) => $q->whereNull('post_category_id')->orWhereHas('category', fn ($q) => $q->published()))->where('slug', request()->route('slug'))->first();
        }
        if ($route === 'category') {
            $record = Category::where('is_active', true)->where('slug', request()->route('slug'))->first();
        }
        if ($route === 'brand') {
            $record = Brand::where('is_active', true)->where('slug', request()->route('slug'))->first();
        }
        if ($record) {
            $seo = $record->seo ?? [];
            $title = $seo[$locale]['title'] ?? $seo['title'] ?? ($record instanceof Product || $record instanceof Category || $record instanceof Brand ? $record->name : $record->title);
            $description = $seo[$locale]['description'] ?? $seo['description'] ?? strip_tags((string) ($record instanceof Product ? $record->short_desc : ($record->excerpt ?? $record->description ?? '')));
            if (! empty($seo['canonical']) && SafeContent::link($seo['canonical'])) {
                $canonical = url($seo['canonical']);
            }
            if (! empty($seo['image']) && SafeContent::image($seo['image'])) {
                $image = SafeContent::imageUrl($seo['image']);
            }
            $schema[] = ['@type' => 'BreadcrumbList', 'itemListElement' => [['@type' => 'ListItem', 'position' => 1, 'name' => __('store.home'), 'item' => route('home')], ['@type' => 'ListItem', 'position' => 2, 'name' => $title, 'item' => url()->current()]]];
        }
        if ($public && ($settings['schema'] ?? true)) {
            $schema[] = ['@type' => 'Organization', 'name' => store_setting('name'), 'url' => route('home')];
            if ($record instanceof Product) {
                $type = 'product';
                $image = $image ?: $record->getFirstMediaUrl('images');
                $p = ['@type' => 'Product', 'name' => $record->name, 'description' => strip_tags((string) $record->short_desc), 'sku' => $record->sku, 'url' => route('product', $record->slug), 'offers' => ['@type' => $record->type === 'variable' ? 'AggregateOffer' : 'Offer', 'priceCurrency' => config('checkout.base_currency'), 'availability' => 'https://schema.org/'.($record->available() ? 'InStock' : 'OutOfStock'), 'url' => route('product', $record->slug)]];
                if ($record->type === 'variable') {
                    $prices = $record->variants->where('is_active', true)->map(fn ($v) => app(FlashPricing::class)->variant($record, $v));
                    $p['offers']['lowPrice'] = $this->decimal((int) ($prices->min() ?? $record->currentPrice()));
                    $p['offers']['highPrice'] = $this->decimal((int) ($prices->max() ?? $record->currentPrice()));
                    $p['offers']['offerCount'] = $prices->count();
                } else {
                    $p['offers']['price'] = $this->decimal($record->currentPrice());
                }
                if ($image) {
                    $p['image'] = [$image];
                }
                if ($record->rating_count > 0) {
                    $p['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => (string) $record->rating_avg, 'reviewCount' => (int) $record->rating_count, 'bestRating' => '5', 'worstRating' => '1'];
                }
                $schema[] = $p;
            }
            if ($route === 'faq') {
                $faq = FaqEntry::published()->orderBy('sort')->get()->map(fn ($e) => ['@type' => 'Question', 'name' => $e->title, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags((string) $e->body)]])->all();
                if ($faq) {
                    $schema[] = ['@type' => 'FAQPage', 'mainEntity' => $faq];
                }
            }
        } else {
            $schema = [];
        }
        $hreflang = [];
        if ($public) {
            foreach (['en', 'bn'] as $language) {
                $hreflang[$language] = url()->current().'?lang='.$language;
            }
        }

        return ['title' => $title, 'description' => mb_substr($description, 0, 500), 'canonical' => $canonical, 'image' => $image, 'type' => $type, 'public' => $public, 'hreflang' => $hreflang, 'schema' => ['@context' => 'https://schema.org', '@graph' => $schema]];
    }

    public function decimal(int $minor): string
    {
        return intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }
}
