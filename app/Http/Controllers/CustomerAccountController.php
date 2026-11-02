<?php

namespace App\Http\Controllers;

use App\Domain\Customers\Actions\SaveAddress;
use App\Domain\Customers\Models\Address;
use App\Domain\Orders\Actions\ManageOrder;
use App\Domain\Orders\Actions\OrderAccess;
use App\Domain\Orders\Actions\Reorder;
use App\Domain\Orders\Models\DownloadEntitlement;
use App\Domain\Orders\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class CustomerAccountController extends Controller
{
    public function export(): mixed
    {
        $u = auth('web')->user();
        $orders = Order::where('user_id', $u->id)->with('items')->get()->map(fn ($o) => ['number' => $o->number, 'status' => $o->status, 'currency' => $o->currency, 'total' => $o->total, 'shipping_address' => $o->shipping_address, 'placed_at' => $o->placed_at, 'items' => $o->items->map(fn ($i) => $i->only(['name', 'sku', 'qty', 'total']))]);

        return response()->streamDownload(function () use ($u, $orders) {
            echo json_encode(['profile' => $u->only(['name', 'email', 'phone', 'locale', 'currency']), 'addresses' => Address::where('user_id', $u->id)->get()->map(fn ($a) => $a->only(['name', 'phone', 'country', 'line1', 'division_id', 'district_id', 'upazila_id', 'area', 'area_id', 'is_default'])), 'orders' => $orders], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        }, 'my-data.json', ['Content-Type' => 'application/json']);
    }

    public function privacyRequest(): mixed
    {
        DB::transaction(function () {
            User::whereKey(auth('web')->id())->lockForUpdate()->firstOrFail();
            if (! DB::table('customer_privacy_requests')->where('user_id', auth('web')->id())->where('status', 'requested')->exists()) {
                DB::table('customer_privacy_requests')->insert(['user_id' => auth('web')->id(), 'created_at' => now(), 'updated_at' => now()]);
            }
        }, 3);

        return back()->with('status', __('orders.privacy_requested'));
    }

    public function index(): mixed
    {
        return view('themes.default.account.index', ['user' => auth('web')->user(), 'orders' => Order::where('user_id', auth('web')->id())->latest('id')->paginate(15)]);
    }

    public function profile(Request $r): mixed
    {
        $u = auth('web')->user();
        if ($r->filled('email')) {
            $r->merge(['email' => strtolower(trim((string) $r->input('email')))]);
        }
        $d = $r->validate(['name' => 'required|string|max:100', 'email' => 'nullable|email:rfc|max:254|unique:users,email,'.$u->id, 'locale' => 'required|in:en,bn']);
        if (($d['email'] ?? null) !== $u->email) {
            $u->forceFill(['email_verified_at' => null]);
        }$u->fill($d)->save();

        return back()->with('status', __('orders.saved'));
    }

    public function password(Request $r): mixed
    {
        $fresh = (int) $r->session()->get('customer_email_verified_until', 0) > now()->timestamp;
        $d = $r->validate(['current_password' => $fresh ? 'nullable' : 'required|current_password:web', 'password' => 'required|confirmed|min:12|max:128']);
        $u = auth('web')->user();
        $u->forceFill(['password' => $d['password'], 'auth_version' => $u->auth_version + 1, 'remember_token' => Str::random(60)])->save();
        $r->session()->forget('customer_email_verified_until');
        $r->session()->regenerate();
        $r->session()->put('customer_auth_version', $u->auth_version);

        return back()->with('status', __('orders.saved'));
    }

    public function order(string $number): mixed
    {
        $o = Order::where('number', $number)->firstOrFail();
        app(OrderAccess::class)->owner($o);

        return view('themes.default.account.order', ['order' => $o->load(['items', 'events', 'shipments', 'returns', 'payments', 'refunds'])]);
    }

    public function action(Request $r, string $number): mixed
    {
        $o = Order::where('number', $number)->firstOrFail();
        app(OrderAccess::class)->owner($o);
        $d = $r->validate(['action' => 'required|in:cancel,reorder,return', 'reason' => 'required_if:action,return|nullable|string|max:2000', 'items' => 'required_if:action,return|array', 'items.*' => 'integer|min:0|max:100']);
        switch ($d['action']) {
            case 'cancel':app(ManageOrder::class)->transition($o, 'cancelled', null, true);
                break;
            case 'reorder':app(Reorder::class)->execute($o);

                return redirect()->route('cart');
            case 'return':app(ManageOrder::class)->requestReturn($o, array_filter(array_map('intval', $d['items'])), $d['reason']);
        }

        return back()->with('status', __('orders.saved'));
    }

    public function addresses(): mixed
    {
        return view('themes.default.account.addresses', ['addresses' => Address::where('user_id', auth('web')->id())->get()]);
    }

    public function address(Request $r, ?Address $address = null): mixed
    {
        app(SaveAddress::class)->execute(auth('web')->user(), $r->all(), $address);

        return back()->with('status', __('orders.saved'));
    }

    public function deleteAddress(Address $address): mixed
    {
        abort_unless($address->user_id === auth('web')->id(), 403);
        $address->delete();

        return back();
    }

    public function downloads(): mixed
    {
        $ids = DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')->where('orders.user_id', auth('web')->id())->where('orders.payment_status', 'paid')->where('orders.refunded_amount', 0)->whereNotIn('orders.status', ['cancelled', 'failed'])->pluck('order_items.id');

        return view('themes.default.account.downloads', ['downloads' => DownloadEntitlement::whereIn('order_item_id', $ids)->get()]);
    }

    public function download(DownloadEntitlement $entitlement): mixed
    {
        $path = DB::transaction(function () use ($entitlement) {
            $row = DB::table('order_items')->where('id', $entitlement->order_item_id)->first();
            $o = Order::lockForUpdate()->findOrFail($row?->order_id);
            app(OrderAccess::class)->owner($o);
            $e = DownloadEntitlement::lockForUpdate()->findOrFail($entitlement->id);
            abort_unless($o->payment_status === 'paid' && ! $o->refunded_amount && ! in_array($o->status, ['cancelled', 'failed']) && $e->enabled_at && (! $e->expires_at || now()->lt($e->expires_at)) && (! $e->limit || $e->used < $e->limit), 403);
            if ($e->file) {
                abort_unless(Storage::disk('local')->exists($e->file), 404);
            } else {
                abort_unless(filter_var($e->external_url, FILTER_VALIDATE_URL) && str_starts_with($e->external_url, 'https://'), 404);
            }
            $e->increment('used');

            return [$e->file, $e->external_url];
        }, 3);

        return $path[0] ? Storage::disk('local')->download($path[0]) : redirect()->away($path[1]);
    }
}
