<?php

namespace App\Domain\Marketing\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ReviewPhotos
{
    /** @param array<int,UploadedFile> $files
     * @return list<string> */
    public function store(array $files): array
    {
        validator(['photos' => $files], ['photos' => 'array|max:5', 'photos.*' => 'image|mimes:jpg,jpeg,png,webp,avif|max:4096|dimensions:max_width=3000,max_height=3000'])->validate();
        $paths = [];
        try {
            foreach ($files as $file) {
                $raw = file_get_contents($file->getRealPath());
                $image = $raw !== false ? @imagecreatefromstring($raw) : false;
                if (! $image) {
                    throw ValidationException::withMessages(['photos' => __('marketing.invalid_photo')]);
                }$width = imagesx($image);
                $height = imagesy($image);
                $scale = min(1, 1600 / max($width, $height));
                $canvas = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
                if (! $canvas) {
                    throw new \RuntimeException('Review media allocation failed');
                }$white = imagecolorallocate($canvas, 255, 255, 255);
                imagefill($canvas, 0, 0, $white === false ? 0xFFFFFF : $white);
                imagecopyresampled($canvas, $image, 0, 0, 0, 0, imagesx($canvas), imagesy($canvas), $width, $height);
                ob_start();
                imagewebp($canvas, null, 82);
                $encoded = ob_get_clean();
                imagedestroy($canvas);
                imagedestroy($image);
                if (! is_string($encoded) || $encoded === '') {
                    throw ValidationException::withMessages(['photos' => __('marketing.invalid_photo')]);
                }$path = 'reviews/'.Str::uuid().'.webp';
                if (! Storage::disk('public')->put($path, $encoded)) {
                    throw new \RuntimeException('Review media storage failed');
                }$paths[] = $path;
            }

            return $paths;
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($paths);
            throw $e;
        }
    }
}
