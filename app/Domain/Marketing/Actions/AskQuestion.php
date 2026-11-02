<?php

namespace App\Domain\Marketing\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Marketing\Models\Question;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class AskQuestion
{
    /** @param array<string,mixed> $input */
    public function execute(User $u, Product $product, array $input): Question
    {
        app(ReviewRules::class)->owner($u);
        abort_unless($product->status === 'active' && $product->visibility === 'visible', 404);
        $d = validator($input, ['display_name' => 'required|string|max:50', 'question' => 'required|string|min:10|max:2000', 'consent' => 'accepted'])->validate();
        if (! app(ReviewRules::class)->allowedText($d['question'])) {
            throw ValidationException::withMessages(['question' => __('marketing.profanity_rejected')]);
        }

        return Question::create(['user_id' => $u->id, 'product_id' => $product->id, 'display_name' => $d['display_name'], 'question' => $d['question']]);
    }
}
