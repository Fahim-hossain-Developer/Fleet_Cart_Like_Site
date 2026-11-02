<?php

namespace App\Http\Controllers;

use App\Domain\Cart\Actions\CartSession;
use App\Domain\Cart\Models\Cart;
use App\Domain\Marketing\Actions\CaptureRecoveryContact;
use App\Domain\Marketing\Actions\RecoveryPhoneCode;
use App\Domain\Marketing\Actions\RestoreCartRecovery;
use App\Domain\Marketing\Models\CartRecoveryContact;
use App\Domain\Marketing\Models\CartRecoveryMessage;
use App\Domain\Orders\Actions\OrderAccess;
use Illuminate\Http\Request;

final class CartRecoveryController extends Controller
{
    public function draft(int $id): mixed
    {
        app(OrderAccess::class)->staff('marketing.manage');
        $m = CartRecoveryMessage::findOrFail($id);
        $c = CartRecoveryContact::findOrFail($m->cart_recovery_contact_id);
        abort_unless(store_setting('recovery_enabled', false) && $m->channel === 'whatsapp' && $m->status === 'ready_manual' && ! $c->stopped_at && ! $c->converted_at && $m->generation === $c->generation && now()->lt($m->expires_at) && $c->phone_verified_at && ! empty($c->channels['whatsapp']), 410);
        $cart = Cart::findOrFail($c->cart_id);
        abort_unless($cart->items()->where('saved', false)->exists() && $cart->updated_at->lte(now()->subHours($m->stage)), 410);
        $token = $m->payload['token'] ?? '';
        abort_unless(is_string($token) && preg_match('/^[a-zA-Z0-9]{64}$/D', $token) && preg_match('/^\+[1-9][0-9]{7,14}$/D', $c->phone), 410);
        $body = __('marketing.recovery_message')."\n".route('recovery.restore', $token)."\n".__('marketing.recovery_stop').': '.route('recovery.stop', $c->stop_token);
        $url = 'https://wa.me/'.ltrim($c->phone, '+').'?text='.rawurlencode($body);

        return response()->view('themes.default.marketing.recovery-draft', compact('url'))->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex,nofollow');
    }

    public function capture(Request $request): mixed
    {
        $c = app(CaptureRecoveryContact::class)->execute(app(CartSession::class)->current(), $request->all());

        return response()->json(['active' => $c !== null, 'email_verified' => (bool) $c?->email_verified_at, 'phone_verified' => (bool) $c?->phone_verified_at]);
    }

    private function contact(): CartRecoveryContact
    {
        $cart = app(CartSession::class)->current();

        return CartRecoveryContact::where('cart_id', $cart->id)->whereNull('stopped_at')->whereNull('converted_at')->firstOrFail();
    }

    public function sendCode(): mixed
    {
        app(RecoveryPhoneCode::class)->send($this->contact());

        return response()->json(['queued' => true]);
    }

    public function verifyCode(Request $r): mixed
    {
        $d = $r->validate(['code' => 'required|digits:6']);
        app(RecoveryPhoneCode::class)->consume($this->contact(), $d['code']);

        return response()->json(['phone_verified' => true]);
    }

    public function confirm(string $token): mixed
    {
        $valid = app(CaptureRecoveryContact::class)->confirm($token);

        return $this->screen('confirmed', $token, $valid ? 200 : 410);
    }

    public function stop(Request $r, string $token): mixed
    {
        if ($r->isMethod('post')) {
            app(CaptureRecoveryContact::class)->stop($token);

            return $this->screen('stopped', $token);
        }

        return $this->screen('stop', $token);
    }

    public function oneClick(string $token): mixed
    {
        app(CaptureRecoveryContact::class)->stop($token);

        return response()->json(['stopped' => true])->header('Cache-Control', 'no-store');
    }

    public function restore(Request $r, string $token): mixed
    {
        abort_unless(preg_match('/^[a-zA-Z0-9]{64}$/D', $token), 410);
        if ($r->isMethod('post')) {
            app(RestoreCartRecovery::class)->execute($token);

            return redirect()->route('cart')->with('status', __('marketing.recovery_restored'))->header('Referrer-Policy', 'no-referrer');
        }

        return $this->screen('restore', $token);
    }

    private function screen(string $state, string $token, int $status = 200): mixed
    {
        return response()->view('themes.default.marketing.recovery-status', compact('state', 'token', 'status'), $status)->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex, nofollow')->header('Cache-Control', 'no-store');
    }
}
