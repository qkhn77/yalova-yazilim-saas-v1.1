<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ProjectResource;
use Filament\Pages\Page;

class ProjeEkle extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';
    protected static ?string $navigationGroup = 'Projeler';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Proje Ekle';
    protected static ?string $title = 'Yeni Proje';
    protected static string $view = 'filament.pages.redirect-placeholder';
    protected static string $routePath = 'projeler/ekle';

    public function mount(): void
    {
        $this->redirect(ProjectResource::getUrl('create'));
    }

    public static function getRoutePath(): string
    {
        return static::$routePath;
    }
}
