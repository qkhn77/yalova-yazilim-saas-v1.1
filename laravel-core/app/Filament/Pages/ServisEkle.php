<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ServiceResource;
use Filament\Pages\Page;

class ServisEkle extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';
    protected static ?string $navigationGroup = 'Servisler';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Servis Ekle';
    protected static ?string $title = 'Yeni Servis';
    protected static string $view = 'filament.pages.redirect-placeholder';
    protected static string $routePath = 'servisler/ekle';

    public function mount(): void
    {
        $this->redirect(ServiceResource::getUrl('create'));
    }

    public static function getRoutePath(): string
    {
        return static::$routePath;
    }
}
