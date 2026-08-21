<?php

namespace App\Filament\Clusters\TeknikServis\Pages;

class ServisTalepFormuSablonuSayfasi extends TeknikServisBaskiSablonuDuzenleyiciSayfasi
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $title = 'Servis talep formu şablonu';

    protected static ?string $navigationLabel = 'Talep formu şablonu';

    protected static ?string $navigationGroup = 'Ayarlar ve şablonlar';

    protected static ?int $navigationSort = 41;

    protected static ?string $slug = 'sablonlar/talep-formu';

    protected static function sablonTuru(): string
    {
        return 'talep_formu';
    }

    protected static function sayfaBasligi(): string
    {
        return 'Servis Talep Formu';
    }
}
