<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ProjectCategoryResource;
use Filament\Pages\Page;

class ProjeKategoriEkle extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';
    protected static ?string $navigationGroup = 'Projeler';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationLabel = 'Kategori Ekle';
    protected static ?string $title = 'Yeni Proje Kategorisi';
    protected static string $view = 'filament.pages.redirect-placeholder';
    protected static string $routePath = 'projeler/kategori/ekle';

    public function mount(): void
    {
        $this->redirect(ProjectCategoryResource::getUrl('create'));
    }

    public static function getRoutePath(): string
    {
        return static::$routePath;
    }
}
