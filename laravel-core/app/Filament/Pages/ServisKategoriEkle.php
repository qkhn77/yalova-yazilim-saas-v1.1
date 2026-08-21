<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ServiceCategoryResource;
use Filament\Pages\Page;

class ServisKategoriEkle extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';
    protected static ?string $navigationGroup = 'Servisler';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationLabel = 'Kategori Ekle';
    protected static ?string $title = 'Yeni Servis Kategorisi';
    protected static string $view = 'filament.pages.redirect-placeholder';
    protected static string $routePath = 'servisler/kategori/ekle';

    public function mount(): void
    {
        $this->redirect(ServiceCategoryResource::getUrl('create'));
    }

    public static function getRoutePath(): string
    {
        return static::$routePath;
    }
}
