<?php

namespace App\Http\Controllers;

use App\Domain\Catalog\Models\Product;
use App\Domain\Checkout\Landing\CheckoutPhoneProof;
use App\Domain\Checkout\Landing\LandingCart;
use App\Domain\Checkout\Landing\LandingCheckout;
use App\Domain\Checkout\Landing\LandingOffers;
use App\Domain\Content\Landing\LandingTemplates;
use App\Domain\Content\Models\LandingPage;
use App\Domain\Content\Models\LandingTemplate;
use App\Domain\Content\Models\ProductPackage;
use App\Domain\Marketing\Actions\CaptureRecoveryContact;
use App\Domain\Marketing\Actions\FlashPricing;
use App\Domain\Marketing\Actions\Tracking;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Actions\PaymentMethods;
use App\Domain\Shipping\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class LandingController extends Controller
{
    private function locale(): void
    {
        $l = request()->input('_locale', request()->query('lang'));
        if (is_string($l) && in_array($l, ['en', 'bn'], true)) {
            app()->setLocale($l);
        }
    }

    public function page(string $slug): LandingPage
    {
        $this->locale();
        $page = LandingPage::published()->where('slug', $slug)->firstOrFail();
        abort_unless(Product::published()->whereKey($page->product_id)->exists(), 404);

        return $page;
    }

    public function show(string $slug): mixed
    {
        $page = $this->page($slug);
        $product = Product::published()->with(['media', 'variants', 'options.values'])->findOrFail($page->product_id);
        $packages = $page->packages()->where('role', 'primary')->where('is_active', true)->get();
        abort_if($packages->isEmpty(), 404);
        DB::table('landing_pages')->where('id', $page->id)->increment('views');
        app(Tracking::class)->product($product);
        $key = session('landing_keys.'.$page->id);
        if (! is_string($key)) {
            $key = (string) Str::uuid();
            session(['landing_keys.'.$page->id => $key]);
        }
        $bump = ! empty($page->form_config['bump_package_id']) ? ProductPackage::where('landing_page_id', $page->id)->where('role', 'bump')->where('is_active', true)->find($page->form_config['bump_package_id']) : null;

        return response()->view('themes.default.landing.page', ['page' => $page, 'product' => $product, 'packages' => $packages, 'bumpPackage' => $bump, 'key' => $key, 'divisions' => Location::where('type', 'division')->where('country', 'BD')->get(), 'paymentMethods' => app(PaymentMethods::class)->current()])->header('Cache-Control', 'private, no-store');
    }

    public function quick(string $slug, int $id): mixed
    {
        $this->page($slug);
        $p = Product::published()->with(['media', 'variants'])->findOrFail($id);
        $single = store_setting('store_mode', 'multi') === 'single';
        $lp = $single ? LandingPage::published()->where('product_id', $p->id)->whereHas('packages', fn ($q) => $q->where('role', 'primary')->where('is_active', true))->first() : null;
        abort_if($single && ! $lp, 404);
        $variants = $p->variants->where('is_active', true)->map(fn ($v) => ['id' => $v->id, 'label' => catalog_variant_label($v), 'price' => catalog_money(app(FlashPricing::class)->variant($p, $v)), 'qty' => $v->qty])->values();

        return response()->json(['id' => $p->id, 'name' => $p->name, 'description' => strip_tags($p->short_desc ?? ''), 'price' => catalog_money($p->displayPrice()), 'image' => $p->getFirstMediaUrl('images', 'card'), 'variants' => $variants, 'variable' => $p->type === 'variable', 'qty' => $p->qty, 'can_cart' => ! $single && $p->available() && ! $p->options()->where('required', true)->exists(), 'url' => $lp ? route('landing.show', $lp->slug) : route('product', $p->slug)])->header('Cache-Control', 'private, no-store');
    }

    public function methods(Request $r, string $slug): mixed
    {
        return response()->json(app(LandingCheckout::class)->methods($this->page($slug), $r->all()))->header('Cache-Control', 'no-store');
    }

    public function quote(Request $r, string $slug): mixed
    {
        return response()->json(app(LandingCheckout::class)->quote($this->page($slug), $r->all()))->header('Cache-Control', 'no-store');
    }

    public function place(Request $r, string $slug): mixed
    {
        $page = $this->page($slug);
        $order = app(LandingCheckout::class)->place($page, $r->all());
        $ids = (array) session('placed_orders', []);
        $ids[] = $order->id;
        session(['placed_orders' => array_slice(array_unique($ids), -20), 'landing_orders.'.$page->id => $order->id]);
        $r->session()->forget('landing_keys.'.$page->id);
        $redirect = route('landing.thanks', [$page->slug, 'lang' => app()->getLocale()]);

        return $r->expectsJson() ? response()->json(['redirect' => $redirect]) : redirect($redirect);
    }

    public function thanks(string $slug): mixed
    {
        $this->locale();
        $page = LandingPage::where('slug', $slug)->firstOrFail();
        $id = session('landing_orders.'.$page->id);
        $order = Order::where('landing_page_id', $page->id)->with('items')->findOrFail($id);
        abort_unless(in_array($order->id, (array) session('placed_orders', []), true) || ($order->user_id && auth('web')->id() === $order->user_id), 403);

        $offer = app(LandingOffers::class)->current($order);

        return response()->view('themes.default.landing.thanks', compact('page', 'order', 'offer'))->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function offer(Request $r, string $slug): mixed
    {
        $this->locale();
        $page = LandingPage::where('slug', $slug)->firstOrFail();
        $order = Order::where('landing_page_id', $page->id)->findOrFail(session('landing_orders.'.$page->id));
        $d = $r->validate(['stage' => 'required|in:upsell,downsell', 'accept' => 'required|boolean', 'hash' => 'nullable|string|size:64']);
        app(LandingOffers::class)->decide($order, $d['stage'], (bool) $d['accept'], $d['hash'] ?? null);

        return redirect()->route('landing.thanks', [$slug, 'lang' => app()->getLocale()]);
    }

    public function sendCode(Request $r, string $slug): mixed
    {
        $page = $this->page($slug);
        abort_unless(! empty($page->form_config['phone_otp']), 404);
        $d = $r->validate(['phone' => 'required|string|max:32']);
        app(CheckoutPhoneProof::class)->send(app(LandingCart::class)->current($page), $d['phone']);

        return response()->json(['queued' => true]);
    }

    public function verifyCode(Request $r, string $slug): mixed
    {
        $page = $this->page($slug);
        abort_unless(! empty($page->form_config['phone_otp']), 404);
        $d = $r->validate(['phone' => 'required|string|max:32', 'code' => 'required|digits:6']);
        app(CheckoutPhoneProof::class)->verify(app(LandingCart::class)->current($page), $d['phone'], $d['code']);

        return response()->json(['verified' => true]);
    }

    public function recovery(Request $r, string $slug): mixed
    {
        $page = $this->page($slug);
        $c = app(CaptureRecoveryContact::class)->execute(app(LandingCart::class)->current($page), $r->all());

        return response()->json(['saved' => $c !== null, 'email_verified' => (bool) $c?->email_verified_at]);
    }

    public function touched(string $slug): mixed
    {
        $page = $this->page($slug);
        $key = session('landing_keys.'.$page->id);
        abort_unless(is_string($key), 422);
        app(Tracking::class)->checkout(app(LandingCart::class)->current($page), $page->product_id, 'landing:'.$key);

        return response()->json(['recorded' => true]);
    }

    public function exportTemplate(int $id): mixed
    {
        $template = LandingTemplate::findOrFail($id);

        return response(app(LandingTemplates::class)->export($template), 200, ['Content-Type' => 'application/json; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="landing-template.json"', 'Cache-Control' => 'private, no-store']);
    }
}
