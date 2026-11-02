<?php

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Settings\Actions\CredentialVault;
use App\Domain\Settings\Models\Setting;
use Illuminate\Support\Facades\DB;

it('encrypts stored credentials, masks them and excludes plaintext from serialization and audit records', function () {
    $vault = app(CredentialVault::class);
    $sentinel = 'sk_live_CATALOG_TEST_SENTINEL_9f6c';
    $vault->replace('payment', $sentinel);
    expect($vault->get('payment'))->toBe($sentinel)->and($vault->masked('payment'))->toBe('••••9f6c');
    expect(DB::table('settings')->where('key', 'credential.payment')->value('encrypted_value'))->not->toContain($sentinel);
    expect(Setting::where('key', 'credential.payment')->first()->toJson())->not->toContain($sentinel);
    expect(DB::table('activity_log')->get()->toJson())->not->toContain($sentinel);
});
it('crawls existing storefront routes for credential leaks and provides the baseline for later route coverage', function () {
    $sentinel = 'sk_live_CATALOG_TEST_SENTINEL_9f6c';
    app(CredentialVault::class)->replace('payment', $sentinel);
    $p = Product::factory()->create();
    $c = Category::factory()->create();
    $p->categories()->attach($c);
    foreach (['/', '/shop', '/category/'.$c->slug, '/product/'.$p->slug, '/search?q=test', '/cart'] as $route) {
        $html = $this->get($route)->assertOk()->getContent();
        expect($html)->not->toContain($sentinel)->not->toContain('sk_live_')->not->toContain('private_key');
    }
    // Add cart, checkout and landing routes when those phases introduce them.
});
