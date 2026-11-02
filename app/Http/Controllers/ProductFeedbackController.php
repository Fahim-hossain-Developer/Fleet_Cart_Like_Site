<?php

namespace App\Http\Controllers;

use App\Domain\Catalog\Models\Product;
use App\Domain\Marketing\Actions\AskQuestion;
use App\Domain\Marketing\Actions\ReviewVote;
use App\Domain\Marketing\Actions\SubmitReview;
use App\Domain\Marketing\Models\Question;
use App\Domain\Marketing\Models\Review;
use Illuminate\Http\Request;

final class ProductFeedbackController extends Controller
{
    public function review(Request $r, Product $product): mixed
    {
        $review = app(SubmitReview::class)->execute(auth('web')->user(), $product, $r->all());

        return redirect(route('product', $product->slug).'#reviews')->with('status', __('marketing.review_saved', ['status' => __('marketing.'.$review->status)]));
    }

    public function question(Request $r, Product $product): mixed
    {
        app(AskQuestion::class)->execute(auth('web')->user(), $product, $r->all());

        return redirect(route('product', $product->slug).'#questions')->with('status', __('marketing.question_saved'));
    }

    public function vote(Request $r, Review $review): mixed
    {
        $d = $r->validate(['helpful' => 'required|boolean']);
        app(ReviewVote::class)->set(auth('web')->user(), $review, $r->boolean('helpful'));

        return back();
    }

    public function account(): mixed
    {
        return view('themes.default.account.reviews', ['reviews' => Review::where('user_id', auth('web')->id())->latest()->paginate(12, ['*'], 'reviews_page'), 'questions' => Question::where('user_id', auth('web')->id())->latest()->paginate(12, ['*'], 'questions_page')]);
    }
}
