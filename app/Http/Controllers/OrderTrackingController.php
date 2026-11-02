<?php

namespace App\Http\Controllers;

use App\Domain\Customers\Actions\NormalizePhone;
use App\Domain\Orders\Models\Order;
use Illuminate\Http\Request;

final class OrderTrackingController extends Controller
{
    public function __invoke(Request $r): mixed
    {
        $order = null;
        if ($r->isMethod('post')) {
            $d = $r->validate(['number' => 'required|string|max:50', 'phone' => 'required|string|max:32']);
            $order = Order::where('number', $d['number'])->where('phone', app(NormalizePhone::class)->execute($d['phone']))->with(['shipments', 'events' => fn ($q) => $q->where('is_public', true)])->first();
            if (! $order) {
                return back()->withErrors(['number' => __('orders.tracking_not_found')]);
            }
        }

        return view('themes.default.account.tracking', compact('order'));
    }
}
