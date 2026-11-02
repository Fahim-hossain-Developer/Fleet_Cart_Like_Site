<?php

namespace App\Filament\Resources\ProductResource\Pages\Concerns;

use App\Domain\Catalog\Models\Product;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

trait SavesMedia
{
    protected function saveGallery(): void
    {
        /** @var Product $record */ $record = $this->record;
        foreach ($this->data['new_images'] ?? [] as $file) {
            if ($file instanceof TemporaryUploadedFile) {
                validator(['image' => $file], ['image' => 'required|image|mimes:jpg,jpeg,png,webp,avif|max:4096'])->validate();
                $record->addMedia($file->getRealPath())->usingFileName(Str::uuid().'.'.$file->guessExtension())->withCustomProperties(['alt' => ''])->toMediaCollection('images');
            }
        }
        foreach ($this->data['gallery_metadata'] ?? [] as $index => $item) {
            $media = $record->getMedia('images')->firstWhere('id', $item['id']);
            if (! $media) {
                continue;
            } if ($item['remove'] ?? false) {
                $media->delete();
            } else {
                $media->setCustomProperty('alt', mb_substr((string) ($item['alt'] ?? ''), 0, 255));
                $media->order_column = $index + 1;
                $media->save();
            }
        }
    }
}
