<?php

namespace App\Filament\Pages;

use App\Filament\Resources\PostResource;
use Filament\Pages\Page;

class BlogEkle extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';
    protected static ?string $navigationGroup = 'Bloglar';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Blog Ekle';
    protected static ?string $title = 'Yeni Blog Yazısı';
    protected static string $view = 'filament.pages.redirect-placeholder';
    protected static string $routePath = 'bloglar/ekle';

    public function mount(): void
    {
        $this->redirect(PostResource::getUrl('create'));
    }

    public static function getRoutePath(): string
    {
        return static::$routePath;
    }
}
