<?php

namespace App\Http\Controllers;

use App\Domain\Catalog\Actions\CatalogCsv;
use App\Domain\Catalog\Models\Product;
use App\Models\Admin;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CatalogExportController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof Admin && $admin->is_active && $admin->hasPermissionTo('catalog.manage', 'admin'), 403);

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'wb');
            if (! $handle) {
                return;
            } fputcsv($handle, CatalogCsv::COLUMNS, ',', '"', '');
            foreach (Product::query()->cursor() as $p) {
                fputcsv($handle, array_map(fn ($v) => CatalogCsv::safeCell((string) $v), [$p->sku, $p->slug, $p->getTranslation('name', 'en'), $p->getTranslation('name', 'bn'), $p->price, $p->qty, $p->status]), ',', '"', '');
            }
            fclose($handle);
        }, 'products.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
