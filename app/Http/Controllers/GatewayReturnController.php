<?php

namespace App\Http\Controllers;

use App\Domain\Payments\Actions\CallbackToken;
use App\Domain\Payments\Actions\PaymentEngine;
use App\Domain\Payments\Models\GatewayAttempt;
use Illuminate\Http\Request;

final class GatewayReturnController extends Controller
{
    public function __invoke(Request $request, GatewayAttempt $attempt, string $expires, string $token): mixed
    {
        abort_unless(app(CallbackToken::class)->valid($attempt, (int) $expires, $token), 403);
        abort_if(app()->environment('production') && $attempt->mode !== 'live', 403);
        if ($attempt->driver === 'bkash') {
            abort_unless($attempt->callback_proof_hash && hash_equals($attempt->callback_proof_hash, hash('sha256', (string) $request->query('signature', ''))), 403);
        }
        app(PaymentEngine::class)->verify($attempt, true);

        // Stateless API return: never overwrite the guest receipt session on a cross-site POST.
        return redirect()->route('payments.status', $attempt->uuid);
    }
}
