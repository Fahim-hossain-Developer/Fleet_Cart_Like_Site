<?php

use App\Domain\Customers\Models\SocialIdentity;
use App\Domain\Settings\Actions\CredentialVault;
use App\Domain\Settings\Actions\Settings;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

function enableSocialProvider(string $p = 'google'): void
{
    app(CredentialVault::class)->replace('social.'.$p.'.client_id', 'test-client');
    app(CredentialVault::class)->replace('social.'.$p.'.client_secret', 'private-oauth-example-token');
    app(Settings::class)->set('social_login_'.$p, true);
}
function socialStartState(string $p = 'google', bool $link = false): string
{
    $response = test()->get(route('social.start', [$p, 'link' => $link ? 1 : 0]))->assertRedirect();
    parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $q);
    expect($q['state'])->toHaveLength(64);

    return $q['state'];
}
function fakeGoogleProfile(string $subject = 'google-subject-1', string $email = 'social@example.test'): void
{
    Http::preventStrayRequests();
    Http::fake(['https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'ephemeral-access-token']), 'https://openidconnect.googleapis.com/v1/userinfo' => Http::response(['sub' => $subject, 'email' => $email, 'email_verified' => true, 'name' => 'Social Shopper'])]);
}
it('hides unconfigured providers and rejects forged or replayed state before any provider call', function () {
    $this->get('/login')->assertOk()->assertDontSee('Continue with Google');
    $this->get(route('social.start', 'google'))->assertNotFound();
    enableSocialProvider();
    fakeGoogleProfile();
    $state = socialStartState();
    $this->get(route('social.callback', ['google', 'state' => 'forged', 'code' => 'fake-code']))->assertStatus(419);
    Http::assertNothingSent();
    $this->get(route('social.callback', ['google', 'state' => $state, 'code' => 'fake-code']))->assertStatus(419);
    Http::assertNothingSent();
});
it('creates one private provider identity with a fresh account from verified Google userinfo', function () {
    enableSocialProvider();
    fakeGoogleProfile();
    $state = socialStartState();
    $this->get(route('social.callback', ['google', 'state' => $state, 'code' => 'fake-code']))->assertRedirect(route('account'));
    $u = User::firstOrFail();
    $identity = SocialIdentity::firstOrFail();
    expect($u->email_verified_at)->not->toBeNull()->and($identity->user_id)->toBe($u->id);
    expect(DB::table('social_identities')->value('subject'))->not->toContain('google-subject-1');
    expect($identity->toJson())->not->toContain('google-subject-1');
    $this->assertAuthenticatedAs($u, 'web');
    $this->assertGuest('admin');
    Http::assertSent(fn ($r) => $r->url() === 'https://oauth2.googleapis.com/token' && $r['grant_type'] === 'authorization_code' && strlen($r['code_verifier']) === 96);
});
it('never attaches a provider to an existing account based on matching email alone', function () {
    enableSocialProvider();
    fakeGoogleProfile();
    $existing = User::factory()->create(['email' => 'social@example.test']);
    $state = socialStartState();
    $this->get(route('social.callback', ['google', 'state' => $state, 'code' => 'fake-code']))->assertSessionHasErrors('social');
    expect(SocialIdentity::count())->toBe(0)->and(User::count())->toBe(1);
    $this->assertGuest('web');
});
it('requires fresh contact proof for explicit linking and rejects identity collisions', function () {
    enableSocialProvider();
    fakeGoogleProfile();
    $u = User::factory()->create();
    $this->actingAs($u, 'web')->get(route('social.start', ['google', 'link' => 1]))->assertForbidden();
    $this->withSession(['customer_email_verified_until' => now()->addMinutes(5)->timestamp]);
    $state = socialStartState('google', true);
    $this->get(route('social.callback', ['google', 'state' => $state, 'code' => 'fake-code']))->assertRedirect(route('account'));
    expect(SocialIdentity::firstOrFail()->user_id)->toBe($u->id);
    $this->post('/logout');
    $other = User::factory()->create();
    $this->actingAs($other, 'web')->withSession(['customer_email_verified_until' => now()->addMinutes(5)->timestamp]);
    $state = socialStartState('google', true);
    $this->get(route('social.callback', ['google', 'state' => $state, 'code' => 'fake-code']))->assertStatus(409);
    expect(SocialIdentity::count())->toBe(1);
});
it('checks the Facebook token app and subject before using a profile and does not trust its email as verified', function () {
    enableSocialProvider('facebook');
    Http::preventStrayRequests();
    Http::fake(['https://graph.facebook.com/v26.0/oauth/access_token' => Http::response(['access_token' => 'fb-access']), 'https://graph.facebook.com/v26.0/debug_token*' => Http::response(['data' => ['is_valid' => true, 'app_id' => 'test-client', 'user_id' => '1234', 'expires_at' => now()->addHour()->timestamp]]), 'https://graph.facebook.com/v26.0/me*' => Http::response(['id' => '1234', 'name' => 'FB Customer', 'email' => 'fb@example.test'])]);
    $state = socialStartState('facebook');
    $this->get(route('social.callback', ['facebook', 'state' => $state, 'code' => 'fb-code']))->assertRedirect(route('account'));
    expect(User::firstOrFail()->email)->toBeNull()->and(User::firstOrFail()->email_verified_at)->toBeNull();
});
it('keeps OAuth credentials out of the customer HTML and limits key configuration to root', function () {
    enableSocialProvider();
    $this->get('/login')->assertOk()->assertSee('Continue with Google')->assertDontSee('private-oauth-example-token')->assertDontSee('test-client');
    $this->actingAs(catalogAdmin('manager'), 'admin')->get('/admin/social-login-settings')->assertForbidden();
});
