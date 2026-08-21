<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class ModulSlider extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-photo';
    protected static ?string $navigationGroup = 'Modüller';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Slider';
    protected static ?string $title = 'Slider Düzenleme';
    protected static string $view = 'filament.pages.modul-placeholder';
    protected static string $routePath = 'moduller/slider';

    public static function getRoutePath(): string
    {
        return static::$routePath;
    }
}
