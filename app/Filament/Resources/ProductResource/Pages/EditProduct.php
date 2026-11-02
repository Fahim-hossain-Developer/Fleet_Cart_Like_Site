<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Domain\Catalog\Actions\DuplicateProduct;
use App\Domain\Catalog\Actions\GenerateVariants;
use App\Domain\Catalog\Actions\SaveProduct;
use App\Domain\Catalog\Models\Product;
use App\Filament\Resources\ProductResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/** @property Product $record */
class EditProduct extends EditRecord
{
    use Concerns\SavesMedia;

    protected static string $resource = ProductResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        assert($record instanceof Product);

        return app(SaveProduct::class)->execute($data, $record);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['cost'] = $this->record->cost;
        $data['gallery_metadata'] = $this->record->getMedia('images')->map(fn ($m) => ['id' => $m->id, 'alt' => $m->getCustomProperty('alt', ''), 'remove' => false])->values()->all();

        return $data;
    }

    protected function afterSave(): void
    {
        $this->saveGallery();
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('duplicate')->label(__('catalog.duplicate'))->action(function (): void {
            $copy = app(DuplicateProduct::class)->execute($this->record);
            $this->redirect(static::getResource()::getUrl('edit', ['record' => $copy]));
        }), Action::make('generate')->label(__('catalog.generate_variants'))->action(function (): void {
            app(GenerateVariants::class)->execute($this->record);
            $this->redirect(static::getResource()::getUrl('edit', ['record' => $this->record]));
        }), DeleteAction::make()];
    }
}
