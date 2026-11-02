<?php

namespace App\Http\Controllers;

use App\Domain\Catalog\Models\Product;
use App\Domain\Customers\Actions\NoticePreferences;
use App\Domain\Customers\Actions\WatchProduct;
use App\Domain\Customers\Models\ProductWatch;
use App\Domain\Customers\Models\StoreNotification;
use App\Models\User;
use Illuminate\Http\Request;

final class NotificationController extends Controller
{
    public function index(): mixed
    {
        $u = auth('web')->user();

        return view('themes.default.account.notifications', ['notices' => StoreNotification::where('notifiable_type', User::class)->where('notifiable_id', $u->id)->where('data->in_app', true)->latest()->paginate(20), 'preferences' => app(NoticePreferences::class), 'watches' => ProductWatch::where('user_id', $u->id)->where('is_active', true)->get()]);
    }

    public function read(StoreNotification $notification): mixed
    {
        abort_unless($notification->notifiable_type === User::class && (int) $notification->notifiable_id === auth('web')->id(), 403);
        $notification->update(['read_at' => now()]);

        return back();
    }

    public function readAll(): mixed
    {
        StoreNotification::where('notifiable_type', User::class)->where('notifiable_id', auth('web')->id())->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }

    public function preferences(Request $r): mixed
    {
        app(NoticePreferences::class)->save(auth('web')->user(), $r->all());

        return back()->with('status', __('orders.saved'));
    }

    public function stop(int $watch): mixed
    {
        ProductWatch::where('user_id', auth('web')->id())->findOrFail($watch)->update(['is_active' => false]);

        return back()->with('status', __('orders.saved'));
    }

    public function watch(Request $r, Product $product): mixed
    {
        $d = $r->validate(['kind' => 'required|in:stock,price', 'enabled' => 'required|boolean']);
        app(WatchProduct::class)->set(auth('web')->user(), $product, $d['kind'], $r->boolean('enabled'));

        return back()->with('status', __('orders.saved'));
    }
}
