<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Customers\Models\NoticeDelivery;
use App\Domain\Customers\Models\StoreNotification;
use App\Domain\Marketing\Actions\ManageReviews;
use App\Domain\Marketing\Actions\SubmitReview;
use App\Domain\Marketing\Models\Question;
use App\Domain\Marketing\Models\Review;
use App\Domain\Marketing\Models\ReviewRequest;
use App\Domain\Marketing\Models\ReviewVote;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Settings\Actions\Settings;
use App\Filament\Resources\ReviewResource\Pages\ManageRecords;
use App\Jobs\PrepareReviewRequests;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function feedbackInput(): array
{
    return ['display_name' => 'A shopper', 'rating' => 5, 'title' => 'A useful product', 'body' => 'This product worked well for my everyday needs.', 'consent' => 1];
}
function fulfilledFeedbackOrder(User $u, Product $p, ?string $mode = null): Order
{
    $o = Order::factory()->create(['user_id' => $u->id, 'status' => 'delivered', 'fulfillment_status' => 'fulfilled', 'total' => 100000, 'paid_amount' => 100000, 'payment_status' => 'paid', 'payment_environment' => $mode]);
    OrderItem::factory()->create(['order_id' => $o->id, 'product_id' => $p->id, 'qty' => 1, 'unit_price' => 100000, 'subtotal' => 100000, 'total' => 100000]);

    return $o;
}
it('requires actual verified purchase evidence and does not trust a submitted verified badge', function () {
    Queue::fake();
    $u = User::factory()->create();
    $p = Product::factory()->create();
    $this->actingAs($u, 'web')->post(route('product.review', $p->id), feedbackInput() + ['is_verified' => true])->assertSessionHasErrors('review');
    expect(Review::count())->toBe(0);
    fulfilledFeedbackOrder($u, $p);
    $this->post(route('product.review', $p->id), feedbackInput())->assertRedirect();
    $r = Review::firstOrFail();
    expect($r->is_verified)->toBeTrue()->and($r->status)->toBe('pending')->and($p->refresh()->rating_count)->toBe(0);
    auth('web')->logout();
    $this->flushSession();
    $this->get(route('product', $p->slug))->assertOk()->assertDontSee('This product worked well for my everyday needs.');
});
it('excludes sandbox purchases from verified reviews and supports one moderated revision per customer', function () {
    Queue::fake();
    $u = User::factory()->create();
    $p = Product::factory()->create();
    fulfilledFeedbackOrder($u, $p, 'sandbox');
    $this->actingAs($u, 'web')->post(route('product.review', $p->id), feedbackInput())->assertSessionHasErrors('review');
    app(Settings::class)->set('reviews_require_verified', false);
    app(Settings::class)->set('reviews_moderate', false);
    $this->post(route('product.review', $p->id), feedbackInput())->assertSessionHasNoErrors();
    $r = Review::firstOrFail();
    expect($r->is_verified)->toBeFalse()->and($p->refresh()->rating_count)->toBe(1);
    app(Settings::class)->set('reviews_moderate', true);
    $this->post(route('product.review', $p->id), array_replace(feedbackInput(), ['rating' => 2]))->assertSessionHasNoErrors();
    expect(Review::count())->toBe(1)->and($r->refresh()->rating)->toBe(2)->and($r->revision)->toBe(2)->and($p->refresh()->rating_count)->toBe(0);
});
it('moderates reviews through staff controls and publishes accurate ratings replies and useful votes', function () {
    Queue::fake();
    $u = User::factory()->create();
    $p = Product::factory()->create();
    fulfilledFeedbackOrder($u, $p);
    $r = app(SubmitReview::class)->execute($u, $p, feedbackInput());
    $this->actingAs(catalogAdmin('manager'), 'admin');
    Livewire::test(ManageRecords::class)->callTableAction('edit', $r, data: ['status' => 'approved', 'admin_reply' => 'Thank you for sharing your experience.'])->assertHasNoTableActionErrors();
    expect($r->refresh()->status)->toBe('approved')->and($p->refresh()->rating_count)->toBe(1)->and(number_format((float) $p->rating_avg, 2, '.', ''))->toBe('5.00');
    $this->get(route('product', $p->slug))->assertOk()->assertSee('Verified purchase')->assertSee('Thank you for sharing your experience.');
    $this->actingAs($u, 'web')->post(route('review.vote', $r->id), ['helpful' => true])->assertForbidden();
    $other = User::factory()->create();
    $this->actingAs($other, 'web')->post(route('review.vote', $r->id), ['helpful' => true])->assertRedirect();
    $this->post(route('review.vote', $r->id), ['helpful' => true]);
    expect(ReviewVote::count())->toBe(1);
    app(ManageReviews::class)->delete($r);
    expect($p->refresh()->rating_count)->toBe(0);
});
it('stores only reencoded review images and rejects unverified users and prohibited wording', function () {
    Queue::fake();
    Storage::fake('public');
    app(Settings::class)->set('reviews_require_verified', false);
    $u = User::factory()->create();
    $p = Product::factory()->create();
    $input = feedbackInput() + ['photos' => [UploadedFile::fake()->image('original.jpg', 120, 120)]];
    $r = app(SubmitReview::class)->execute($u, $p, $input);
    expect($r->images)->toHaveCount(1);
    expect($r->images[0])->toMatch('/^reviews\/.*\.webp$/');
    Storage::disk('public')->assertExists($r->images[0]);
    app(Settings::class)->set('reviews_blocked_words', ['forbidden']);
    $this->actingAs($u, 'web')->post(route('product.review', $p->id), array_replace(feedbackInput(), ['body' => 'This text contains forbidden wording.']))->assertSessionHasErrors('body');
    $unverified = User::factory()->unverified()->create();
    $this->actingAs($unverified, 'web')->post(route('product.review', $p->id), feedbackInput())->assertSessionHasErrors('review');
});
it('keeps questions private until answered and rejects non-marketing staff moderation', function () {
    Queue::fake();
    $u = User::factory()->create();
    $p = Product::factory()->create();
    $this->actingAs($u, 'web')->post(route('product.question', $p->id), ['display_name' => 'A shopper', 'question' => 'Does this product suit everyday use?', 'consent' => 1])->assertRedirect();
    $q = Question::firstOrFail();
    auth('web')->logout();
    $this->flushSession();
    $this->get(route('product', $p->slug))->assertDontSee('Does this product suit everyday use?');
    $this->actingAs(catalogAdmin('manager'), 'admin');
    app(ManageReviews::class)->answer($q, 'approved', 'Please read the product specifications for suitability.');
    $this->get(route('product', $p->slug))->assertSee('Does this product suit everyday use?')->assertSee('Please read the product specifications');
    $this->flushSession();
    $this->actingAs(catalogAdmin('support'), 'admin')->get('/admin/reviews')->assertForbidden();
});
it('prepares one consent-aware review request only for recent real fulfilled orders', function () {
    Queue::fake();
    $u = User::factory()->create();
    $p = Product::factory()->create();
    $o = fulfilledFeedbackOrder($u, $p);
    $o->events()->create(['type' => 'delivered', 'note' => 'Delivered', 'created_at' => now()->subDays(4)]);
    (new PrepareReviewRequests)->handle();
    expect(ReviewRequest::count())->toBe(0);
    app(Settings::class)->set('reviews_request_enabled', true);
    (new PrepareReviewRequests)->handle();
    (new PrepareReviewRequests)->handle();
    expect(ReviewRequest::count())->toBe(1)->and(StoreNotification::count())->toBe(1)->and(NoticeDelivery::count())->toBe(0);
});
