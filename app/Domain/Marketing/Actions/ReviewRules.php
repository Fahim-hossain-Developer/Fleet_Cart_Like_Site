<?php

namespace App\Domain\Marketing\Actions;

use App\Domain\Marketing\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReviewRules
{
    public function owner(User $u): void
    {
        abort_if($u->is_blocked, 403);
        if (! $u->email_verified_at && ! $u->phone_verified_at) {
            throw ValidationException::withMessages(['review' => __('marketing.verify_before_review')]);
        }
    }

    public function allowedText(string $text): bool
    {
        foreach ((array) store_setting('reviews_blocked_words', []) as $word) {
            if (is_string($word) && trim($word) !== '' && mb_stripos($text, trim($word)) !== false) {
                return false;
            }
        }

        return true;
    }

    public function aggregate(int $productId): void
    {
        $query = Review::where('product_id', $productId)->where('status', 'approved');
        $count = $query->count();
        $sum = (int) $query->sum('rating');
        $average = $count ? number_format(intdiv($sum * 100 + intdiv($count, 2), $count) / 100, 2, '.', '') : '0.00';
        DB::table('products')->where('id', $productId)->update(['rating_count' => $count, 'rating_avg' => $average]);
    }
}
