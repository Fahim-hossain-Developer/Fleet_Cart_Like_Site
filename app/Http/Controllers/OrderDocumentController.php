<?php

namespace App\Http\Controllers;

use App\Domain\Orders\Actions\OrderAccess;
use App\Domain\Orders\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

final class OrderDocumentController extends Controller
{
    public function customer(Order $order, string $type): mixed
    {
        app(OrderAccess::class)->owner($order);

        return $this->render($order, $type);
    }

    public function staff(Order $order, string $type): mixed
    {
        app(OrderAccess::class)->staff();

        return $this->render($order, $type);
    }

    public function proof(Order $order): mixed
    {
        app(OrderAccess::class)->staff('payments.manage');
        abort_unless($order->payment_proof && Storage::disk('local')->exists($order->payment_proof), 404);

        return Storage::disk('local')->download($order->payment_proof);
    }

    private function render(Order $order, string $type): mixed
    {
        abort_unless(in_array($type, ['invoice', 'packing', 'label'], true), 404);
        if (! is_dir(storage_path('fonts'))) {
            mkdir(storage_path('fonts'), 0700, true);
        }
        $order->load('items');

        return Pdf::loadView('pdf.order', compact('order', 'type'))->setOption(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false, 'defaultFont' => 'DejaVu Sans'])->setPaper('a4')->download($order->number.'-'.$type.'.pdf');
    }
}
