<?php

namespace App\Domain\Marketing\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Marketing\Models\Question;
use App\Domain\Marketing\Models\Review;
use App\Domain\Orders\Actions\OrderAccess;
use App\Jobs\PublishFeedbackNotice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

final class ManageReviews
{
    private function dispatch(string $kind, int $id): void
    {
        DB::afterCommit(function () use ($kind, $id) {
            try {
                PublishFeedbackNotice::dispatch($kind, $id);
            } catch (\Throwable $e) {
                Log::warning('Feedback notice pending retry', ['kind' => $kind, 'record_id' => $id]);
            }
        });
    }

    public function moderate(Review $review, string $status, ?string $reply): void
    {
        $admin = app(OrderAccess::class)->staff('marketing.manage');
        validator(compact('status', 'reply'), ['status' => 'required|in:pending,approved,rejected', 'reply' => 'nullable|string|max:3000'])->validate();
        DB::transaction(function () use ($review, $status, $reply, $admin) {
            $p = Product::withTrashed()->lockForUpdate()->findOrFail($review->product_id);
            $r = Review::lockForUpdate()->findOrFail($review->id);
            $changed = $status === 'approved' && ($r->status !== 'approved' || $reply !== $r->admin_reply);
            $r->update(['status' => $status, 'admin_reply' => $reply, 'moderated_by' => $admin->id, 'approved_at' => $status === 'approved' ? now() : null, 'notice_pending' => $changed ? true : ($status === 'approved' && $r->notice_pending), 'notice_attempts' => $changed ? 0 : $r->notice_attempts]);
            app(ReviewRules::class)->aggregate($p->id);
            activity()->causedBy($admin)->performedOn($r)->withProperties(['status' => $status])->log('review_moderated');
            if ($r->notice_pending) {
                $this->dispatch('review', $r->id);
            }
        }, 3);
    }

    public function delete(Review $review): void
    {
        $admin = app(OrderAccess::class)->staff('marketing.manage');
        DB::transaction(function () use ($review, $admin) {
            $p = Product::withTrashed()->lockForUpdate()->findOrFail($review->product_id);
            $r = Review::lockForUpdate()->findOrFail($review->id);
            $images = $r->images ?? [];
            activity()->causedBy($admin)->performedOn($r)->log('review_deleted');
            $r->delete();
            app(ReviewRules::class)->aggregate($p->id);
            DB::afterCommit(fn () => Storage::disk('public')->delete($images));
        }, 3);
    }

    public function answer(Question $question, string $status, ?string $answer): void
    {
        $admin = app(OrderAccess::class)->staff('marketing.manage');
        validator(compact('status', 'answer'), ['status' => 'required|in:pending,approved,rejected', 'answer' => 'nullable|required_if:status,approved|string|max:3000'])->validate();
        DB::transaction(function () use ($question, $status, $answer, $admin) {
            $q = Question::lockForUpdate()->findOrFail($question->id);
            $changed = $status === 'approved' && ($q->status !== 'approved' || $answer !== $q->answer);
            $q->update(['status' => $status, 'answer' => $answer, 'answered_by' => $admin->id, 'notice_pending' => $changed ? true : ($status === 'approved' && $q->notice_pending), 'notice_attempts' => $changed ? 0 : $q->notice_attempts]);
            activity()->causedBy($admin)->performedOn($q)->withProperties(['status' => $status])->log('question_answered');
            if ($q->notice_pending) {
                $this->dispatch('question', $q->id);
            }
        }, 3);
    }
}
