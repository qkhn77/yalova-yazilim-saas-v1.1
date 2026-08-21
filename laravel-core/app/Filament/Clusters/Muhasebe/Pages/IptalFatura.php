<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Muhasebe\Enumlar\FaturaDurumu;

class IptalFatura extends FaturaListesiFiltreliSayfasi
{
    protected static ?string $title = 'Iptal Faturalar';

    protected static ?string $slug = 'faturalar/iptal-faturalar';

    public static function faturaTurleri(): array
    {
        return [];
    }

    public static function faturaDurumlari(): array
    {
        return [FaturaDurumu::Iptal->value];
    }

    protected static function olusturmaSayfasiAnahtari(): string
    {
        return 'createIptal';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
