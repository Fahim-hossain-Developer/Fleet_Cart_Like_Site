<?php

namespace App\Http\Controllers;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Actions\GatewayManager;
use App\Domain\Payments\Actions\PaymentAccess;
use App\Domain\Payments\Actions\PaymentEngine;
use App\Domain\Payments\Actions\PendingOperations;
use App\Domain\Payments\Models\GatewayAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PaymentController extends Controller
{
    public function order(Order $order): mixed
    {
        app(PaymentAccess::class)->customer($order);
        $advance = max(0, $order->advance_required - ($order->paid_amount - $order->refunded_amount));
        $due = $advance > 0 ? min($advance, $order->total - $order->paid_amount) : $order->total - $order->paid_amount;

        return view('themes.default.payments.order', ['order' => $order, 'due' => $due, 'advance' => $advance, 'gateways' => app(GatewayManager::class)->available($order->currency, $due), 'key' => (string) Str::uuid(), 'active' => GatewayAttempt::where('order_id', $order->id)->whereIn('status', PendingOperations::PAYMENTS)->first()]);
    }

    public function start(Request $r, Order $order): mixed
    {
        $d = $r->validate(['driver' => 'required|string|max:40', 'key' => 'required|uuid', 'purpose' => 'required|in:due,full', 'email' => 'nullable|email:rfc|max:254']);
        $attempt = app(PaymentEngine::class)->start($order, $d['driver'], $d['key'], $d['purpose'], $d['email'] ?? null);

        return redirect()->route('payments.status', $attempt->uuid);
    }

    public function status(GatewayAttempt $attempt): mixed
    {
        $order = Order::findOrFail($attempt->order_id);
        app(PaymentAccess::class)->customer($order);

        return view('themes.default.payments.status', compact('order', 'attempt'));
    }

    public function check(GatewayAttempt $attempt): mixed
    {
        $order = Order::findOrFail($attempt->order_id);
        app(PaymentAccess::class)->customer($order);
        app(PaymentEngine::class)->verify($attempt, true);

        return redirect()->route('payments.status', $attempt->uuid);
    }

    public function fake(Request $r, GatewayAttempt $attempt): mixed
    {
        abort_unless(app()->environment('local', 'testing') && $attempt->driver === 'fake', 403);
        $order = Order::findOrFail($attempt->order_id);
        app(PaymentAccess::class)->customer($order);
        if ($r->isMethod('post')) {
            $d = $r->validate(['outcome' => 'required|in:paid,failed,cancelled']);
            DB::transaction(function () use ($attempt, $d) {
                $a = GatewayAttempt::lockForUpdate()->findOrFail($attempt->id);
                if (! $a->provider_state || $a->provider_state === 'pending') {
                    $a->update(['provider_state' => $d['outcome']]);
                }
            }, 3);
            app(PaymentEngine::class)->verify($attempt);

            return redirect()->route('payments.status', $attempt->uuid);
        }

        return view('themes.default.payments.fake', compact('order', 'attempt'));
    }
}
