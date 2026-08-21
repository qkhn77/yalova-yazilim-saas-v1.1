<?php

namespace App\Filament\Clusters\TeknikServis\Pages;

class TeslimEdildiBelgesiSablonuSayfasi extends TeknikServisBaskiSablonuDuzenleyiciSayfasi
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $title = 'Teslim edildi belgesi şablonu';

    protected static ?string $navigationLabel = 'Teslim belgesi şablonu';

    protected static ?string $navigationGroup = 'Ayarlar ve şablonlar';

    protected static ?int $navigationSort = 43;

    protected static ?string $slug = 'sablonlar/teslim-belgesi';

    protected static function sablonTuru(): string
    {
        return 'teslim_belgesi';
    }

    protected static function sayfaBasligi(): string
    {
        return 'Teslim Edildi Belgesi';
    }
}
