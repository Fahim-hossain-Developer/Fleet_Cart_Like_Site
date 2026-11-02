<?php

namespace App\Filament\Pages;

use App\Domain\Catalog\Models\CatalogImport;
use App\Jobs\ImportCatalogCsv;
use App\Models\Admin;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

/** @property Form $form */
class CatalogImports extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static string $view = 'filament.pages.catalog-imports';

    /** @var array<string,mixed>|null */
    public ?array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return __('catalog.catalog');
    }

    public static function getNavigationLabel(): string
    {
        return __('catalog.import_export');
    }

    public function getTitle(): string
    {
        return __('catalog.import_export');
    }

    public static function canAccess(): bool
    {
        $a = Auth::guard('admin')->user();

        return $a instanceof Admin && $a->is_active && $a->hasPermissionTo('catalog.manage', 'admin');
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form->schema([FileUpload::make('path')->label(__('catalog.csv_file'))->disk('local')->directory('catalog-imports')->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])->maxSize(2048)->required()])->statePath('data');
    }

    public function submit(): void
    {
        abort_unless(static::canAccess(), 403);
        $data = $this->form->getState();
        $import = CatalogImport::create(['admin_id' => Auth::guard('admin')->id(), 'path' => $data['path']]);
        ImportCatalogCsv::dispatch($import->id);
        $this->form->fill();
        Notification::make()->title(__('catalog.import_submitted'))->success()->send();
    }

    protected function getViewData(): array
    {
        return ['imports' => CatalogImport::where('admin_id', Auth::guard('admin')->id())->latest()->limit(20)->get()];
    }
}
