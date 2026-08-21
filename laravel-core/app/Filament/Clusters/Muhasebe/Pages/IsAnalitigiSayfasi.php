<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe as MuhasebeCluster;
use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeSayfaErisimleri;
use App\Services\IsAnalitikServisi;
use App\Services\TenantContextService;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class IsAnalitigiSayfasi extends Page
{
    use MuhasebeSayfaErisimleri;

    protected static ?string $cluster = MuhasebeCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Is Analitigi';

    protected static ?string $slug = 'is-analitigi';

    protected static ?string $navigationLabel = 'Is Analitigi';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?int $navigationSort = 96;

    protected static string $view = 'filament.clusters.muhasebe.pages.is-analitigi-sayfasi';

    /** @var array<string, mixed>|null */
    protected ?array $analizOnbellek = null;

    public function getHeading(): string|Htmlable
    {
        return 'Is Analitigi';
    }

    public function getSubheading(): ?string
    {
        return 'Siparis, odeme, ciro ve urun performansi KPI gorunumu.';
    }

    protected static function gerekliYetkiKodu(): string
    {
        return MuhasebeYetkiSablonlari::MUHASEBE_GORUNTULE;
    }

    /**
     * @return array<string, mixed>
     */
    public function analiz(): array
    {
        if ($this->analizOnbellek !== null) {
            return $this->analizOnbellek;
        }

        $firmaId = app(TenantContextService::class)->aktifFirmaId();
        if ($firmaId === null) {
            return $this->analizOnbellek = ['firma_id' => null];
        }

        return $this->analizOnbellek = [
            'firma_id' => $firmaId,
            'data' => app(IsAnalitikServisi::class)->olustur($firmaId),
        ];
    }
}
