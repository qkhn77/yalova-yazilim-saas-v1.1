<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe as MuhasebeCluster;
use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeSayfaErisimleri;
use App\Muhasebe\Servisler\CariYaslandirmaServisi;
use App\Services\TenantContextService;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class CariYaslandirmaSayfasi extends Page implements HasForms
{
    use InteractsWithForms;
    use MuhasebeSayfaErisimleri;

    protected static ?string $cluster = MuhasebeCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Cari Yaşlandırma';

    protected static ?string $slug = 'cari-yonetimi/cari-yaslandirma';

    protected static string $view = 'filament.clusters.muhasebe.pages.cari-yaslandirma';

    /** @var array<int, array<string, mixed>> */
    public array $satirlar = [];

    public string $para_birimi = 'TRY';

    public function getTitle(): string|Htmlable
    {
        return 'Cari Yaşlandırma';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Cari Yaşlandırma';
    }

    public function getSubheading(): ?string
    {
        return 'Vade tarihi girilmiş hareketlerin gecikme gününe göre dağılımı (net: borç − alacak).';
    }

    protected static function gerekliYetkiKodu(): string
    {
        return MuhasebeYetkiSablonlari::CARI_GORUNTULE;
    }

    /**
     * @return array<int, string>
     */
    protected static function muhasebeSayfasiYetkiKodlari(): array
    {
        return [
            MuhasebeYetkiSablonlari::CARI_GORUNTULE,
            MuhasebeYetkiSablonlari::CARI_GUNCELLE,
        ];
    }

    public function mount(): void
    {
        $this->form->fill([
            'para_birimi' => 'TRY',
        ]);
        $this->yukle();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('para_birimi')
                    ->label('Para birimi')
                    ->options([
                        'TRY' => 'TRY',
                        'USD' => 'USD',
                        'EUR' => 'EUR',
                        'GBP' => 'GBP',
                    ])
                    ->live()
                    ->afterStateUpdated(fn () => $this->yukle()),
            ]);
    }

    public function yukle(): void
    {
        $firmaId = app(TenantContextService::class)->aktifFirmaId();
        if (! $firmaId) {
            $this->satirlar = [];

            return;
        }

        $para = (string) ($this->para_birimi ?: 'TRY');

        $this->satirlar = app(CariYaslandirmaServisi::class)
            ->rapor((int) $firmaId, $para)
            ->all();
    }
}
