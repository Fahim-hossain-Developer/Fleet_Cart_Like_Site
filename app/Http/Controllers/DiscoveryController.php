<?php

namespace App\Http\Controllers;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Content\Actions\Seo;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\Post;
use App\Domain\Marketing\Actions\FlashPricing;
use Illuminate\Database\Eloquent\Builder;

final class DiscoveryController extends Controller
{
    /** @return \Illuminate\Database\Eloquent\Builder<*> */
    private function query(string $type): Builder
    {
        return match ($type) {
            'products' => Product::published(), 'categories' => Category::where('is_active', true), 'brands' => Brand::where('is_active', true), 'pages' => Page::published(), 'posts' => Post::published()->where(fn ($q) => $q->whereNull('post_category_id')->orWhereHas('category', fn ($q) => $q->published())), default => abort(404)
        };
    }

    private function xml(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    public function index(): mixed
    {
        return response()->stream(function () {
            echo '<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
            foreach (['products', 'categories', 'brands', 'pages', 'posts'] as $type) {
                for ($page = 1; $page <= max(1, (int) ceil($this->query($type)->count() / 1000)); $page++) {
                    echo '<sitemap><loc>'.$this->xml(route('sitemap.type', [$type, $page])).'</loc></sitemap>';
                }
            }
            echo '</sitemapindex>';
        }, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function sitemap(string $type, int $page): mixed
    {
        abort_if($page < 1 || $page > max(1, (int) ceil($this->query($type)->count() / 1000)), 404);

        return response()->stream(function () use ($type, $page) {
            echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">';
            if ($type === 'pages' && $page === 1) {
                foreach (['home', 'shop', 'blog', 'faq', 'contact', 'offers'] as $route) {
                    echo '<url><loc>'.$this->xml(route($route)).'</loc></url>';
                }
            }
            foreach ($this->query($type)->orderBy('id')->offset(($page - 1) * 1000)->limit(1000)->get() as $item) {
                /** @var Product|Category|Brand|Page|Post $item */
                $route = match ($type) {
                    'products' => 'product','categories' => 'category','brands' => 'brand','pages' => 'page','posts' => 'blog.post', default => abort(404)
                };
                $url = route($route, $item->slug);
                echo '<url><loc>'.$this->xml($url).'</loc><lastmod>'.$item->updated_at->toAtomString().'</lastmod>';
                foreach (['en', 'bn'] as $locale) {
                    echo '<xhtml:link rel="alternate" hreflang="'.$locale.'" href="'.$this->xml($url.'?lang='.$locale).'"/>';
                }
                echo '</url>';
            }echo '</urlset>';
        }, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): mixed
    {
        return response("User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /account\nDisallow: /checkout\nDisallow: /cart\nDisallow: /payments\nDisallow: /integrations\nDisallow: /newsletter/confirm\nDisallow: /newsletter/unsubscribe\nDisallow: /cart-recovery\nDisallow: /login\nDisallow: /register\nDisallow: /email-code\nDisallow: /phone-code\nDisallow: /reset-password\nDisallow: /track-order\nDisallow: /orders\nSitemap: ".route('sitemap.index')."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function feed(string $format): mixed
    {
        abort_unless(in_array($format, ['google', 'meta'], true), 404);

        return response()->stream(function () {
            echo '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0" xmlns:g="http://base.google.com/ns/1.0"><channel><title>'.$this->xml(store_setting('name')).'</title><link>'.$this->xml(route('home')).'</link><description>Product catalog</description>';
            Product::published()->with(['variants', 'media', 'brand'])->orderBy('id')->chunkById(100, function ($products) {
                foreach ($products as $p) {
                    $image = $p->getFirstMediaUrl('images');
                    if (! $image) {
                        continue;
                    }
                    $variants = $p->type === 'variable' ? $p->variants->where('is_active', true) : collect([null]);
                    foreach ($variants as $v) {
                        $minor = $v ? app(FlashPricing::class)->variant($p, $v) : $p->currentPrice();
                        $data = ['id' => $p->id.($v ? '-'.$v->id : ''), 'title' => $p->name, 'description' => strip_tags((string) ($p->short_desc ?: $p->name)), 'link' => route('product', $p->slug).($v ? '?variant='.$v->id : ''), 'image_link' => $image, 'availability' => ($v ? $v->qty > 0 : $p->available()) ? 'in_stock' : 'out_of_stock', 'price' => app(Seo::class)->decimal($minor).' '.config('checkout.base_currency'), 'condition' => 'new'];
                        if ($v) {
                            $data['item_group_id'] = (string) $p->id;
                        }
                        if ($p->brand && $p->brand->is_active) {
                            $data['brand'] = $p->brand->name;
                        }
                        // GTIN/MPN are intentionally omitted when no verified identifier is recorded.
                        echo '<item>';
                        foreach ($data as $key => $value) {
                            echo '<g:'.$key.'>'.$this->xml((string) $value).'</g:'.$key.'>';
                        }echo '</item>';
                    }
                }
            });
            echo '</channel></rss>';
        }, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
