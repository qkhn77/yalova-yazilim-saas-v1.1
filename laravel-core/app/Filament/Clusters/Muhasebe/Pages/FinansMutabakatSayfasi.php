<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe as MuhasebeCluster;
use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeSayfaErisimleri;
use App\Muhasebe\Servisler\MuhasebeSistemDogrulamaServisi;
use App\Services\TenantContextService;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class FinansMutabakatSayfasi extends Page
{
    use MuhasebeSayfaErisimleri;

    protected static ?string $cluster = MuhasebeCluster::class;

    protected static ?string $title = 'Finans mutabakatı';

    protected static ?string $slug = 'finans/finans-mutabakat';

    protected static ?string $navigationLabel = 'Finans mutabakatı';

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?int $navigationSort = 25;

    protected static string $view = 'filament.clusters.muhasebe.pages.finans-mutabakat-sayfasi';

    /** @var array<int,array{kod:string,detay:string,firma_id:int,kaynak_id:int,seviye:string}> */
    public array $sorunlar = [];

    public int $kontrolEdilen = 0;

    protected static function gerekliYetkiKodu(): string
    {
        return MuhasebeYetkiSablonlari::MUHASEBE_GORUNTULE;
    }

    public function getHeading(): string|Htmlable
    {
        return 'Finans mutabakatı';
    }

    public function getSubheading(): ?string
    {
        return 'Finans hareketlerini kaynak modüller, cari ve veresiye bağlantılarıyla salt okunur karşılaştırın.';
    }

    public function mount(): void
    {
        $this->kontroluCalistir();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('yenile')
                ->label('Kontrolü yenile')
                ->icon('heroicon-o-arrow-path')
                ->action(function (): void {
                    $this->kontroluCalistir();
                }),
        ];
    }

    public function kontroluCalistir(): void
    {
        $firmaId = app(TenantContextService::class)->aktifFirmaId();
        if ($firmaId === null) {
            $this->sorunlar = [];
            $this->kontrolEdilen = 0;

            Notification::make()->title('Aktif firma seçilmedi')->warning()->send();

            return;
        }

        $sonuclar = app(MuhasebeSistemDogrulamaServisi::class)
            ->sistemTutarlilikKontrolu((int) $firmaId, false);
        $this->kontrolEdilen = count($sonuclar);
        $this->sorunlar = array_map(static function (array $sorun): array {
            $kod = (string) ($sorun['kod'] ?? '');
            $sorun['seviye'] = in_array($kod, [
                'teknik_tahsilat_finans_iptal',
                'restoran_tahsilat_finans_iptal',
                'finans_tutar_gecersiz',
            ], true) ? 'kritik' : 'uyarı';

            return $sorun;
        }, $sonuclar);
    }
}
