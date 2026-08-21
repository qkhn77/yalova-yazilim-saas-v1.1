<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class FrontThemeService
{
    public const DEFAULT_THEME = 'yalovakamera';

    /** @return array<string, array{name: string, description: string, css: string, js: string|null, favicon: string|null}> */
    public function themes(): array
    {
        return [
            'yalovakamera' => [
                'name' => 'Kamera Teması',
                'description' => 'Mevcut kurumsal kamera ve güvenlik sistemleri görünümü.',
                'css' => 'theme/yalovakamera/css/custom.css',
                'js' => null,
                'favicon' => null,
            ],
            'software' => [
                'name' => 'Yazılım Teması',
                'description' => 'Teknoloji ve yazılım firmaları için modern, koyu arayüz.',
                'css' => 'theme/software/css/theme.css',
                'js' => 'theme/software/js/theme.js',
                'favicon' => 'theme/software/images/favicon.svg',
            ],
        ];
    }

    public function active(): string
    {
        $theme = (string) Setting::get('front_theme', self::DEFAULT_THEME);

        return array_key_exists($theme, $this->themes()) ? $theme : self::DEFAULT_THEME;
    }

    public function is(string $theme): bool
    {
        return $this->active() === $theme;
    }

    public function bodyClass(): string
    {
        return 'front-theme-' . $this->active();
    }

    public function asset(string $path): string
    {
        return asset($path);
    }

    public function fallbackImage(string $defaultPath): string
    {
        if ($this->is('software')) {
            return asset('theme/software/images/placeholder.svg');
        }

        return asset($defaultPath);
    }

    public function cssPath(): ?string
    {
        return $this->themes()[$this->active()]['css'] ?? null;
    }

    public function jsPath(): ?string
    {
        return $this->themes()[$this->active()]['js'] ?? null;
    }

    public function faviconPath(): ?string
    {
        return $this->themes()[$this->active()]['favicon'] ?? null;
    }

    public function versionedAsset(string $path): string
    {
        $fullPath = public_path($path);
        $version = Cache::remember(
            'front.asset-version.' . md5($fullPath),
            600,
            fn (): int => is_file($fullPath) ? (int) filemtime($fullPath) : time()
        );

        return asset($path) . '?v=' . $version;
    }
}
