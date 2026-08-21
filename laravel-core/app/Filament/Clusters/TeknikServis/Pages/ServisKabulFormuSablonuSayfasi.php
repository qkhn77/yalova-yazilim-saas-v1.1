<?php

namespace App\Filament\Clusters\TeknikServis\Pages;

class ServisKabulFormuSablonuSayfasi extends TeknikServisBaskiSablonuDuzenleyiciSayfasi
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?string $title = 'Servis kabul formu şablonu';

    protected static ?string $navigationLabel = 'Kabul formu şablonu';

    protected static ?string $navigationGroup = 'Ayarlar ve şablonlar';

    protected static ?int $navigationSort = 42;

    protected static ?string $slug = 'sablonlar/kabul-formu';

    protected static function sablonTuru(): string
    {
        return 'kabul_formu';
    }

    protected static function sayfaBasligi(): string
    {
        return 'Servis Kabul Formu';
    }
}
