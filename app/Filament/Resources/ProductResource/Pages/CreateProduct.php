<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Domain\Catalog\Actions\SaveProduct;
use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProduct extends CreateRecord
{
    use Concerns\SavesMedia;

    protected static string $resource = ProductResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveProduct::class)->execute($data);
    }

    protected function afterCreate(): void
    {
        $this->saveGallery();
    }
}
