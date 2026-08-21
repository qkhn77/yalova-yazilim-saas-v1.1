<?php

namespace App\Filament\Clusters\Muhasebe;

use App\Filament\Clusters\Muhasebe as MuhasebeCluster;
use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeSayfaErisimleri;
use Filament\Pages\Page;

/**
 * Muhasebe kümesi: veritabanı sorgusu yapmayan boş sayfa iskeleti.
 *
 * Erişim: {@see MuhasebeSayfaErisimleri} — süper yönetici bypass + kiracıda `SidebarService::menuGorunurMu(..., 'muhasebe', gerekliYetkiKodu)`.
 */
abstract class MuhasebeTaslakSayfa extends Page
{
    use MuhasebeSayfaErisimleri;

    protected static ?string $cluster = MuhasebeCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.clusters.muhasebe.pages.taslak';

    /**
     * İşlem bazlı yetki kodu (örn. cari.goruntule).
     */
    abstract protected static function gerekliYetkiKodu(): string;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'aciklama' => static::taslakAciklamasi(),
        ];
    }

    protected static function taslakAciklamasi(): string
    {
        return 'Bu ekran yapısal iskelettir. İş mantığı ve veritabanı bağlantısı sonraki aşamalarda eklenecektir.';
    }
}
