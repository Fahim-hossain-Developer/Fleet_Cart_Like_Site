<?php

namespace App\Http\Controllers;

use App\Domain\Orders\Actions\OrderAccess;
use App\Domain\Orders\Models\Order;
use App\Models\User;

final class CommerceExportController extends Controller
{
    public function __invoke(string $type): mixed
    {
        abort_unless(in_array($type, ['customers', 'orders']), 404);
        app(OrderAccess::class)->staff($type.'.export');
        $columns = $type === 'orders' ? ['number', 'phone', 'guest_email', 'status', 'payment_status', 'currency', 'total', 'paid_amount', 'refunded_amount', 'placed_at'] : ['name', 'email', 'phone', 'is_blocked', 'created_at'];
        $records = $type === 'orders' ? Order::query() : User::query();

        return response()->streamDownload(function () use ($columns, $records) {
            $f = fopen('php://output', 'w');
            fwrite($f, "\xEF\xBB\xBF");
            fputcsv($f, $columns, escape: '');
            foreach ($records->orderBy('id')->limit(5000)->cursor() as $record) {
                $row = [];
                foreach ($columns as $column) {
                    $value = (string) ($record->$column ?? '');
                    $row[] = preg_match('/^[=+@\-\t\r]/u', $value) ? "'".$value : $value;
                }fputcsv($f, $row, escape: '');
            }fclose($f);
        }, $type.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
