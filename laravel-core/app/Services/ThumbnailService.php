<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class ThumbnailService
{
    public const THUMB_SIZE = 48;

    /**
     * Ana görselden thumbnail oluşturur. storage/app/public/{directory}/thumbs/ altına kaydeder.
     * Örn: posts/abc.jpg -> posts/thumbs/abc_48.jpg
     *
     * @param  string  $directory  Klasör adı (posts, services)
     * @param  string  $imagePath  Mevcut görsel yolu (posts/abc.jpg veya abc.jpg)
     * @param  int  $width  Thumbnail genişlik
     * @param  int  $height  Thumbnail yükseklik
     * @return string|null  Kaydedilen thumb path (directory/thumbs/xxx_ext) veya null
     */
    public function generate(string $directory, string $imagePath, int $width = self::THUMB_SIZE, int $height = self::THUMB_SIZE): ?string
    {
        $disk = Storage::disk('public');
        $path = str_replace('\\', '/', $imagePath);
        $path = ltrim($path, '/');
        if (! str_starts_with($path, $directory.'/')) {
            $path = $directory.'/'.$path;
        }

        $fullPath = $disk->path($path);
        if (! is_file($fullPath) || ! is_readable($fullPath)) {
            return null;
        }

        $info = pathinfo($path);
        $ext = strtolower($info['extension'] ?? '');
        $basename = $info['filename'] ?? 'image';
        $thumbRelPath = $directory.'/thumbs/'.$basename.'_'.$width.'.'.$ext;

        $thumbFullPath = $disk->path($thumbRelPath);
        $thumbDir = dirname($thumbFullPath);
        if (! is_dir($thumbDir)) {
            @mkdir($thumbDir, 0755, true);
        }

        $image = $this->loadImage($fullPath, $ext);
        if (! $image) {
            return null;
        }

        $srcW = imagesx($image);
        $srcH = imagesy($image);
        if ($srcW <= 0 || $srcH <= 0) {
            imagedestroy($image);
            return null;
        }

        $dstW = min($width, $srcW);
        $dstH = min($height, $srcH);
        $ratio = min($dstW / $srcW, $dstH / $srcH);
        $dstW = (int) round($srcW * $ratio);
        $dstH = (int) round($srcH * $ratio);
        if ($dstW < 1) {
            $dstW = 1;
        }
        if ($dstH < 1) {
            $dstH = 1;
        }

        $thumb = imagecreatetruecolor($dstW, $dstH);
        if (! $thumb) {
            imagedestroy($image);
            return null;
        }

        imagecopyresampled($thumb, $image, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
        imagedestroy($image);

        $saved = $this->saveImage($thumb, $thumbFullPath, $ext);
        imagedestroy($thumb);

        if (! $saved) {
            return null;
        }

        return $thumbRelPath;
    }

    /**
     * Thumbnail dosya yolu (directory/thumbs/xxx_48.ext) döner. Dosya yoksa null.
     */
    public function getThumbPath(string $directory, string $imagePath, int $size = self::THUMB_SIZE): ?string
    {
        $path = str_replace('\\', '/', $imagePath);
        $path = ltrim($path, '/');
        if (! str_starts_with($path, $directory.'/')) {
            $path = $directory.'/'.$path;
        }
        $info = pathinfo($path);
        $ext = strtolower($info['extension'] ?? 'jpg');
        $basename = $info['filename'] ?? '';
        if ($basename === '') {
            return null;
        }
        $thumbPath = $directory.'/thumbs/'.$basename.'_'.$size.'.'.$ext;
        if (! Storage::disk('public')->exists($thumbPath)) {
            return null;
        }

        return $thumbPath;
    }

    private function loadImage(string $path, string $ext): \GdImage|false
    {
        return match ($ext) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($path),
            'png' => @imagecreatefrompng($path),
            'gif' => @imagecreatefromgif($path),
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
    }

    private function saveImage(\GdImage $image, string $path, string $ext): bool
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $result = match ($ext) {
            'jpg', 'jpeg' => imagejpeg($image, $path, 82),
            'png' => imagepng($image, $path, 8),
            'gif' => imagegif($image, $path),
            'webp' => function_exists('imagewebp') ? imagewebp($image, $path, 82) : false,
            default => false,
        };

        return $result !== false;
    }
}
