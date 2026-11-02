<?php

use App\Domain\Content\Models\Page;
use App\Domain\Settings\Actions\CredentialVault;
use Illuminate\Support\Facades\Queue;

it('keeps integration credentials out of public marketing content and built scripts', function () {
    Queue::fake();
    $values = [];
    foreach (['social.google.client_secret', 'social.facebook.client_secret', 'tracking.meta_token', 'smtp.password', 'courier_pathao.live.api_key'] as $key) {
        $value = 'private-canary-'.hash('sha256', $key);
        $values[] = $value;
        app(CredentialVault::class)->replace($key, $value);
    }
    $f = checkoutFixture();
    $page = Page::factory()->create();
    foreach (['/', '/shop', '/product/'.$f['product']->slug, '/cart', '/checkout', '/blog', '/page/'.$page->slug, '/faq', '/offers', '/contact', '/login', '/wishlist', '/compare'] as $path) {
        $response = $this->get($path)->assertOk();
        foreach ($values as $value) {
            $response->assertDontSee($value, false);
        }
    }
    foreach (glob(public_path('build/assets/*.js')) as $path) {
        $js = file_get_contents($path);
        foreach ($values as $value) {
            expect($js)->not->toContain($value);
        }
    }
});
