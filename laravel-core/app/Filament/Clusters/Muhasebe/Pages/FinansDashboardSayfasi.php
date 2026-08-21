<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe as MuhasebeCluster;
use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeSayfaErisimleri;
use App\Models\Muhasebe\BankaHareketi;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\Muhasebe\KasaHareketi;
use App\Models\Muhasebe\PosHareketi;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Muhasebe\Enumlar\FinansHareketDurumu;
use App\Muhasebe\Enumlar\FinansHareketTuru;
use App\Muhasebe\Enumlar\HareketDurumu;
use App\Muhasebe\Guvenlik\MuhasebeFilamentErisimYardimcisi;
use App\Muhasebe\Servisler\DovizKurServisi;
use App\Services\TenantContextService;
use App\Support\MuhasebeYetkiSablonlari;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class FinansDashboardSayfasi extends Page
{
    use MuhasebeSayfaErisimleri;

    protected static ?string $cluster = MuhasebeCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Finans';

    protected static ?string $slug = 'finans/finans-panel';

    protected static string $view = 'filament.clusters.muhasebe.pages.finans-dashboard';

    /** @var array<string, mixed>|null */
    protected ?array $ozetOnbellek = null;

    /** @var array<string, string|null> */
    protected array $tlKurCache = [];

    public function getHeading(): string|Htmlable
    {
        return 'Finans paneli';
    }

    public function getSubheading(): ?string
    {
        return 'Tahsilat ve ödeme özetleri, kasa/banka/POS durumu ve son hareketler.';
    }

    protected static function gerekliYetkiKodu(): string
    {
        return MuhasebeYetkiSablonlari::FINANS_GORUNTULE;
    }

    public function getSubNavigation(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function ozet(): array
    {
        if ($this->ozetOnbellek !== null) {
            return $this->ozetOnbellek;
        }

        $fid = app(TenantContextService::class)->aktifFirmaId();
        if ($fid === null) {
            return $this->ozetOnbellek = ['firma_id' => null];
        }

        return $this->ozetOnbellek = Cache::remember(
            'muhasebe:finans-dashboard-ozet:v2:firma:'.$fid,
            now()->addSeconds(60),
            fn (): array => $this->ozetHesapla($fid)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function ozetHesapla(int $fid): array
    {
        $bugunBas = Carbon::today()->startOfDay();
        $bugunSon = Carbon::today()->endOfDay();

        $gunlukToplamlar = $this->finansGunlukToplamlari($fid, $bugunBas, $bugunSon);
        $tahsilatBugun = $gunlukToplamlar[FinansHareketTuru::Tahsilat->value] ?? collect();
        $odemeBugun = $gunlukToplamlar[FinansHareketTuru::Odeme->value] ?? collect();

        $kasaToplamlar = $this->tlKarsiliklariniEkle($this->hesapHareketToplamlari(KasaHareketi::class, $fid));
        $bankaToplamlar = $this->tlKarsiliklariniEkle($this->hesapHareketToplamlari(BankaHareketi::class, $fid));
        $posToplamlar = $this->tlKarsiliklariniEkle($this->hesapHareketToplamlari(PosHareketi::class, $fid));

        $acikTahsilat = $this->tlKarsiliklariniEkle($this->acikTahsilatFaturaToplami($fid));

        $sonHareketler = FinansHareketi::query()
            ->where('firma_id', $fid)
            ->where('durum', FinansHareketDurumu::Aktif)
            ->with([
                'cari:id,ad,kod',
                'kasaHareketleri' => fn ($query) => $query
                    ->where('durum', HareketDurumu::Aktif)
                    ->select(['id', 'finans_hareket_id', 'tutar', 'para_birimi']),
                'bankaHareketleri' => fn ($query) => $query
                    ->where('durum', HareketDurumu::Aktif)
                    ->select(['id', 'finans_hareket_id', 'tutar', 'para_birimi']),
                'posHareketleri' => fn ($query) => $query
                    ->where('durum', HareketDurumu::Aktif)
                    ->select(['id', 'finans_hareket_id', 'tutar', 'para_birimi']),
                'referansFaturasi:id,kaynak_tipi',
                'iptalEdilenHareket:id,referans_turu,referans_id,iptal_edilen_hareket_id',
                'iptalEdilenHareket.referansFaturasi:id,kaynak_tipi',
            ])
            ->withExists([
                'teknikServisTahsilatlari as teknik_servis_tahsilat_kaynagi',
                'iptalTeknikServisTahsilatlari as teknik_servis_iptal_tahsilat_kaynagi',
                'teknikServisMuhasebeBaglantilari as teknik_servis_baglanti_kaynagi',
            ])
            ->orderByDesc('tarih')
            ->orderByDesc('id')
            ->limit(40)
            ->get(['id', 'tur', 'tarih', 'tutar', 'para_birimi', 'cari_id', 'referans_turu', 'referans_id', 'aciklama']);

        $sonFinans = $sonHareketler->take(10)->values();
        $sonTahsilat = $sonHareketler
            ->filter(fn (FinansHareketi $hareket): bool => $this->finansHareketTuruDegeri($hareket) === FinansHareketTuru::Tahsilat->value)
            ->take(8)
            ->values();
        $sonOdeme = $sonHareketler
            ->filter(fn (FinansHareketi $hareket): bool => $this->finansHareketTuruDegeri($hareket) === FinansHareketTuru::Odeme->value)
            ->take(8)
            ->values();

        return [
            'firma_id' => $fid,
            'ozet_zamani' => now()->format('d.m.Y H:i'),
            'kpi' => [
                'tahsilat_bugun' => $tahsilatBugun,
                'odeme_bugun' => $odemeBugun,
                'net_akis_bugun' => $this->paraBirimiFarklari($tahsilatBugun, $odemeBugun),
                'kasa' => $kasaToplamlar,
                'banka' => $bankaToplamlar,
                'pos' => $posToplamlar,
                'acik_tahsilat' => $acikTahsilat,
            ],
            'son_finans' => $sonFinans,
            'son_tahsilat' => $sonTahsilat,
            'son_odeme' => $sonOdeme,
        ];
    }

    protected function getHeaderActions(): array
    {
        $olustur = MuhasebeFilamentErisimYardimcisi::muhasebeYetkisiVarMi(MuhasebeYetkiSablonlari::FINANS_OLUSTUR);

        return [
            Actions\Action::make('tahsilat')
                ->label('Tahsilat ekle')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(TahsilatOlusturSayfasi::getUrl())
                ->visible(fn (): bool => $olustur)
                ->color('success'),
            Actions\Action::make('odeme')
                ->label('Ödeme ekle')
                ->icon('heroicon-o-arrow-up-tray')
                ->url(OdemeOlusturSayfasi::getUrl())
                ->visible(fn (): bool => $olustur)
                ->color('warning'),
            Actions\Action::make('transfer')
                ->label('Transfer ekle')
                ->icon('heroicon-o-arrow-right-circle')
                ->url(TransferOlusturSayfasi::getUrl())
                ->visible(fn (): bool => $olustur)
                ->color('info'),
            Actions\Action::make('hareketler')
                ->label('Tüm finans hareketleri')
                ->icon('heroicon-o-list-bullet')
                ->url(FinansHareketleriListesiSayfasi::getUrl())
                ->color('gray'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function finansGunlukToplamlari(int $firmaId, Carbon $bas, Carbon $bit): array
    {
        return FinansHareketi::query()
            ->where('firma_id', $firmaId)
            ->where('durum', FinansHareketDurumu::Aktif)
            ->whereIn('tur', [FinansHareketTuru::Tahsilat->value, FinansHareketTuru::Odeme->value])
            ->whereBetween('tarih', [$bas, $bit])
            ->selectRaw('tur, para_birimi, SUM(tutar) as toplam')
            ->groupBy('tur', 'para_birimi')
            ->get()
            ->groupBy(fn (FinansHareketi $satir): string => $this->finansHareketTuruDegeri($satir))
            ->map(fn ($rows) => $rows->map(fn ($row) => (object) [
                'para_birimi' => strtoupper((string) ($row->para_birimi ?: 'TRY')),
                'toplam' => number_format((float) $row->toplam, 2, '.', ''),
            ])->values())
            ->all();
    }

    private function paraBirimiFarklari(Collection $girisler, Collection $cikislar): Collection
    {
        $birimler = $girisler->pluck('para_birimi')->merge($cikislar->pluck('para_birimi'))->unique();

        return $birimler->map(function (string $paraBirimi) use ($girisler, $cikislar): object {
            $girisSatiri = $girisler->firstWhere('para_birimi', $paraBirimi);
            $cikisSatiri = $cikislar->firstWhere('para_birimi', $paraBirimi);
            $giris = (string) ($girisSatiri?->toplam ?? '0');
            $cikis = (string) ($cikisSatiri?->toplam ?? '0');

            return (object) [
                'para_birimi' => $paraBirimi,
                'toplam' => bcsub($giris, $cikis, 2),
            ];
        })->values();
    }

    private function finansHareketTuruDegeri(FinansHareketi $hareket): string
    {
        return $hareket->tur instanceof FinansHareketTuru
            ? $hareket->tur->value
            : (string) $hareket->tur;
    }

    /**
     * @param  class-string<KasaHareketi|BankaHareketi|PosHareketi>  $model
     * @return Collection<int, object{para_birimi: string, toplam: string}>
     */
    private function hesapHareketToplamlari(string $model, int $firmaId): Collection
    {
        return $model::query()
            ->where('firma_id', $firmaId)
            ->where('durum', HareketDurumu::Aktif)
            ->selectRaw('para_birimi, SUM(tutar) as toplam')
            ->groupBy('para_birimi')
            ->get()
            ->map(fn ($r) => (object) [
                'para_birimi' => (string) $r->para_birimi,
                'toplam' => number_format((float) $r->toplam, 2, '.', ''),
            ]);
    }

    private function tlKarsiliklariniEkle(Collection $satirlar): Collection
    {
        return $satirlar->map(function (object $satir): object {
            $paraBirimi = strtoupper((string) ($satir->para_birimi ?: 'TRY'));
            $kur = $this->tlKuru($paraBirimi);
            $tlToplam = $kur !== null
                ? bcmul((string) ($satir->toplam ?? '0'), $kur, 2)
                : null;

            $satir->tl_kur = $kur;
            $satir->tl_toplam = $tlToplam;

            return $satir;
        });
    }

    private function tlKuru(string $paraBirimi): ?string
    {
        $paraBirimi = strtoupper(trim($paraBirimi));
        if ($paraBirimi === 'TRY') {
            return '1.00000000';
        }

        if (array_key_exists($paraBirimi, $this->tlKurCache)) {
            return $this->tlKurCache[$paraBirimi];
        }

        try {
            $sonuc = app(DovizKurServisi::class)->otomatikKurGetir($paraBirimi, 'TRY', Carbon::today()->toDateString());
            $kur = number_format((float) ($sonuc['kur'] ?? 0), 8, '.', '');

            return $this->tlKurCache[$paraBirimi] = (float) $kur > 0 ? $kur : null;
        } catch (\Throwable) {
            return $this->tlKurCache[$paraBirimi] = null;
        }
    }

    private function acikTahsilatFaturaToplami(int $firmaId): Collection
    {
        $turler = [
            FaturaTuru::Giden->value,
            FaturaTuru::GidenFatura->value,
            FaturaTuru::AlisIadesi->value,
        ];

        return Fatura::query()
            ->where('firma_id', $firmaId)
            ->where('durum', FaturaDurumu::Onayli)
            ->whereIn('tur', $turler)
            ->whereRaw('CAST(acik_tutar AS DECIMAL(18,4)) > 0')
            ->selectRaw('para_birimi, SUM(acik_tutar) as toplam')
            ->groupBy('para_birimi')
            ->get()
            ->map(fn ($row): object => (object) [
                'para_birimi' => strtoupper((string) ($row->para_birimi ?: 'TRY')),
                'toplam' => number_format((float) $row->toplam, 2, '.', ''),
            ]);
    }

    public static function faturaTahsilatUrl(int $faturaId): string
    {
        return static::tahsilatUrlQuery(['fatura_id' => $faturaId]);
    }

    public static function cariTahsilatUrl(int $cariId): string
    {
        return static::tahsilatUrlQuery(['cari_id' => $cariId]);
    }

    public static function cariOdemeUrl(int $cariId): string
    {
        return static::odemeUrlQuery(['cari_id' => $cariId]);
    }

    public static function faturaOdemeUrl(int $faturaId): string
    {
        return static::odemeUrlQuery(['fatura_id' => $faturaId]);
    }

    /**
     * @param  array<string, int>  $query
     */
    public static function tahsilatUrlQuery(array $query): string
    {
        return TahsilatOlusturSayfasi::getUrl().'?'.http_build_query($query);
    }

    /**
     * @param  array<string, int>  $query
     */
    public static function odemeUrlQuery(array $query): string
    {
        return OdemeOlusturSayfasi::getUrl().'?'.http_build_query($query);
    }
}
