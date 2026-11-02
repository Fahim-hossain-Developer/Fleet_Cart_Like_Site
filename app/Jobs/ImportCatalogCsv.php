<?php

namespace App\Jobs;

use App\Domain\Catalog\Actions\CatalogCsv;
use App\Domain\Catalog\Models\CatalogImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ImportCatalogCsv implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $importId) {}

    public function handle(CatalogCsv $csv): void
    {
        $import = CatalogImport::findOrFail($this->importId);
        if ($import->status === 'done') {
            return;
        }
        $import->update(['status' => 'running']);
        try {
            $report = DB::transaction(fn () => $csv->import(Storage::disk('local')->path($import->path)));
            $import->update(['status' => 'done', 'imported' => $report['imported'], 'errors' => $report['errors']]);
        } catch (\Throwable $e) {
            $import->update(['status' => 'failed', 'errors' => [['row' => 0, 'messages' => [__('catalog.import_failed')]]]]);
            throw $e;
        }
    }
}
