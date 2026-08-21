<?php

namespace App\Filament\Clusters\TeknikServis\Pages;

class ServisFormuSablonuSayfasi extends TeknikServisBaskiSablonuDuzenleyiciSayfasi
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $title = 'Teknik Servis Formu';

    protected static ?string $navigationLabel = 'Teknik Servis Formu';

    protected static ?string $navigationGroup = 'Ayarlar ve şablonlar';

    protected static ?int $navigationSort = 44;

    protected static ?string $slug = 'sablonlar/servis-formu';

    protected static function sablonTuru(): string
    {
        return 'servis_formu';
    }

    protected static function sayfaBasligi(): string
    {
        return 'Teknik Servis Formu';
    }
}
