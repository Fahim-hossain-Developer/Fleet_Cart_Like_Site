<?php

namespace App\Jobs;

use App\Domain\Catalog\Models\Product;
use App\Domain\Customers\Actions\PublishNotice;
use App\Domain\Marketing\Models\Question;
use App\Domain\Marketing\Models\Review;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

final class PublishFeedbackNotice implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public string $kind, public int $recordId) {}

    public function handle(): void
    {
        $class = match ($this->kind) {
            'review' => Review::class,'question' => Question::class,default => null
        };
        if (! $class) {
            return;
        }
        if (! $class::whereKey($this->recordId)->where('notice_pending', true)->where('notice_attempts', '<', 3)->increment('notice_attempts')) {
            return;
        }
        $r = $class::find($this->recordId);
        if (! $r) {
            return;
        }
        try {
            $u = User::find($r->user_id);
            $p = Product::published()->find($r->product_id);
            if (! $u || ! $p || $r->status !== 'approved') {
                $r->update(['notice_pending' => false]);

                return;
            }
            $key = $r instanceof Review ? ($r->admin_reply ? 'marketing.notice_review_reply' : 'marketing.notice_review_approved') : 'marketing.notice_question_answer';
            $text = $r instanceof Review ? ($r->admin_reply ?? 'approved') : ($r->answer ?? '');
            $revision = $r instanceof Review ? $r->revision : 0;
            app(PublishNotice::class)->execute($u, 'reviews', $key, ['product' => $p->name], route('product', $p->slug, false), 'feedback:'.$this->kind.':'.$r->id.':'.$revision.':'.hash('sha256', $text));
            $query = $class::whereKey($r->id)->where('status', 'approved');
            if ($r instanceof Review) {
                $query->where('revision', $r->revision)->where('admin_reply', $r->admin_reply);
            } else {
                $query->where('answer', $r->answer);
            }$query->update(['notice_pending' => false]);
        } catch (\Throwable $e) {
            Log::warning('Feedback notice pending retry', ['kind' => $this->kind, 'record_id' => $r->id]);
        }
    }
}
