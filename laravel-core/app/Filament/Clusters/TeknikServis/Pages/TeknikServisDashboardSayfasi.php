<?php

namespace App\Filament\Clusters\TeknikServis\Pages;

use App\Filament\Clusters\TeknikServis as TeknikServisCluster;
use App\Filament\Clusters\TeknikServis\Kaynaklar\TeknikServisSayfaErisimleri;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi\Pages\TeknikServisKayitlariAcikSayfasi;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi\Pages\TeknikServisKayitlariFiyatVerilenSayfasi;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi\Pages\TeknikServisKayitlariGarantiyeGonderilenSayfasi;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi\Pages\TeknikServisKayitlariParcaBekleyenSayfasi;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi\Pages\TeknikServisKayitlariTeslimBekleyenSayfasi;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi\Pages\TeknikServisKayitlariTeslimEdilenSayfasi;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi\Pages\TeknikServisKayitlariTezgahtaSayfasi;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi\Pages\TeknikServisKayitlariYeniSayfasi;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi;
use App\Models\TeknikServis\TeknikServisHatirlatma;
use App\Models\TeknikServis\TeknikServisKaydi;
use App\TeknikServis\Filament\TeknikServisListePreset;
use App\TeknikServis\Filament\TeknikServisListePresetleri;
use App\TeknikServis\Servisler\TeknikServisOkumaCache;
use Carbon\Carbon;
use Filament\Pages\Page;

class TeknikServisDashboardSayfasi extends Page
{
    use TeknikServisSayfaErisimleri;

    protected static ?string $cluster = TeknikServisCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $title = "Teknik servis \u{00F6}zeti";

    protected static ?string $navigationLabel = "\u{00D6}zet";

    protected static ?string $navigationGroup = "\u{00D6}zet";

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.clusters.teknik-servis.pages.dashboard';

    protected static ?string $slug = 'ozet';

    public function getHeading(): string
    {
        return 'Teknik servis özeti';
    }

    public function getSubheading(): ?string
    {
        return 'Açık işler, öncelikli kayıtlar ve hızlı servis işlemleri.';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'hizliIslemler' => [
                ['etiket' => 'Arızalı Cihaz Kaydı', 'url' => TeknikServisKaydiKaynagi::getUrl('create_arizali'), 'renk' => 'primary'],
                ['etiket' => 'Bakım Kaydı', 'url' => TeknikServisKaydiKaynagi::getUrl('create_bakim'), 'renk' => 'success'],
                ['etiket' => 'Dış Servis Kaydı', 'url' => TeknikServisKaydiKaynagi::getUrl('create_dis_servis'), 'renk' => 'warning'],
            ],
            'acikListeUrl' => TeknikServisKayitlariAcikSayfasi::getUrl(),
            'tumKayitlarUrl' => TeknikServisKaydiKaynagi::getUrl('index'),
            'durumKartlari' => $this->durumKartlari(),
            'listeKisayollari' => $this->listeKisayollari(),
            'operasyonMetrikleri' => $this->operasyonMetrikleri(),
        ];
    }

    /**
     * @return array<int, array{baslik:string,deger:int,url:string,renk:string,ikon:string}>
     */
    private function durumKartlari(): array
    {
        $kartlar = [
            ['baslik' => 'Açık Servisler', 'preset' => TeknikServisListePreset::Acik, 'url' => TeknikServisKayitlariAcikSayfasi::getUrl(), 'renk' => 'primary', 'ikon' => 'heroicon-m-clipboard-document-list'],
            ['baslik' => 'Tezgahtakiler', 'preset' => TeknikServisListePreset::Tezgahta, 'url' => TeknikServisKayitlariTezgahtaSayfasi::getUrl(), 'renk' => 'info', 'ikon' => 'heroicon-m-wrench-screwdriver'],
            ['baslik' => 'Parça Bekleyen', 'preset' => TeknikServisListePreset::ParcaBekleyen, 'url' => TeknikServisKayitlariParcaBekleyenSayfasi::getUrl(), 'renk' => 'warning', 'ikon' => 'heroicon-m-cube'],
            ['baslik' => 'Garantiye Giden', 'preset' => TeknikServisListePreset::GarantiyeGonderilen, 'url' => TeknikServisKayitlariGarantiyeGonderilenSayfasi::getUrl(), 'renk' => 'gray', 'ikon' => 'heroicon-m-truck'],
        ];

        $sayimlar = $this->servisSayimlari();

        return array_map(function (array $kart) use ($sayimlar): array {
            return [
                'baslik' => $kart['baslik'],
                'deger' => $this->presetSayisi($sayimlar, $kart['preset']),
                'url' => $kart['url'],
                'renk' => $kart['renk'],
                'ikon' => $kart['ikon'],
            ];
        }, $kartlar);
    }

    /**
     * @return array<int, array{baslik:string,aciklama:string,deger:int,url:string,renk:string}>
     */
    private function listeKisayollari(): array
    {
        $sayimlar = $this->servisSayimlari();

        $kisayollar = [
            ['baslik' => 'Yeni kayıtlar', 'aciklama' => 'Kabul sonrası ilk işlem bekleyenler', 'preset' => TeknikServisListePreset::Yeni, 'url' => TeknikServisKayitlariYeniSayfasi::getUrl(), 'renk' => 'primary'],
            ['baslik' => 'Fiyat verilenler', 'aciklama' => 'Müşteri onayı bekleyen servisler', 'preset' => TeknikServisListePreset::FiyatVerilen, 'url' => TeknikServisKayitlariFiyatVerilenSayfasi::getUrl(), 'renk' => 'info'],
            ['baslik' => 'Teslim bekleyenler', 'aciklama' => 'İşi bitmiş, teslim aşamasındaki kayıtlar', 'preset' => TeknikServisListePreset::TeslimBekleyen, 'url' => TeknikServisKayitlariTeslimBekleyenSayfasi::getUrl(), 'renk' => 'success'],
            ['baslik' => 'Tüm servis kayıtları', 'aciklama' => 'Arama, filtre ve detaylı kayıt listesi', 'preset' => TeknikServisListePreset::Tum, 'url' => TeknikServisKaydiKaynagi::getUrl('index'), 'renk' => 'gray'],
        ];

        return array_map(function (array $kisayol) use ($sayimlar): array {
            return [
                'baslik' => $kisayol['baslik'],
                'aciklama' => $kisayol['aciklama'],
                'deger' => $this->presetSayisi($sayimlar, $kisayol['preset']),
                'url' => $kisayol['url'],
                'renk' => $kisayol['renk'],
            ];
        }, $kisayollar);
    }

    /**
     * @return array<int, array{baslik:string,aciklama:string,deger:int,url:string,renk:string,ikon:string}>
     */
    private function operasyonMetrikleri(): array
    {
        $sayimlar = $this->servisSayimlari();
        $ekMetrikler = app(TeknikServisOkumaCache::class)->remember('dashboard:operasyon-metrikleri', static function (): array {
            $ayBaslangici = Carbon::now()->startOfMonth()->startOfDay();
            $ayBitisi = Carbon::now()->endOfDay();
            $bugun = Carbon::today();
            $bakimBitis = Carbon::today()->addDays(30);

            return [
                'bu_ay_teslim_edilen' => TeknikServisListePresetleri::uygula(
                    TeknikServisKaydi::query()->whereBetween('teslim_tarihi', [$ayBaslangici, $ayBitisi]),
                    TeknikServisListePreset::TeslimEdilen
                )->count(),
                'garantili_cihazlar' => TeknikServisKaydi::query()
                    ->whereNotNull('garanti_bitis_tarihi')
                    ->whereDate('garanti_bitis_tarihi', '>=', $bugun->toDateString())
                    ->count(),
                'bakimi_yaklasan' => TeknikServisHatirlatma::query()
                    ->where('hatirlatma_tipi', 'bakim')
                    ->where('durum', 'aktif')
                    ->whereNotNull('sonraki_tarih')
                    ->whereBetween('sonraki_tarih', [$bugun->toDateString(), $bakimBitis->toDateString()])
                    ->count(),
            ];
        });

        return [
            [
                'baslik' => 'Teslim bekleyen',
                'aciklama' => 'Teslime hazır kayıtlar',
                'deger' => $this->presetSayisi($sayimlar, TeknikServisListePreset::TeslimBekleyen),
                'url' => TeknikServisKayitlariTeslimBekleyenSayfasi::getUrl(),
                'renk' => 'success',
                'ikon' => 'heroicon-m-check-circle',
            ],
            [
                'baslik' => 'Bu ay teslim edilen',
                'aciklama' => Carbon::now()->translatedFormat('F').' teslimleri',
                'deger' => (int) ($ekMetrikler['bu_ay_teslim_edilen'] ?? 0),
                'url' => TeknikServisKayitlariTeslimEdilenSayfasi::getUrl(),
                'renk' => 'primary',
                'ikon' => 'heroicon-m-archive-box',
            ],
            [
                'baslik' => 'Garantili cihazlar',
                'aciklama' => 'Garantisi devam eden cihazlar',
                'deger' => (int) ($ekMetrikler['garantili_cihazlar'] ?? 0),
                'url' => GarantiliCihazlarSayfasi::getUrl(['durum' => 'aktif']),
                'renk' => 'info',
                'ikon' => 'heroicon-m-shield-check',
            ],
            [
                'baslik' => 'Bakımı yaklaşan',
                'aciklama' => 'Önümüzdeki 30 gün',
                'deger' => (int) ($ekMetrikler['bakimi_yaklasan'] ?? 0),
                'url' => BakimHatirlatmalariSayfasi::getUrl(['durum' => 'planlandi']),
                'renk' => 'warning',
                'ikon' => 'heroicon-m-bell-alert',
            ],
        ];
    }

    /**
     * @return array<int, array{servis_durumu_id:int,servis_tipi:string,toplam:int}>
     */
    private function servisSayimlari(): array
    {
        return app(TeknikServisOkumaCache::class)->remember('dashboard:servis-sayimlari', static function (): array {
            return TeknikServisKaydi::query()
                ->select(['servis_durumu_id', 'servis_tipi'])
                ->selectRaw('COUNT(*) as toplam')
                ->groupBy('servis_durumu_id', 'servis_tipi')
                ->toBase()
                ->get()
                ->map(static fn (object $kayit): array => [
                    'servis_durumu_id' => (int) ($kayit->servis_durumu_id ?? 0),
                    'servis_tipi' => (string) ($kayit->servis_tipi ?? ''),
                    'toplam' => (int) ($kayit->toplam ?? 0),
                ])
                ->all();
        });
    }

    /**
     * @param  array<int, array{servis_durumu_id:int,servis_tipi:string,toplam:int}>  $sayimlar
     */
    private function presetSayisi(array $sayimlar, TeknikServisListePreset $preset): int
    {
        $durumIdleri = TeknikServisListePresetleri::durumIdleriPresetIcin($preset);
        $servisTipleri = TeknikServisListePresetleri::servisTipleriPresetIcin($preset);
        $adet = 0;

        foreach ($sayimlar as $sayim) {
            if ($durumIdleri !== null && ! in_array($sayim['servis_durumu_id'], $durumIdleri, true)) {
                continue;
            }

            if ($servisTipleri !== null && ! in_array($sayim['servis_tipi'], $servisTipleri, true)) {
                continue;
            }

            $adet += $sayim['toplam'];
        }

        return $adet;
    }
}
