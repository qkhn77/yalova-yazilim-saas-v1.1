<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SistemYedekleriServisi
{
    public function dizin(): string
    {
        return rtrim((string) config('backup.path'), DIRECTORY_SEPARATOR);
    }

    /**
     * @return list<array{name: string, size: int, modified_at: int}>
     */
    public function listele(): array
    {
        $directory = $this->dizin();
        if (! is_dir($directory)) {
            return [];
        }

        return collect(File::files($directory))
            ->filter(fn (\SplFileInfo $file): bool => preg_match('/\\.sql(?:\\.gz)?\\z/i', $file->getFilename()) === 1)
            ->map(fn (\SplFileInfo $file): array => [
                'name' => $file->getFilename(),
                'size' => $file->getSize(),
                'modified_at' => $file->getMTime(),
            ])
            ->sortByDesc('modified_at')
            ->values()
            ->all();
    }

    public function guvenliYol(string $name): string
    {
        abort_unless($name === basename($name), 404);
        abort_unless(preg_match('/\\A[a-zA-Z0-9._-]+\\.sql(?:\\.gz)?\\z/', $name) === 1, 404);

        $path = $this->dizin().DIRECTORY_SEPARATOR.$name;
        abort_unless(is_file($path), 404);

        return $path;
    }

    public function indir(string $name): BinaryFileResponse
    {
        return response()->download($this->guvenliYol($name), $name, [
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function sil(string $name): void
    {
        File::delete($this->guvenliYol($name));
    }
}
