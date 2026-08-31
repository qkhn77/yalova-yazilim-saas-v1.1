<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe as MuhasebeCluster;
use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeSayfaErisimleri;
use App\Filament\Clusters\Muhasebe\Resources\CariKartiKaynagi;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\Muhasebe\StokKarti;
use App\Models\SistemOlayi;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FinansHareketDurumu;
use App\Muhasebe\Enumlar\FinansHareketTuru;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Servisler\MuhasebeSistemDogrulamaServisi;
use App\Services\TenantContextService;
use App\Support\MuhasebeYetkiSablonlari;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cache;

class MuhasebeDashboardSayfasi extends Page
{
    use MuhasebeSayfaErisimleri;

    protected static ?string $cluster = MuhasebeCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Muhasebe';

    protected static ?string $slug = 'muhasebe-panel';

    protected static string $view = 'filament.clusters.muhasebe.pages.muhasebe-dashboard';

    /** @var array<string, mixed>|null */
    protected ?array $ozetOnbellek = null;

    public bool $sistemDetaylariYuklendi = false;

    public function getHeading(): string|Htmlable
    {
        return 'Muhasebe özeti';
    }

    public function getSubheading(): ?string
    {
        return 'Tahsilat, ödeme, alacak riski ve stok sinyalleri — tek ekranda.';
    }

    protected static function gerekliYetkiKodu(): string
    {
        return MuhasebeYetkiSablonlari::MUHASEBE_GORUNTULE;
    }

    public function getSubNavigation(): array
    {
        return [];
    }

    public function sistemDetaylariniYukle(): void
    {
        $this->sistemDetaylariYuklendi = true;
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
            return $this->ozetOnbellek = [
                'firma_id' => null,
            ];
        }

        return $this->ozetOnbellek = Cache::remember(
            $this->ozetCacheKey($fid),
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
        $ayBas = Carbon::now()->startOfMonth()->startOfDay();
        $aySon = Carbon::now()->endOfDay();

        $finansToplamlari = $this->finansToplamlari($fid, $bugunBas, $bugunSon, $ayBas, $aySon);
        $tahsilatBugun = $finansToplamlari['tahsilat_bugun'];
        $tahsilatAy = $finansToplamlari['tahsilat_ay'];
        $odemeBugun = $finansToplamlari['odeme_bugun'];
        $odemeAy = $finansToplamlari['odeme_ay'];

        $acikFatura = $this->acikFaturaOzeti($fid);
        $stokOzeti = $this->stokOzeti($fid);
        $sonFaturalar = Fatura::query()
            ->where('firma_id', $fid)
            ->where('durum', FaturaDurumu::Onayli)
            ->with(['cari:id,ad,kod'])
            ->orderByDesc('tarih')
            ->orderByDesc('id')
            ->limit(8)
            ->get(['id', 'fatura_no', 'cari_id', 'tur', 'tarih', 'vade_tarihi', 'odeme_durumu', 'para_birimi', 'acik_tutar', 'genel_toplam']);

        $sonFinans = FinansHareketi::query()
            ->where('firma_id', $fid)
            ->where('durum', FinansHareketDurumu::Aktif)
            ->with(['cari:id,ad,kod'])
            ->orderByDesc('tarih')
            ->orderByDesc('id')
            ->limit(8)
            ->get(['id', 'tur', 'tarih', 'tutar', 'para_birimi', 'cari_id', 'aciklama']);

        $mutabakatUyari = Cache::get('barkodlu_satis:mutabakat:sonuc:firma:'.$fid);
        if (! is_array($mutabakatUyari)) {
            $mutabakatUyari = Cache::get('barkodlu_satis:mutabakat:sonuc:global');
        }

        return [
            'firma_id' => $fid,
            'kpi' => [
                'tahsilat_bugun' => $tahsilatBugun,
                'tahsilat_ay' => $tahsilatAy,
                'odeme_bugun' => $odemeBugun,
                'odeme_ay' => $odemeAy,
                'net_akis_bugun' => bcsub($tahsilatBugun, $odemeBugun, 2),
                'acik_fatura' => $acikFatura['toplam'],
                'acik_fatura_sayisi' => $acikFatura['sayisi'],
                'vadesi_gecmis_acik' => $acikFatura['vadesi_gecmis_toplam'],
                'vadesi_gecmis_acik_sayisi' => $acikFatura['vadesi_gecmis_sayisi'],
                'kritik_stok' => $stokOzeti['kritik'],
                'negatif_stok' => $stokOzeti['negatif'],
                'stok_degeri' => $stokOzeti['degeri'],
            ],
            'son_faturalar' => $sonFaturalar,
            'son_finans' => $sonFinans,
            'barkodlu_satis_mutabakat' => is_array($mutabakatUyari) ? $mutabakatUyari : null,
        ];
    }

    /**
     * @return array{tutarsizliklar: array<int, array<string, mixed>>, sistem_uyarilari: mixed}
     */
    public function sistemDetaylari(): array
    {
        $fid = app(TenantContextService::class)->aktifFirmaId();
        if ($fid === null) {
            return [
                'tutarsizliklar' => [],
                'sistem_uyarilari' => collect(),
            ];
        }

        $detaylar = Cache::remember(
            'muhasebe:dashboard-sistem-detaylari:v1:firma:'.$fid,
            now()->addMinutes(5),
            fn (): array => [
                'tutarsizliklar' => app(MuhasebeSistemDogrulamaServisi::class)->sistemTutarlilikKontrolu($fid, false),
                'sistem_uyarilari' => SistemOlayi::query()
                    ->where('firma_id', $fid)
                    ->whereIn('seviye', ['error', 'critical', 'warning'])
                    ->orderByDesc('id')
                    ->limit(10)
                    ->get(['id', 'tip', 'seviye', 'mesaj', 'created_at']),
            ]
        );

        return [
            'tutarsizliklar' => $detaylar['tutarsizliklar'] ?? [],
            'sistem_uyarilari' => $detaylar['sistem_uyarilari'] ?? collect(),
        ];
    }

    private function ozetCacheKey(int $firmaId): string
    {
        return 'muhasebe:dashboard-ozet:v2:firma:'.$firmaId;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('tumFaturalar')
                ->label('Tüm faturalar')
                ->icon('heroicon-o-document-text')
                ->url(TumFaturalarSayfasi::getUrl())
                ->color('gray'),
            Actions\Action::make('kritikStok')
                ->label('Kritik stoklar')
                ->icon('heroicon-o-cube')
                ->url(KritikStoklarSayfasi::getUrl())
                ->color('gray'),
            Actions\Action::make('cariler')
                ->label('Cariler')
                ->icon('heroicon-o-users')
                ->url(CariKartiKaynagi::getUrl())
                ->color('gray'),
        ];
    }

    /**
     * @return array{tahsilat_bugun: string, tahsilat_ay: string, odeme_bugun: string, odeme_ay: string}
     */
    private function finansToplamlari(int $firmaId, Carbon $bugunBas, Carbon $bugunSon, Carbon $ayBas, Carbon $aySon): array
    {
        $bazParaBirimi = strtoupper((string) config('muhasebe.coklu_para_birimi.baz_para_birimi', 'TRY'));
        $bazTutar = "COALESCE(baz_tutar, CASE WHEN UPPER(COALESCE(para_birimi, 'TRY')) = ? THEN tutar ELSE 0 END)";
        $toplamlar = FinansHareketi::query()
            ->where('firma_id', $firmaId)
            ->where('durum', FinansHareketDurumu::Aktif)
            ->whereIn('tur', [FinansHareketTuru::Tahsilat, FinansHareketTuru::Odeme])
            ->whereBetween('tarih', [$ayBas, $aySon])
            ->selectRaw(
                'tur,
                SUM(CASE WHEN tarih BETWEEN ? AND ? THEN '.$bazTutar.' ELSE 0 END) AS bugun,
                SUM('.$bazTutar.') AS ay',
                [$bugunBas, $bugunSon, $bazParaBirimi, $bazParaBirimi]
            )
            ->groupBy('tur')
            ->get()
            ->keyBy(fn (FinansHareketi $hareket): string => $hareket->tur instanceof FinansHareketTuru
                ? $hareket->tur->value
                : (string) $hareket->tur);

        return [
            'tahsilat_bugun' => $this->normalizeDecimal((string) ($toplamlar[FinansHareketTuru::Tahsilat->value]->bugun ?? '0')),
            'tahsilat_ay' => $this->normalizeDecimal((string) ($toplamlar[FinansHareketTuru::Tahsilat->value]->ay ?? '0')),
            'odeme_bugun' => $this->normalizeDecimal((string) ($toplamlar[FinansHareketTuru::Odeme->value]->bugun ?? '0')),
            'odeme_ay' => $this->normalizeDecimal((string) ($toplamlar[FinansHareketTuru::Odeme->value]->ay ?? '0')),
        ];
    }

    /**
     * @return array{sayisi: int, toplam: string, dagilim: array<int, array{para_birimi: string, tutar: string}>, vadesi_gecmis_toplam: string, vadesi_gecmis_dagilim: array<int, array{para_birimi: string, tutar: string}>, vadesi_gecmis_sayisi: int}
     */
    private function acikFaturaOzeti(int $firmaId): array
    {
        $bazParaBirimi = strtoupper((string) config('muhasebe.coklu_para_birimi.baz_para_birimi', 'TRY'));
        $bazTutar = "COALESCE(baz_acik_tutar, CASE WHEN UPPER(COALESCE(para_birimi, 'TRY')) = ? THEN acik_tutar ELSE 0 END)";
        $satirlar = Fatura::query()
            ->where('firma_id', $firmaId)
            ->where('durum', FaturaDurumu::Onayli)
            ->whereRaw('CAST(acik_tutar AS DECIMAL(18,4)) > 0')
            ->selectRaw('para_birimi, COUNT(*) AS sayisi, COALESCE(SUM(acik_tutar), 0) AS toplam, COALESCE(SUM('.$bazTutar.'), 0) AS baz_toplam', [$bazParaBirimi])
            ->selectRaw('COALESCE(SUM(CASE WHEN vade_tarihi IS NOT NULL AND DATE(vade_tarihi) < ? THEN acik_tutar ELSE 0 END), 0) AS vadesi_gecmis_toplam', [Carbon::today()->toDateString()])
            ->selectRaw('COALESCE(SUM(CASE WHEN vade_tarihi IS NOT NULL AND DATE(vade_tarihi) < ? THEN '.$bazTutar.' ELSE 0 END), 0) AS vadesi_gecmis_baz_toplam', [Carbon::today()->toDateString(), $bazParaBirimi])
            ->selectRaw('SUM(CASE WHEN vade_tarihi IS NOT NULL AND DATE(vade_tarihi) < ? THEN 1 ELSE 0 END) AS vadesi_gecmis_sayisi', [Carbon::today()->toDateString()])
            ->groupBy('para_birimi')
            ->get();

        $dagilim = [];
        $vadesiGecmisDagilim = [];
        $sayisi = 0;
        $toplam = '0';
        $vadesiGecmisToplam = '0';
        $vadesiGecmisSayisi = 0;
        foreach ($satirlar as $satir) {
            $paraBirimi = strtoupper((string) ($satir->para_birimi ?: 'TRY'));
            $tutar = $this->normalizeDecimal((string) ($satir->toplam ?? '0'));
            $bazTutarSatiri = $this->normalizeDecimal((string) ($satir->baz_toplam ?? '0'));
            $vadesiGecmisTutar = $this->normalizeDecimal((string) ($satir->vadesi_gecmis_toplam ?? '0'));
            $vadesiGecmisBazTutar = $this->normalizeDecimal((string) ($satir->vadesi_gecmis_baz_toplam ?? '0'));
            $dagilim[] = ['para_birimi' => $paraBirimi, 'tutar' => $tutar];
            $vadesiGecmisDagilim[] = ['para_birimi' => $paraBirimi, 'tutar' => $vadesiGecmisTutar];
            $sayisi += (int) ($satir->sayisi ?? 0);
            $toplam = bcadd($toplam, $bazTutarSatiri, 2);
            $vadesiGecmisToplam = bcadd($vadesiGecmisToplam, $vadesiGecmisBazTutar, 2);
            $vadesiGecmisSayisi += (int) ($satir->vadesi_gecmis_sayisi ?? 0);
        }

        return [
            'sayisi' => $sayisi,
            'toplam' => $toplam,
            'dagilim' => $dagilim,
            'vadesi_gecmis_toplam' => $vadesiGecmisToplam,
            'vadesi_gecmis_dagilim' => $vadesiGecmisDagilim,
            'vadesi_gecmis_sayisi' => $vadesiGecmisSayisi,
        ];
    }

    /**
     * @return array{kritik: int, negatif: int, degeri: string}
     */
    private function stokOzeti(int $firmaId): array
    {
        $satir = StokKarti::query()
            ->where('firma_id', $firmaId)
            ->where('durum', HesapDurumu::Aktif)
            ->selectRaw('SUM(CASE WHEN stok_takip = 1 AND minimum_stok IS NOT NULL AND stok_miktari <= minimum_stok THEN 1 ELSE 0 END) AS kritik')
            ->selectRaw('SUM(CASE WHEN negative_flag = 1 THEN 1 ELSE 0 END) AS negatif')
            ->selectRaw('COALESCE(SUM(CASE WHEN stok_takip = 1 THEN stok_degeri ELSE 0 END), 0) AS degeri')
            ->first();

        return [
            'kritik' => (int) ($satir->kritik ?? 0),
            'negatif' => (int) ($satir->negatif ?? 0),
            'degeri' => $this->normalizeDecimal((string) ($satir->degeri ?? '0')),
        ];
    }

    private function normalizeDecimal(string $v): string
    {
        return number_format((float) $v, 2, '.', '');
    }
}
