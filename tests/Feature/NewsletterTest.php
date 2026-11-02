<?php

use App\Domain\Marketing\Actions\Newsletter;
use App\Domain\Marketing\Actions\QueueNewsletterCampaign;
use App\Domain\Marketing\Models\NewsletterCampaign;
use App\Domain\Marketing\Models\NewsletterMessage;
use App\Domain\Marketing\Models\NewsletterSubscriber;
use App\Jobs\DeliverNewsletter;
use App\Jobs\PrepareNewsletterCampaign;
use App\Mail\NewsletterMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

it('requires explicit consent and confirms a private expiring double-opt-in token once', function () {
    Queue::fake();
    Mail::fake();
    $this->post('/newsletter', ['email' => 'subscriber@example.test'])->assertSessionHasErrors('consent');
    $this->post('/newsletter', ['email' => 'subscriber@example.test', 'consent' => 1])->assertSessionHasNoErrors();
    $s = NewsletterSubscriber::firstOrFail();
    $m = NewsletterMessage::firstOrFail();
    $token = $m->payload['token'];
    expect($s->status)->toBe('pending')->and(DB::table('newsletter_subscribers')->value('email'))->not->toContain('subscriber@example.test')->and(DB::table('newsletter_messages')->value('payload'))->not->toContain($token)->and($s->toJson())->not->toContain('subscriber@example.test')->not->toContain($token);
    (new DeliverNewsletter($m->id))->handle();
    Mail::assertSent(NewsletterMail::class, fn ($mail) => str_contains($mail->bodyText, $token));
    expect($m->refresh()->payload)->toBeNull();
    $this->get(route('newsletter.confirm', $token))->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
    expect($s->refresh()->status)->toBe('active');
    $this->get(route('newsletter.confirm', $token))->assertGone();
});
it('checks active confirmed membership again before each queued campaign message', function () {
    Queue::fake();
    Mail::fake();
    $this->actingAs(catalogAdmin('manager'), 'admin');
    $s = NewsletterSubscriber::factory()->create();
    $c = NewsletterCampaign::factory()->create();
    app(QueueNewsletterCampaign::class)->execute($c);
    (new PrepareNewsletterCampaign($c->id))->handle();
    $m = NewsletterMessage::where('kind', 'campaign')->firstOrFail();
    $s->update(['status' => 'unsubscribed', 'unsubscribed_at' => now()]);
    (new DeliverNewsletter($m->id))->handle();
    expect($m->refresh()->status)->toBe('skipped');
    Mail::assertNothingSent();
});
it('prepares an immutable campaign audience once and avoids duplicate accepted messages', function () {
    Queue::fake();
    Mail::fake();
    $this->actingAs(catalogAdmin('manager'), 'admin');
    $first = NewsletterSubscriber::factory()->create();
    $c = NewsletterCampaign::factory()->create();
    app(QueueNewsletterCampaign::class)->execute($c);
    (new PrepareNewsletterCampaign($c->id))->handle();
    NewsletterSubscriber::factory()->create(['confirmed_at' => now()->addMinute()]);
    (new PrepareNewsletterCampaign($c->id))->handle();
    expect(NewsletterMessage::where('kind', 'campaign')->count())->toBe(1);
    $m = NewsletterMessage::where('kind', 'campaign')->firstOrFail();
    (new DeliverNewsletter($m->id))->handle();
    (new DeliverNewsletter($m->id))->handle();
    Mail::assertSentCount(1);
    expect($m->refresh()->status)->toBe('sent');
});
it('supports safe unsubscribe confirmation and the exact stateless one-click endpoint', function () {
    Queue::fake();
    app(Newsletter::class)->subscribe(['email' => 'unsubscribe@example.test', 'consent' => 1]);
    $s = NewsletterSubscriber::firstOrFail();
    $m = NewsletterMessage::firstOrFail();
    app(Newsletter::class)->confirm($m->payload['token']);
    $token = $s->refresh()->unsubscribe_token;
    $this->get(route('newsletter.unsubscribe', $token))->assertOk();
    expect($s->refresh()->status)->toBe('active');
    $this->post('/integrations/newsletter/unsubscribe/'.$token, ['List-Unsubscribe' => 'One-Click'])->assertOk()->assertCookieMissing(config('session.cookie'));
    expect($s->refresh()->status)->toBe('unsubscribed');
});
it('does not spend delivery attempts while an operator has paused a campaign', function () {
    Queue::fake();
    Mail::fake();
    $this->actingAs(catalogAdmin('manager'), 'admin');
    NewsletterSubscriber::factory()->create();
    $c = NewsletterCampaign::factory()->create();
    app(QueueNewsletterCampaign::class)->execute($c);
    (new PrepareNewsletterCampaign($c->id))->handle();
    app(QueueNewsletterCampaign::class)->pause($c);
    $m = NewsletterMessage::where('kind', 'campaign')->firstOrFail();
    for ($i = 0; $i < 4; $i++) {
        (new DeliverNewsletter($m->id))->handle();
    }expect($m->refresh()->attempts)->toBe(0)->and($m->status)->toBe('pending');
    Mail::assertNothingSent();
});
