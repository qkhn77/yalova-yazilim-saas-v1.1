<?php

namespace App\Filament\Clusters\TeknikServis\Pages;

class ServisFisiSablonuSayfasi extends TeknikServisBaskiSablonuDuzenleyiciSayfasi
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $title = 'Servis fişi şablonu';

    protected static ?string $navigationLabel = 'Servis fişi şablonu';

    protected static ?string $navigationGroup = 'Ayarlar ve şablonlar';

    protected static ?int $navigationSort = 43;

    protected static ?string $slug = 'sablonlar/servis-fisi';

    protected static function sablonTuru(): string
    {
        return 'servis_fisi';
    }

    protected static function sayfaBasligi(): string
    {
        return 'Servis Fişi';
    }
}
