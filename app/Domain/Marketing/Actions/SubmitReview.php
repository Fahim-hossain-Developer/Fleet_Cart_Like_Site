<?php

namespace App\Domain\Marketing\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Marketing\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class SubmitReview
{
    /** @param array<string,mixed> $input */
    public function execute(User $u, Product $product, array $input): Review
    {
        app(ReviewRules::class)->owner($u);
        abort_unless($product->status === 'active' && $product->visibility === 'visible', 404);
        $minimum = max(10, min(1000, (int) store_setting('reviews_min_length', 20)));
        $maxPhotos = max(0, min(5, (int) store_setting('reviews_max_photos', 3)));
        $d = validator($input, ['display_name' => 'required|string|max:50', 'rating' => 'required|integer|min:1|max:5', 'title' => 'required|string|max:120', 'body' => 'required|string|min:'.$minimum.'|max:5000', 'photos' => 'nullable|array|max:'.$maxPhotos, 'photos.*' => 'file', 'remove_photos' => 'sometimes|boolean', 'consent' => 'accepted'])->validate();
        if (! app(ReviewRules::class)->allowedText($d['title'].' '.$d['body'])) {
            throw ValidationException::withMessages(['body' => __('marketing.profanity_rejected')]);
        }
        if (! empty($d['photos']) && ! store_setting('reviews_allow_photos', true)) {
            throw ValidationException::withMessages(['photos' => __('marketing.photos_disabled')]);
        }
        $new = app(ReviewPhotos::class)->store($d['photos'] ?? []);
        $old = [];
        try {
            return DB::transaction(function () use ($u, $product, $d, $new, &$old, $maxPhotos) {
                Product::published()->lockForUpdate()->findOrFail($product->id);
                $purchase = app(VerifiedPurchase::class)->item($u, $product->id);
                if (store_setting('reviews_require_verified', true) && ! $purchase) {
                    throw ValidationException::withMessages(['review' => __('marketing.purchase_required')]);
                }
                $review = Review::where('product_id', $product->id)->where('user_id', $u->id)->lockForUpdate()->first();
                $existing = $review->images ?? [];
                $images = array_merge(! empty($d['remove_photos']) ? [] : $existing, $new);
                if (count($images) > $maxPhotos && $new) {
                    throw ValidationException::withMessages(['photos' => __('marketing.photo_limit')]);
                }if (! empty($d['remove_photos'])) {
                    $old = $existing;
                }
                $review ??= new Review(['product_id' => $product->id, 'user_id' => $u->id]);
                $review->fill(['display_name' => $d['display_name'], 'rating' => $d['rating'], 'title' => $d['title'], 'body' => $d['body'], 'images' => $images, 'order_item_id' => $purchase?->id, 'is_verified' => $purchase !== null, 'status' => store_setting('reviews_moderate', true) ? 'pending' : 'approved', 'approved_at' => store_setting('reviews_moderate', true) ? null : now(), 'revision' => ($review->revision ?? 0) + 1, 'moderated_by' => null, 'admin_reply' => null, 'notice_pending' => false]);
                $review->save();
                app(ReviewRules::class)->aggregate($product->id);
                DB::afterCommit(fn () => Storage::disk('public')->delete($old));

                return $review;
            }, 3);
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($new);
            throw $e;
        }
    }
}
