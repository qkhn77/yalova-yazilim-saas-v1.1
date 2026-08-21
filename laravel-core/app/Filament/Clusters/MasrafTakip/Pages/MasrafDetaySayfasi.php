<?php

namespace App\Filament\Clusters\MasrafTakip\Pages;

use App\Filament\Clusters\MasrafTakip as MasrafTakipCluster;
use App\Filament\Clusters\MasrafTakip\Kaynaklar\MasrafTakipSayfaErisimleri;
use App\Models\Muhasebe\Masraf;
use App\Services\TenantContextService;
use Filament\Actions\Action;
use Filament\Pages\Page;

class MasrafDetaySayfasi extends Page
{
    use MasrafTakipSayfaErisimleri;

    protected static ?string $cluster = MasrafTakipCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Masraf detayı';

    protected static ?string $slug = 'masraf/{record}';

    protected static string $view = 'filament.clusters.masraf-takip.pages.masraf-detay';

    public Masraf $masraf;

    public function mount(int|string $record): void
    {
        $this->masraf = Masraf::query()
            ->with([
                'kategori:id,ad,ust_kategori_id',
                'kategori.ustKategori:id,ad',
                'isletmeProjesi:id,kod,ad',
                'olusturanKullanici:id,name',
            ])
            ->where('firma_id', $this->aktifFirmaId() ?? 0)
            ->findOrFail($record);
    }

    public function getHeading(): string
    {
        return 'Masraf detayı';
    }

    public function getSubheading(): ?string
    {
        return $this->masraf->aciklama ?: 'Masraf hareketi bilgileri';
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('raporlaraDon')
                ->label('Raporlara dön')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(MasrafRaporlariSayfasi::getUrl()),
        ];
    }

    private function aktifFirmaId(): ?int
    {
        $firmaId = app(TenantContextService::class)->aktifFirmaId();

        return $firmaId ? (int) $firmaId : null;
    }
}
