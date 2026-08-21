<?php

namespace App\Filament\Pages;

use App\Filament\Resources\PostCategoryResource;
use Filament\Pages\Page;

class BlogKategoriEkle extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';
    protected static ?string $navigationGroup = 'Bloglar';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationLabel = 'Kategori Ekle';
    protected static ?string $title = 'Yeni Blog Kategorisi';
    protected static string $view = 'filament.pages.redirect-placeholder';
    protected static string $routePath = 'bloglar/kategori/ekle';

    public function mount(): void
    {
        $this->redirect(PostCategoryResource::getUrl('create'));
    }

    public static function getRoutePath(): string
    {
        return static::$routePath;
    }
}
