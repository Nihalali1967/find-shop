<?php

namespace App\Services\Media;

use App\Exceptions\MarketplaceException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageProcessor
{
    public const THUMB_WIDTH = 480;

    /**
     * Store an uploaded product image, returning [path, thumbPath] relative to the public disk.
     *
     * @return array{path:string,thumb_path:?string}
     */
    public function store(UploadedFile $file, string $directory = 'products'): array
    {
        $binary = @file_get_contents($file->getRealPath());

        if ($binary === false) {
            throw MarketplaceException::invalid('The uploaded image could not be read.');
        }

        // Verify the payload really is a decodable image (never trust the client MIME type).
        $image = @imagecreatefromstring($binary);
        $mime = @getimagesizefromstring($binary)['mime'] ?? null;

        if ($image === false || ! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw MarketplaceException::invalid('Images must be valid JPEG, PNG or WebP files.');
        }

        $name = Str::uuid()->toString();
        $extension = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $path = "{$directory}/{$name}.{$extension}";
        $thumbPath = "{$directory}/thumbs/{$name}.{$extension}";

        // Re-encoding strips EXIF/metadata embedded in the original bytes.
        $this->encode($image, $path, $mime);
        $this->encode($this->scale($image, self::THUMB_WIDTH), $thumbPath, $mime);
        imagedestroy($image);

        return ['path' => $path, 'thumb_path' => $thumbPath];
    }

    public function delete(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    protected function encode(\GdImage $image, string $path, string $mime): void
    {
        ob_start();
        match ($mime) {
            'image/png' => imagepng($image, null, 8),
            'image/webp' => imagewebp($image, null, 85),
            default => imagejpeg($image, null, 86),
        };
        $bytes = (string) ob_get_clean();

        Storage::disk('public')->put($path, $bytes);
    }

    protected function scale(\GdImage $image, int $maxWidth): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);

        if ($width <= $maxWidth) {
            $canvas = imagecreatetruecolor($width, $height);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);

            return $canvas;
        }

        $newWidth = $maxWidth;
        $newHeight = (int) max(1, round($height * ($maxWidth / $width)));

        return imagescale($image, $newWidth, $newHeight) ?: $image;
    }
}
