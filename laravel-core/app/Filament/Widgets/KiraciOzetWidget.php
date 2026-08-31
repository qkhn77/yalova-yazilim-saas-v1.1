<?php

namespace App\Filament\Widgets;

use App\Filament\Clusters\Muhasebe\Pages\FinansDashboardSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\BarkodluSatisGecmisiSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\BarkodluSatisIadeGecmisiSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\BarkodluSatisSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\MuhasebeDashboardSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\VadeTakipSayfasi;
use App\Filament\Clusters\Muhasebe\Resources\CariKartiKaynagi;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi;
use App\Filament\Clusters\Muhasebe\Resources\StokKartiKaynagi;
use App\Filament\Clusters\TeklifYonetimi\Resources\TeklifKaynagi;
use App\Filament\Clusters\TeknikServis\Pages\TeknikServisDashboardSayfasi;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi;
use App\Filament\Clusters\ETicaret\Pages\MusteriMesajlariSayfasi;
use App\Filament\Clusters\ETicaret\Pages\UrunMesajlariSayfasi;
use App\Filament\Clusters\Web\Resources\UrunKaynagi;
use App\Filament\Clusters\Web\Pages\BlogListesi;
use App\Filament\Clusters\Web\Pages\WebProje;
use App\Filament\Clusters\Web\Pages\WebServisListesi;
use App\Filament\Resources\SiparisKaynagi;
use App\Models\FirmaAboneligi;
use App\Models\FirmaKullanici;
use App\Models\Modul;
use App\Models\Ecommerce\Siparis;
use App\Services\ModulErisimService;
use App\Services\TenantContextService;
use App\Support\EcommerceMesajTanimlari;
use App\Support\SaaSemaYardimcisi;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class KiraciOzetWidget extends Widget
{
    protected static string $view = 'filament.widgets.kiraci-ozet-widget';

    protected int|string|array $columnSpan = 'full';

    protected ?string $placeholderHeight = '12rem';

    protected static ?int $sort = -10;

    public static function canView(): bool
    {
        $kullanici = Auth::user();
        if (! $kullanici) {
            return false;
        }

        if (! SaaSemaYardimcisi::firmalarTablosuVarMi()) {
            return false;
        }

        return app(TenantContextService::class)->aktifFirmaId() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $fid = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
        if ($fid <= 0) {
            return [
                'firma' => null,
                'aktifModuller' => [],
                'saltOkunurModuller' => [],
                'kullaniciSayisi' => 0,
                'abonelik' => null,
                'mesajKpiKarti' => null,
                'gunlukOzetKartlari' => [],
                'oncelikKartlari' => [],
                'servisAkisKartlari' => [],
                'aksiyonUyarilari' => [],
                'modulKpiKartlari' => [],
                'hizliIslemGruplari' => [],
                'altListeler' => [],
            ];
        }

        try {
            return Cache::remember(
                'kiraci-dashboard-ozet-v15:'.$fid,
                now()->addSeconds(60),
                fn (): array => $this->kiraciOzetVerisiniOlustur($fid),
            );
        } catch (\Throwable) {
            return [
                'firma' => null,
                'aktifModuller' => [],
                'saltOkunurModuller' => [],
                'kullaniciSayisi' => 0,
                'abonelik' => null,
                'mesajKpiKarti' => null,
                'gunlukOzetKartlari' => [],
                'oncelikKartlari' => [],
                'servisAkisKartlari' => [],
                'aksiyonUyarilari' => [],
                'modulKpiKartlari' => [],
                'hizliIslemGruplari' => [],
                'altListeler' => [],
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function kiraciOzetVerisiniOlustur(int $fid): array
    {
        $firma = app(TenantContextService::class)->aktifFirma();
        $servis = app(ModulErisimService::class);

        $aktifModuller = [];
        $saltOkunurModuller = [];
        if (SaaSemaYardimcisi::modullerTablosuVarMi()) {
            $modulSorgusu = Modul::query()->where('aktif_mi', true)->orderBy('siralama');
            foreach ($modulSorgusu->get() as $modul) {
                $kod = (string) $modul->kod;

                if (! $servis->modulErisilebilirMi($fid, $kod)) {
                    continue;
                }

                if ($servis->modulSaltOkunurMu($fid, $kod)) {
                    $saltOkunurModuller[] = $modul->ad;
                } else {
                    $aktifModuller[] = $modul->ad;
                }
            }
        }

        $kullaniciSayisi = 0;
        if (SaaSemaYardimcisi::firmaKullanicilariTablosuVarMi()) {
            $kullaniciSayisi = FirmaKullanici::query()
                ->withoutGlobalScopes()
                ->where('firma_id', $fid)
                ->whereNull('deleted_at')
                ->count();
        }

        $abonelik = null;
        if (SaaSemaYardimcisi::firmaAbonelikleriTablosuVarMi()) {
            $bugun = Carbon::today()->toDateString();
            $abonelik = FirmaAboneligi::query()
                ->withoutGlobalScopes()
                ->where('firma_id', $fid)
                ->where('durum', 'aktif')
                ->whereDate('baslangic_tarihi', '<=', $bugun)
                ->where(function ($sorgu) use ($bugun): void {
                    $sorgu->whereNull('bitis_tarihi')
                        ->orWhereDate('bitis_tarihi', '>=', $bugun);
                })
                ->when(SaaSemaYardimcisi::planlarTablosuVarMi(), fn ($q) => $q->with('plan'))
                ->first();
        }

        $metrikler = rescue(fn (): array => $this->dashboardMetrikleri($fid), [], report: false);

        return [
            'firma' => $firma,
            'aktifModuller' => $aktifModuller,
            'saltOkunurModuller' => $saltOkunurModuller,
            'kullaniciSayisi' => $kullaniciSayisi,
            'abonelik' => $abonelik,
            'guncellendiAt' => now()->format('H:i'),
            'mesajKpiKarti' => rescue(fn (): array => $this->mesajKpiKarti($fid, $metrikler), [], report: false),
            'gunlukOzetKartlari' => rescue(fn (): array => $this->gunlukOzetKartlari($fid, $metrikler), [], report: false),
            'oncelikKartlari' => rescue(fn (): array => $this->oncelikKartlari($fid, $metrikler), [], report: false),
            'servisAkisKartlari' => rescue(fn (): array => $this->servisAkisKartlari($metrikler), [], report: false),
            'aksiyonUyarilari' => rescue(fn (): array => $this->aksiyonUyarilari($fid, $metrikler), [], report: false),
            'modulKpiKartlari' => rescue(fn (): array => $this->modulKpiKartlari($fid, $metrikler), [], report: false),
            'hizliIslemGruplari' => rescue(fn (): array => $this->hizliIslemGruplari(), [], report: false),
            'altListeler' => rescue(fn (): array => $this->altListeler($fid), [], report: false),
        ];
    }

    /**
     * @return array<string, int|float|string>
     */
    private function dashboardMetrikleri(int $firmaId): array
    {
        $siparisMetrikleri = $this->siparisDashboardMetrikleri($firmaId);
        $teklifMetrikleri = $this->teklifDashboardMetrikleri($firmaId);
        $mesajMetrikleri = $this->mesajDashboardMetrikleri($firmaId);
        $barkodMetrikleri = $this->barkodDashboardMetrikleri($firmaId);
        $alacakMetrikleri = $this->alacakDashboardMetrikleri($firmaId);

        return [
            'acik_servis' => $this->acikServisSayisi($firmaId),
            'bugun_gelen_servis' => $this->bugunGelenServisSayisi($firmaId),
            'teslim_bekleyen_servis' => $this->teslimBekleyenServisSayisi($firmaId),
            'geciken_servis' => $this->gecikenServisSayisi($firmaId),
            'yeni_siparis' => $siparisMetrikleri['yeni_siparis'],
            'bekleyen_siparis' => $siparisMetrikleri['bekleyen_siparis'],
            'bugunku_siparis_tutari' => $siparisMetrikleri['bugunku_siparis_tutari'],
            'bugunku_siparis_tutari_etiket' => $this->bugunkuSiparisParaBirimiEtiketi($firmaId),
            'musteri_mesaji' => $mesajMetrikleri['musteri_mesaji'],
            'urun_mesaji' => $mesajMetrikleri['urun_mesaji'],
            'bugunku_barkodlu_satis' => $barkodMetrikleri['bugunku_barkodlu_satis'],
            'bugunku_barkodlu_satis_tutari' => $barkodMetrikleri['bugunku_barkodlu_satis_tutari'],
            'bugunku_barkodlu_satis_tutari_etiket' => $this->bugunkuBarkodluSatisParaBirimiEtiketi($firmaId),
            'bugunku_barkodlu_satis_iade_tutari' => $this->bugunkuBarkodluSatisIadeTutari($firmaId),
            'bugunku_barkodlu_satis_iade_tutari_etiket' => $this->bugunkuBarkodluSatisIadeParaBirimiEtiketi($firmaId),
            'bekleyen_tahsilat_tutari' => $alacakMetrikleri['bekleyen_tahsilat_tutari'],
            'geciken_taksit' => $alacakMetrikleri['geciken_taksit'],
            'kritik_stok' => $this->kritikStokSayisi($firmaId),
            'aktif_urun' => $this->aktifUrunSayisi($firmaId),
            'fatura' => $this->firmaTabloSayisi('faturalar', $firmaId),
            'cari' => $this->firmaTabloSayisi('cariler', $firmaId),
            'web_servis' => $this->webTabloSayisi('services', 'is_active'),
            'web_proje' => $this->webTabloSayisi('projects', 'is_active'),
            'web_blog' => $this->webTabloSayisi('posts', 'is_published'),
            'teklif' => $teklifMetrikleri['teklif'],
            'onaylanan_teklif' => $teklifMetrikleri['onaylanan_teklif'],
            'bekleyen_teklif' => $teklifMetrikleri['bekleyen_teklif'],
            'teklif_tutari' => $teklifMetrikleri['teklif_tutari'],
        ];
    }

    /**
     * Aynı dashboard isteğinde sipariş tablosuna üç kez gitmek yerine
     * güncel sipariş metriklerini tek toplu sorguda hesaplar.
     *
     * @return array{yeni_siparis:int,bekleyen_siparis:int,bugunku_siparis_tutari:float}
     */
    private function siparisDashboardMetrikleri(int $firmaId): array
    {
        $bos = [
            'yeni_siparis' => 0,
            'bekleyen_siparis' => 0,
            'bugunku_siparis_tutari' => 0.0,
        ];

        if (! SaaSemaYardimcisi::tabloVarMi('siparisler')) {
            return $bos;
        }

        [$baslangic, $bitis] = $this->bugunAraligi();
        $bekleyenDurumlar = [
            Siparis::DURUM_ONAY_BEKLIYOR,
            Siparis::DURUM_EFT_ONAYI_BEKLIYOR,
            Siparis::DURUM_ODEME_BEKLENIYOR,
            Siparis::DURUM_DETAY_BEKLEYEN,
            Siparis::DURUM_BEKLEMEDE,
        ];
        $iptalDurumlari = [
            Siparis::DURUM_IPTAL_EDILDI,
            Siparis::DURUM_IPTAL,
            Siparis::DURUM_BASARISIZ_ODEME,
        ];

        $bekleyenYerleri = implode(',', array_fill(0, count($bekleyenDurumlar), '?'));
        $iptalYerleri = implode(',', array_fill(0, count($iptalDurumlari), '?'));

        $satir = DB::table('siparisler')
            ->where('firma_id', $firmaId)
            ->selectRaw(
                "SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as yeni_siparis,
                SUM(CASE WHEN durum IN ({$bekleyenYerleri}) THEN 1 ELSE 0 END) as bekleyen_siparis,
                COALESCE(SUM(CASE WHEN created_at BETWEEN ? AND ? AND durum NOT IN ({$iptalYerleri}) THEN genel_toplam ELSE 0 END), 0) as bugunku_siparis_tutari",
                array_merge([$baslangic, $bitis], $bekleyenDurumlar, [$baslangic, $bitis], $iptalDurumlari),
            )
            ->first();

        return [
            'yeni_siparis' => (int) ($satir?->yeni_siparis ?? 0),
            'bekleyen_siparis' => (int) ($satir?->bekleyen_siparis ?? 0),
            'bugunku_siparis_tutari' => (float) ($satir?->bugunku_siparis_tutari ?? 0),
        ];
    }

    /**
     * @return array{teklif:int,onaylanan_teklif:int,bekleyen_teklif:int,teklif_tutari:string}
     */
    private function teklifDashboardMetrikleri(int $firmaId): array
    {
        $bos = [
            'teklif' => 0,
            'onaylanan_teklif' => 0,
            'bekleyen_teklif' => 0,
            'teklif_tutari' => '0,00 TRY',
        ];

        if (! SaaSemaYardimcisi::tabloVarMi('teklifler')) {
            return $bos;
        }

        $bekleyenDurumlar = ['taslak', 'hazirlaniyor', 'gonderildi', 'revizyon_bekliyor'];
        $bekleyenYerleri = implode(',', array_fill(0, count($bekleyenDurumlar), '?'));
        $satir = DB::table('teklifler')
            ->where('firma_id', $firmaId)
            ->when($this->kolonVarMi('teklifler', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw(
                "COUNT(*) as teklif,
                SUM(CASE WHEN durum = 'onaylandi' THEN 1 ELSE 0 END) as onaylanan_teklif,
                SUM(CASE WHEN durum IN ({$bekleyenYerleri}) THEN 1 ELSE 0 END) as bekleyen_teklif",
                $bekleyenDurumlar,
            )
            ->first();

        return [
            'teklif' => (int) ($satir?->teklif ?? 0),
            'onaylanan_teklif' => (int) ($satir?->onaylanan_teklif ?? 0),
            'bekleyen_teklif' => (int) ($satir?->bekleyen_teklif ?? 0),
            'teklif_tutari' => $this->teklifTutari($firmaId),
        ];
    }

    /**
     * @return array{musteri_mesaji:int,urun_mesaji:int}
     */
    private function mesajDashboardMetrikleri(int $firmaId): array
    {
        $bos = ['musteri_mesaji' => 0, 'urun_mesaji' => 0];

        if (! SaaSemaYardimcisi::tabloVarMi('ecommerce_mesaj_konulari')) {
            return $bos;
        }

        $satirlar = DB::table('ecommerce_mesaj_konulari')
            ->where('firma_id', $firmaId)
            ->whereIn('konu_tipi', [
                EcommerceMesajTanimlari::KONU_TIPI_MUSTERI,
                EcommerceMesajTanimlari::KONU_TIPI_URUN,
            ])
            ->where(function ($q): void {
                $q->where('okunmamis_mi', true)
                    ->orWhere('okunmamis_mesaj_sayisi', '>', 0)
                    ->orWhereIn('durum', [
                        EcommerceMesajTanimlari::DURUM_YENI,
                        EcommerceMesajTanimlari::DURUM_OKUNMAMIS,
                        EcommerceMesajTanimlari::DURUM_MUSTERI_YANITI_GELDI,
                    ]);
            })
            ->select('konu_tipi')
            ->selectRaw('COUNT(*) as adet')
            ->groupBy('konu_tipi')
            ->get();

        foreach ($satirlar as $satir) {
            if ((string) $satir->konu_tipi === EcommerceMesajTanimlari::KONU_TIPI_MUSTERI) {
                $bos['musteri_mesaji'] = (int) $satir->adet;
            } elseif ((string) $satir->konu_tipi === EcommerceMesajTanimlari::KONU_TIPI_URUN) {
                $bos['urun_mesaji'] = (int) $satir->adet;
            }
        }

        return $bos;
    }

    /**
     * @return array{bugunku_barkodlu_satis:int,bugunku_barkodlu_satis_tutari:float}
     */
    private function barkodDashboardMetrikleri(int $firmaId): array
    {
        $bos = [
            'bugunku_barkodlu_satis' => 0,
            'bugunku_barkodlu_satis_tutari' => 0.0,
        ];

        if (! SaaSemaYardimcisi::tabloVarMi('muhasebe_barkodlu_satislar')) {
            return $bos;
        }

        [$baslangic, $bitis] = $this->bugunAraligi();
        $satir = DB::table('muhasebe_barkodlu_satislar')
            ->where('firma_id', $firmaId)
            ->whereBetween('satis_tarihi', [$baslangic, $bitis])
            ->selectRaw(
                'SUM(CASE WHEN (durum IS NULL OR durum != ?) THEN 1 ELSE 0 END) as adet,
                COALESCE(SUM(CASE WHEN (durum IS NULL OR durum != ?) THEN genel_toplam ELSE 0 END), 0) as tutar',
                ['iptal', 'iptal'],
            )
            ->first();

        return [
            'bugunku_barkodlu_satis' => (int) ($satir?->adet ?? 0),
            'bugunku_barkodlu_satis_tutari' => (float) ($satir?->tutar ?? 0),
        ];
    }

    /**
     * @return array{bekleyen_tahsilat_tutari:string,geciken_taksit:int}
     */
    private function alacakDashboardMetrikleri(int $firmaId): array
    {
        $bos = [
            'bekleyen_tahsilat_tutari' => '0,00 TRY',
            'geciken_taksit' => 0,
        ];

        if (! SaaSemaYardimcisi::tabloVarMi('muhasebe_alacak_plan_taksitleri')) {
            return $bos;
        }

        $satirlar = DB::table('muhasebe_alacak_plan_taksitleri')
            ->where('firma_id', $firmaId)
            ->when($this->kolonVarMi('muhasebe_alacak_plan_taksitleri', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->whereDate('vade_tarihi', '<=', Carbon::today())
            ->whereNotIn('durum', ['odendi', 'iptal'])
            ->selectRaw("UPPER(COALESCE(para_birimi, 'TRY')) as para_birimi, COALESCE(SUM(kalan_tutar), 0) as toplam, COUNT(*) as adet")
            ->groupByRaw("UPPER(COALESCE(para_birimi, 'TRY'))")
            ->orderBy('para_birimi')
            ->get();

        return [
            'bekleyen_tahsilat_tutari' => $this->paraBirimiToplamEtiketi($satirlar),
            'geciken_taksit' => (int) $satirlar->sum(fn (object $satir): int => (int) $satir->adet),
        ];
    }

    /**
     * @param  array<string, int|float|string>  $metrikler
     */
    private function metrik(array $metrikler, string $anahtar): int|float|string
    {
        return $metrikler[$anahtar] ?? 0;
    }

    /**
     * @return array<int, array{baslik:string,deger:string,aciklama:string,url:string}>
     */
    private function gunlukOzetKartlari(int $firmaId, array $metrikler): array
    {
        $siparisTutari = (string) $this->metrik($metrikler, 'bugunku_siparis_tutari_etiket');
        $posTutari = (string) $this->metrik($metrikler, 'bugunku_barkodlu_satis_tutari_etiket');
        $iadeTutari = (string) $this->metrik($metrikler, 'bugunku_barkodlu_satis_iade_tutari_etiket');

        return [
            [
                'baslik' => 'Servis girişi',
                'deger' => (string) $this->metrik($metrikler, 'bugun_gelen_servis'),
                'aciklama' => 'Bugün açılan kayıt',
                'url' => TeknikServisKaydiKaynagi::getUrl('yeni'),
            ],
            [
                'baslik' => 'Yeni sipariş',
                'deger' => (string) $this->metrik($metrikler, 'yeni_siparis'),
                'aciklama' => 'E-ticaret siparişi',
                'url' => SiparisKaynagi::getUrl('index'),
            ],
            [
                'baslik' => 'POS fişi',
                'deger' => (string) $this->metrik($metrikler, 'bugunku_barkodlu_satis'),
                'aciklama' => 'Barkodlu satış',
                'url' => BarkodluSatisGecmisiSayfasi::getUrl(),
            ],
            [
                'baslik' => 'Günlük ciro',
                'deger' => $siparisTutari.' · '.$posTutari,
                'aciklama' => 'Sipariş + POS · para birimi bazında',
                'url' => FinansDashboardSayfasi::getUrl(),
            ],
            [
                'baslik' => 'Günlük iade',
                'deger' => $iadeTutari,
                'aciklama' => 'Barkodlu satış iadesi',
                'url' => BarkodluSatisIadeGecmisiSayfasi::getUrl(),
            ],
        ];
    }

    /**
     * @return array<int, array{baslik:string,deger:string,aciklama:string,url:string,renk:string}>
     */
    private function oncelikKartlari(int $firmaId, array $metrikler): array
    {
        $gecikenServis = (int) $this->metrik($metrikler, 'geciken_servis');
        $teslimBekleyen = (int) $this->metrik($metrikler, 'teslim_bekleyen_servis');
        $bekleyenTahsilat = (string) $this->metrik($metrikler, 'bekleyen_tahsilat_tutari');
        $kritikStok = (int) $this->metrik($metrikler, 'kritik_stok');
        $bekleyenMesaj = (int) $this->metrik($metrikler, 'musteri_mesaji') + (int) $this->metrik($metrikler, 'urun_mesaji');

        return [
            [
                'baslik' => 'Servis takibi',
                'deger' => (string) $gecikenServis,
                'aciklama' => 'Geciken açık servis',
                'url' => TeknikServisKaydiKaynagi::getUrl('acik'),
                'renk' => $gecikenServis > 0 ? 'warning' : 'gray',
            ],
            [
                'baslik' => 'Teslim',
                'deger' => (string) $teslimBekleyen,
                'aciklama' => 'Teslim bekleyen iş',
                'url' => TeknikServisKaydiKaynagi::getUrl('teslim_bekleyen'),
                'renk' => $teslimBekleyen > 0 ? 'success' : 'gray',
            ],
            [
                'baslik' => 'Tahsilat',
                'deger' => $bekleyenTahsilat,
                'aciklama' => 'Vadesi gelen açık tutar',
                'url' => VadeTakipSayfasi::getUrl(),
                'renk' => $bekleyenTahsilat !== '0,00 TRY' ? 'danger' : 'gray',
            ],
            [
                'baslik' => 'Stok',
                'deger' => (string) $kritikStok,
                'aciklama' => 'Kritik seviyedeki stok',
                'url' => StokKartiKaynagi::getUrl('index'),
                'renk' => $kritikStok > 0 ? 'warning' : 'gray',
            ],
            [
                'baslik' => 'Mesaj',
                'deger' => (string) $bekleyenMesaj,
                'aciklama' => 'Yanıt bekleyen konu',
                'url' => MusteriMesajlariSayfasi::getUrl(),
                'renk' => $bekleyenMesaj > 0 ? 'info' : 'gray',
            ],
        ];
    }

    /**
     * @param  array<string, int|float>  $metrikler
     * @return array<int, array{baslik:string,deger:string,aciklama:string,url:string,renk:string}>
     */
    private function servisAkisKartlari(array $metrikler): array
    {
        $acikServis = (int) $this->metrik($metrikler, 'acik_servis');
        $bugunGelen = (int) $this->metrik($metrikler, 'bugun_gelen_servis');
        $gecikenServis = (int) $this->metrik($metrikler, 'geciken_servis');
        $teslimBekleyen = (int) $this->metrik($metrikler, 'teslim_bekleyen_servis');

        return [
            [
                'baslik' => 'Açık',
                'deger' => (string) $acikServis,
                'aciklama' => 'Aktif servis havuzu',
                'url' => TeknikServisKaydiKaynagi::getUrl('acik'),
                'renk' => $acikServis > 0 ? 'info' : 'gray',
            ],
            [
                'baslik' => 'Bugün',
                'deger' => (string) $bugunGelen,
                'aciklama' => 'Yeni kabul',
                'url' => TeknikServisKaydiKaynagi::getUrl('yeni'),
                'renk' => $bugunGelen > 0 ? 'success' : 'gray',
            ],
            [
                'baslik' => 'Geciken',
                'deger' => (string) $gecikenServis,
                'aciklama' => 'Öncelikli takip',
                'url' => TeknikServisKaydiKaynagi::getUrl('acik'),
                'renk' => $gecikenServis > 0 ? 'warning' : 'gray',
            ],
            [
                'baslik' => 'Teslim',
                'deger' => (string) $teslimBekleyen,
                'aciklama' => 'Hazır bekleyen',
                'url' => TeknikServisKaydiKaynagi::getUrl('teslim_bekleyen'),
                'renk' => $teslimBekleyen > 0 ? 'success' : 'gray',
            ],
        ];
    }

    /**
     * @return array<int, array{baslik:string,aciklama:string,deger:string,url:string,seviye:string}>
     */
    private function aksiyonUyarilari(int $firmaId, array $metrikler): array
    {
        $uyarilar = [];

        $gecikenServis = (int) $this->metrik($metrikler, 'geciken_servis');
        if ($gecikenServis > 0) {
            $uyarilar[] = [
                'baslik' => 'Geciken servisleri kontrol et',
                'aciklama' => 'Kabul tarihi bugünden önce olan açık işler.',
                'deger' => (string) $gecikenServis,
                'url' => TeknikServisKaydiKaynagi::getUrl('acik'),
                'seviye' => 'warning',
            ];
        }

        $teslimBekleyen = (int) $this->metrik($metrikler, 'teslim_bekleyen_servis');
        if ($teslimBekleyen > 0) {
            $uyarilar[] = [
                'baslik' => 'Teslim bekleyen işleri kapat',
                'aciklama' => 'Müşteriye teslim için hazır kayıtlar.',
                'deger' => (string) $teslimBekleyen,
                'url' => TeknikServisKaydiKaynagi::getUrl('teslim_bekleyen'),
                'seviye' => 'success',
            ];
        }

        $bekleyenTahsilat = (string) $this->metrik($metrikler, 'bekleyen_tahsilat_tutari');
        if ($bekleyenTahsilat !== '0,00 TRY') {
            $uyarilar[] = [
                'baslik' => 'Vadesi gelen tahsilatı izle',
                'aciklama' => 'Açık kalan alacak planı tutarı.',
                'deger' => $bekleyenTahsilat,
                'url' => VadeTakipSayfasi::getUrl(),
                'seviye' => 'danger',
            ];
        }

        $kritikStok = (int) $this->metrik($metrikler, 'kritik_stok');
        if ($kritikStok > 0) {
            $uyarilar[] = [
                'baslik' => 'Kritik stokları yenile',
                'aciklama' => 'Minimum seviyeye inen stok kartları.',
                'deger' => (string) $kritikStok,
                'url' => StokKartiKaynagi::getUrl('index'),
                'seviye' => 'warning',
            ];
        }

        $bekleyenMesaj = (int) $this->metrik($metrikler, 'musteri_mesaji') + (int) $this->metrik($metrikler, 'urun_mesaji');
        if ($bekleyenMesaj > 0) {
            $uyarilar[] = [
                'baslik' => 'Müşteri mesajlarını yanıtla',
                'aciklama' => 'Okunmamış veya yanıt bekleyen konu.',
                'deger' => (string) $bekleyenMesaj,
                'url' => MusteriMesajlariSayfasi::getUrl(),
                'seviye' => 'info',
            ];
        }

        return array_slice($uyarilar, 0, 5);
    }

    /**
     * @return array<int, array{baslik:string,url:string,bilgiler:array<int, array{etiket:string,deger:string,url:string}>}>
     */
    private function modulKpiKartlari(int $firmaId, array $metrikler): array
    {
        return [
            [
                'baslik' => 'Teknik Servis',
                'url' => TeknikServisDashboardSayfasi::getUrl(),
                'bilgiler' => [
                    ['etiket' => 'Açık', 'deger' => (string) $this->metrik($metrikler, 'acik_servis'), 'url' => TeknikServisKaydiKaynagi::getUrl('acik')],
                    ['etiket' => 'Bugün', 'deger' => (string) $this->metrik($metrikler, 'bugun_gelen_servis'), 'url' => TeknikServisKaydiKaynagi::getUrl('yeni')],
                    ['etiket' => 'Teslim', 'deger' => (string) $this->metrik($metrikler, 'teslim_bekleyen_servis'), 'url' => TeknikServisKaydiKaynagi::getUrl('teslim_bekleyen')],
                    ['etiket' => 'Gecik.', 'deger' => (string) $this->metrik($metrikler, 'geciken_servis'), 'url' => TeknikServisKaydiKaynagi::getUrl('acik')],
                ],
            ],
            [
                'baslik' => 'E-Ticaret',
                'url' => SiparisKaynagi::getUrl('index'),
                'bilgiler' => [
                    ['etiket' => 'Yeni', 'deger' => (string) $this->metrik($metrikler, 'yeni_siparis'), 'url' => SiparisKaynagi::getUrl('index')],
                    ['etiket' => 'Bekl.', 'deger' => (string) $this->metrik($metrikler, 'bekleyen_siparis'), 'url' => SiparisKaynagi::getUrl('index')],
                    ['etiket' => 'Ciro', 'deger' => (string) $this->metrik($metrikler, 'bugunku_siparis_tutari_etiket'), 'url' => SiparisKaynagi::getUrl('index')],
                    ['etiket' => 'Mesaj', 'deger' => (string) ((int) $this->metrik($metrikler, 'musteri_mesaji') + (int) $this->metrik($metrikler, 'urun_mesaji')), 'url' => MusteriMesajlariSayfasi::getUrl()],
                ],
            ],
            [
                'baslik' => 'Barkodlu Satış',
                'url' => BarkodluSatisGecmisiSayfasi::getUrl(),
                'bilgiler' => [
                    ['etiket' => 'Fiş', 'deger' => (string) $this->metrik($metrikler, 'bugunku_barkodlu_satis'), 'url' => BarkodluSatisGecmisiSayfasi::getUrl()],
                    ['etiket' => 'Ciro', 'deger' => (string) $this->metrik($metrikler, 'bugunku_barkodlu_satis_tutari_etiket'), 'url' => BarkodluSatisGecmisiSayfasi::getUrl()],
                    ['etiket' => 'İade', 'deger' => (string) $this->metrik($metrikler, 'bugunku_barkodlu_satis_iade_tutari_etiket'), 'url' => BarkodluSatisIadeGecmisiSayfasi::getUrl()],
                    ['etiket' => 'Kritik', 'deger' => (string) $this->metrik($metrikler, 'kritik_stok'), 'url' => StokKartiKaynagi::getUrl('index')],
                ],
            ],
            [
                'baslik' => 'Muhasebe',
                'url' => MuhasebeDashboardSayfasi::getUrl(),
                'bilgiler' => [
                    ['etiket' => 'Tahsil', 'deger' => (string) $this->metrik($metrikler, 'bekleyen_tahsilat_tutari'), 'url' => VadeTakipSayfasi::getUrl()],
                    ['etiket' => 'Taksit', 'deger' => (string) $this->metrik($metrikler, 'geciken_taksit'), 'url' => VadeTakipSayfasi::getUrl()],
                    ['etiket' => 'Fatura', 'deger' => (string) $this->metrik($metrikler, 'fatura'), 'url' => FaturaKaynagi::getUrl('index')],
                    ['etiket' => 'Cari', 'deger' => (string) $this->metrik($metrikler, 'cari'), 'url' => CariKartiKaynagi::getUrl('index')],
                ],
            ],
            [
                'baslik' => 'Web',
                'url' => WebServisListesi::getUrl(),
                'bilgiler' => [
                    ['etiket' => 'Ürün', 'deger' => (string) $this->metrik($metrikler, 'aktif_urun'), 'url' => UrunKaynagi::getUrl('index')],
                    ['etiket' => 'Servis', 'deger' => (string) $this->metrik($metrikler, 'web_servis'), 'url' => WebServisListesi::getUrl()],
                    ['etiket' => 'Proje', 'deger' => (string) $this->metrik($metrikler, 'web_proje'), 'url' => WebProje::getUrl()],
                    ['etiket' => 'Blog', 'deger' => (string) $this->metrik($metrikler, 'web_blog'), 'url' => BlogListesi::getUrl()],
                ],
            ],
            [
                'baslik' => 'Teklif',
                'url' => TeklifKaynagi::getUrl('index'),
                'bilgiler' => [
                    ['etiket' => 'Toplam', 'deger' => (string) $this->metrik($metrikler, 'teklif'), 'url' => TeklifKaynagi::getUrl('index')],
                    ['etiket' => 'Onay', 'deger' => (string) $this->metrik($metrikler, 'onaylanan_teklif'), 'url' => TeklifKaynagi::getUrl('index')],
                    ['etiket' => 'Bekl.', 'deger' => (string) $this->metrik($metrikler, 'bekleyen_teklif'), 'url' => TeklifKaynagi::getUrl('index')],
                    ['etiket' => 'Tutar', 'deger' => (string) $this->metrik($metrikler, 'teklif_tutari'), 'url' => TeklifKaynagi::getUrl('index')],
                ],
            ],
        ];
    }

    /**
     * @return array{baslik:string,aciklama:string,url:string,bilgiler:array<int, array{etiket:string,deger:string,url:string}>}
     */
    private function mesajKpiKarti(int $firmaId, array $metrikler): array
    {
        $musteriMesaji = (int) $this->metrik($metrikler, 'musteri_mesaji');
        $urunMesaji = (int) $this->metrik($metrikler, 'urun_mesaji');

        return [
            'baslik' => 'E-Ticaret Mesajları',
            'aciklama' => 'Okunmamış ve yanıt bekleyen mesaj konuları.',
            'url' => MusteriMesajlariSayfasi::getUrl(),
            'bilgiler' => [
                ['etiket' => 'Müşteri mesajı', 'deger' => (string) $musteriMesaji, 'url' => MusteriMesajlariSayfasi::getUrl()],
                ['etiket' => 'Ürün mesajı', 'deger' => (string) $urunMesaji, 'url' => UrunMesajlariSayfasi::getUrl()],
                ['etiket' => 'Toplam bekleyen', 'deger' => (string) ($musteriMesaji + $urunMesaji), 'url' => MusteriMesajlariSayfasi::getUrl()],
            ],
        ];
    }

    /**
     * @return array<int, array{baslik:string,renk:string,url:string,kartlar:array<int, array{etiket:string,deger:string,aciklama:string,url:?string}>}>
     */
    private function kpiGruplari(int $firmaId): array
    {
        return [
            [
                'baslik' => 'Teknik Servis',
                'renk' => 'amber',
                'url' => TeknikServisDashboardSayfasi::getUrl(),
                'kartlar' => [
                    ['etiket' => 'Açık servis', 'deger' => (string) $this->acikServisSayisi($firmaId), 'aciklama' => 'Teslim/iptal/iade hariç', 'url' => TeknikServisKaydiKaynagi::getUrl('acik')],
                    ['etiket' => 'Bugün gelen', 'deger' => (string) $this->bugunGelenServisSayisi($firmaId), 'aciklama' => 'Kabul tarihi bugün', 'url' => TeknikServisKaydiKaynagi::getUrl('yeni')],
                    ['etiket' => 'Teslim bekleyen', 'deger' => (string) $this->teslimBekleyenServisSayisi($firmaId), 'aciklama' => 'Teslime hazır işler', 'url' => TeknikServisKaydiKaynagi::getUrl('teslim_bekleyen')],
                ],
            ],
            [
                'baslik' => 'E-Ticaret',
                'renk' => 'sky',
                'url' => SiparisKaynagi::getUrl('index'),
                'kartlar' => [
                    ['etiket' => 'Yeni sipariş', 'deger' => (string) $this->yeniSiparisSayisi($firmaId), 'aciklama' => 'Bugün açılan kayıt', 'url' => SiparisKaynagi::getUrl('index')],
                    ['etiket' => 'Onay bekleyen', 'deger' => (string) $this->bekleyenSiparisSayisi($firmaId), 'aciklama' => 'Ödeme/onay akışında', 'url' => SiparisKaynagi::getUrl('index')],
                    ['etiket' => 'Bugünkü ciro', 'deger' => $this->bugunkuSiparisParaBirimiEtiketi($firmaId), 'aciklama' => 'Teslim/iptal hariç · para birimi bazında', 'url' => SiparisKaynagi::getUrl('index')],
                    ['etiket' => 'Müşteri mesajı', 'deger' => (string) $this->okunmamisMesajKonusuSayisi($firmaId, EcommerceMesajTanimlari::KONU_TIPI_MUSTERI), 'aciklama' => 'Okunmamış konu', 'url' => MusteriMesajlariSayfasi::getUrl()],
                    ['etiket' => 'Ürün mesajı', 'deger' => (string) $this->okunmamisMesajKonusuSayisi($firmaId, EcommerceMesajTanimlari::KONU_TIPI_URUN), 'aciklama' => 'Okunmamış konu', 'url' => UrunMesajlariSayfasi::getUrl()],
                ],
            ],
            [
                'baslik' => 'Barkodlu Satış',
                'renk' => 'rose',
                'url' => BarkodluSatisGecmisiSayfasi::getUrl(),
                'kartlar' => [
                    ['etiket' => 'Bugünkü satış', 'deger' => (string) $this->bugunkuBarkodluSatisSayisi($firmaId), 'aciklama' => 'İptal hariç fiş', 'url' => BarkodluSatisGecmisiSayfasi::getUrl()],
                    ['etiket' => 'Bugünkü ciro', 'deger' => $this->bugunkuBarkodluSatisParaBirimiEtiketi($firmaId), 'aciklama' => 'Net satış toplamı · para birimi bazında', 'url' => BarkodluSatisGecmisiSayfasi::getUrl()],
                    ['etiket' => 'Bugünkü iade', 'deger' => $this->bugunkuBarkodluSatisIadeParaBirimiEtiketi($firmaId), 'aciklama' => 'İade toplamı · para birimi bazında', 'url' => BarkodluSatisIadeGecmisiSayfasi::getUrl()],
                ],
            ],
            [
                'baslik' => 'Muhasebe',
                'renk' => 'emerald',
                'url' => MuhasebeDashboardSayfasi::getUrl(),
                'kartlar' => [
                    ['etiket' => 'Bekleyen tahsilat', 'deger' => $this->bekleyenTahsilatTutari($firmaId), 'aciklama' => 'Vadesi gelmiş açık tutar · para birimi bazında', 'url' => VadeTakipSayfasi::getUrl()],
                    ['etiket' => 'Geciken taksit', 'deger' => (string) $this->gecikenTaksitSayisi($firmaId), 'aciklama' => 'Bugün ve öncesi', 'url' => VadeTakipSayfasi::getUrl()],
                    ['etiket' => 'Kritik stok', 'deger' => (string) $this->kritikStokSayisi($firmaId), 'aciklama' => 'Minimum seviyede', 'url' => StokKartiKaynagi::getUrl('index')],
                ],
            ],
            [
                'baslik' => 'Web',
                'renk' => 'violet',
                'url' => WebServisListesi::getUrl(),
                'kartlar' => [
                    ['etiket' => 'Yayındaki ürün', 'deger' => (string) $this->aktifUrunSayisi($firmaId), 'aciklama' => 'Web ürün kataloğu', 'url' => UrunKaynagi::getUrl('index')],
                    ['etiket' => 'Servisler', 'deger' => (string) $this->webTabloSayisi('services', 'is_active'), 'aciklama' => 'Aktif servis', 'url' => WebServisListesi::getUrl()],
                    ['etiket' => 'Blog / proje', 'deger' => (string) ($this->webTabloSayisi('posts', 'is_published') + $this->webTabloSayisi('projects', 'is_active')), 'aciklama' => 'Yayındaki içerik', 'url' => BlogListesi::getUrl()],
                ],
            ],
            [
                'baslik' => 'Teklif',
                'renk' => 'indigo',
                'url' => TeklifKaynagi::getUrl('index'),
                'kartlar' => [
                    ['etiket' => 'Teklifler', 'deger' => (string) $this->teklifSayisi($firmaId), 'aciklama' => 'Son 30 gün', 'url' => TeklifKaynagi::getUrl('index')],
                    ['etiket' => 'Onaylanan', 'deger' => (string) $this->onaylananTeklifSayisi($firmaId), 'aciklama' => 'Son 30 gün', 'url' => TeklifKaynagi::getUrl('index')],
                    ['etiket' => 'Teklif toplamı', 'deger' => $this->teklifTutari($firmaId), 'aciklama' => 'Son 30 gün · para birimi bazında', 'url' => TeklifKaynagi::getUrl('index')],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array{baslik:string,aciklama:string,url:string,aksiyonlar:array<int, array{etiket:string,url:string,vurgu?:bool}>}>
     */
    private function hizliIslemGruplari(): array
    {
        return [
            [
                'baslik' => 'Teknik Servis',
                'aciklama' => 'Servis kabul, liste ve operasyon ekranları.',
                'url' => TeknikServisDashboardSayfasi::getUrl(),
                'aksiyonlar' => [
                    ['etiket' => 'Arızalı cihaz kaydı', 'url' => TeknikServisKaydiKaynagi::getUrl('create_arizali'), 'vurgu' => true],
                    ['etiket' => 'Bakım kaydı', 'url' => TeknikServisKaydiKaynagi::getUrl('create_bakim')],
                    ['etiket' => 'Açık servisler', 'url' => TeknikServisKaydiKaynagi::getUrl('acik')],
                    ['etiket' => 'Tüm kayıtlar', 'url' => TeknikServisKaydiKaynagi::getUrl('index')],
                ],
            ],
            [
                'baslik' => 'Muhasebe',
                'aciklama' => 'Cari, fatura, stok ve tahsilat akışları.',
                'url' => MuhasebeDashboardSayfasi::getUrl(),
                'aksiyonlar' => [
                    ['etiket' => 'Yeni fatura', 'url' => FaturaKaynagi::getUrl('createGiden'), 'vurgu' => true],
                    ['etiket' => 'Yeni cari', 'url' => CariKartiKaynagi::getUrl('create')],
                    ['etiket' => 'Yeni stok kartı', 'url' => StokKartiKaynagi::getUrl('create')],
                    ['etiket' => 'Finans paneli', 'url' => FinansDashboardSayfasi::getUrl()],
                ],
            ],
            [
                'baslik' => 'E-Ticaret',
                'aciklama' => 'Sipariş ve ürün yönetimi.',
                'url' => SiparisKaynagi::getUrl('index'),
                'aksiyonlar' => [
                    ['etiket' => 'Siparişler', 'url' => SiparisKaynagi::getUrl('index'), 'vurgu' => true],
                    ['etiket' => 'Müşteri mesajları', 'url' => MusteriMesajlariSayfasi::getUrl()],
                    ['etiket' => 'Ürün mesajları', 'url' => UrunMesajlariSayfasi::getUrl()],
                    ['etiket' => 'Başarısız siparişler', 'url' => SiparisKaynagi::getUrl('failed')],
                ],
            ],
            [
                'baslik' => 'Barkodlu Satış',
                'aciklama' => 'Hızlı satış, geçmiş ve iade ekranları.',
                'url' => BarkodluSatisGecmisiSayfasi::getUrl(),
                'aksiyonlar' => [
                    ['etiket' => 'Hızlı satış', 'url' => BarkodluSatisSayfasi::getUrl(), 'vurgu' => true],
                    ['etiket' => 'Satış geçmişi', 'url' => BarkodluSatisGecmisiSayfasi::getUrl()],
                    ['etiket' => 'İade geçmişi', 'url' => BarkodluSatisIadeGecmisiSayfasi::getUrl()],
                    ['etiket' => 'Stok listesi', 'url' => StokKartiKaynagi::getUrl('index')],
                ],
            ],
            [
                'baslik' => 'Teklif',
                'aciklama' => 'Teklif hazırlama ve takip ekranları.',
                'url' => TeklifKaynagi::getUrl('index'),
                'aksiyonlar' => [
                    ['etiket' => 'Yeni teklif', 'url' => TeklifKaynagi::getUrl('create'), 'vurgu' => true],
                    ['etiket' => 'Teklifler', 'url' => TeklifKaynagi::getUrl('index')],
                    ['etiket' => 'Cari kartları', 'url' => CariKartiKaynagi::getUrl('index')],
                    ['etiket' => 'Giden fatura', 'url' => FaturaKaynagi::getUrl('createGiden')],
                ],
            ],
            [
                'baslik' => 'Web',
                'aciklama' => 'İçerik, proje, blog ve web ürünleri.',
                'url' => WebServisListesi::getUrl(),
                'aksiyonlar' => [
                    ['etiket' => 'Servis listesi', 'url' => WebServisListesi::getUrl(), 'vurgu' => true],
                    ['etiket' => 'Projeler', 'url' => WebProje::getUrl()],
                    ['etiket' => 'Blog listesi', 'url' => BlogListesi::getUrl()],
                    ['etiket' => 'Web ürünleri', 'url' => UrunKaynagi::getUrl('index')],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array{baslik:string,bos:string,kayitlar:array<int, array{baslik:string,alt:string,deger:string,url:?string}>,vurgu?:string}>
     */
    private function altListeler(int $firmaId): array
    {
        return [
            ['baslik' => 'Acil Servisler', 'bos' => 'Geciken açık servis yok.', 'kayitlar' => $this->acilServisler($firmaId), 'vurgu' => 'warning'],
            ['baslik' => 'Teslim Bekleyen Servisler', 'bos' => 'Teslim bekleyen servis yok.', 'kayitlar' => $this->teslimBekleyenServisler($firmaId), 'vurgu' => 'success'],
            ['baslik' => 'Vadesi Geçen Tahsilatlar', 'bos' => 'Vadesi geçen tahsilat yok.', 'kayitlar' => $this->bekleyenTahsilatlar($firmaId), 'vurgu' => 'danger'],
            ['baslik' => 'Bekleyen Siparişler', 'bos' => 'Bekleyen sipariş yok.', 'kayitlar' => $this->bekleyenSiparisler($firmaId), 'vurgu' => 'warning'],
            ['baslik' => 'Son Servis Kayıtları', 'bos' => 'Servis kaydı yok.', 'kayitlar' => $this->sonServisKayitlari($firmaId)],
            ['baslik' => 'Yeni Siparişler', 'bos' => 'Yeni sipariş yok.', 'kayitlar' => $this->yeniSiparisler($firmaId)],
            ['baslik' => 'Bekleyen Mesajlar', 'bos' => 'Bekleyen mesaj yok.', 'kayitlar' => $this->bekleyenMesajlar($firmaId)],
            ['baslik' => 'Kritik Stoklar', 'bos' => 'Kritik stok yok.', 'kayitlar' => $this->kritikStoklar($firmaId)],
            ['baslik' => 'Son Barkodlu Satışlar', 'bos' => 'Barkodlu satış yok.', 'kayitlar' => $this->sonBarkodluSatislar($firmaId)],
        ];
    }

    private function acikServisSayisi(int $firmaId): int
    {
        if (! SaaSemaYardimcisi::tabloVarMi('teknik_servis_kayitlari')) {
            return 0;
        }

        $terminalIds = $this->terminalServisDurumIdleri($firmaId);

        return (int) DB::table('teknik_servis_kayitlari')
            ->where('firma_id', $firmaId)
            ->when($this->kolonVarMi('teknik_servis_kayitlari', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->when($terminalIds !== [], fn ($q) => $q->whereNotIn('servis_durumu_id', $terminalIds))
            ->count();
    }

    private function bugunGelenServisSayisi(int $firmaId): int
    {
        if (! SaaSemaYardimcisi::tabloVarMi('teknik_servis_kayitlari')) {
            return 0;
        }

        return (int) DB::table('teknik_servis_kayitlari')
            ->where('firma_id', $firmaId)
            ->when($this->kolonVarMi('teknik_servis_kayitlari', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->whereBetween('kabul_tarihi', $this->bugunAraligi())
            ->count();
    }

    private function gecikenServisSayisi(int $firmaId): int
    {
        if (! SaaSemaYardimcisi::tabloVarMi('teknik_servis_kayitlari')) {
            return 0;
        }

        $terminalIds = $this->terminalServisDurumIdleri($firmaId);

        return (int) DB::table('teknik_servis_kayitlari')
            ->where('firma_id', $firmaId)
            ->when($this->kolonVarMi('teknik_servis_kayitlari', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->when($terminalIds !== [], fn ($q) => $q->whereNotIn('servis_durumu_id', $terminalIds))
            ->whereDate('kabul_tarihi', '<', Carbon::today())
            ->count();
    }

    private function teslimBekleyenServisSayisi(int $firmaId): int
    {
        if (! SaaSemaYardimcisi::tabloVarMi('teknik_servis_kayitlari') || ! SaaSemaYardimcisi::tabloVarMi('teknik_servis_tanim_servis_durumlari')) {
            return 0;
        }

        return (int) DB::table('teknik_servis_kayitlari as kayit')
            ->join('teknik_servis_tanim_servis_durumlari as durum', 'durum.id', '=', 'kayit.servis_durumu_id')
            ->where('kayit.firma_id', $firmaId)
            ->when($this->kolonVarMi('teknik_servis_kayitlari', 'deleted_at'), fn ($q) => $q->whereNull('kayit.deleted_at'))
            ->when($this->kolonVarMi('teknik_servis_tanim_servis_durumlari', 'deleted_at'), fn ($q) => $q->whereNull('durum.deleted_at'))
            ->where(function ($q): void {
                $q->where('durum.kod', 'like', '%teslim%')
                    ->orWhere('durum.ad', 'like', '%Teslim Bekleyen%');
            })
            ->where(function ($q): void {
                $q->where('durum.is_teslim_edildi', false)->orWhereNull('durum.is_teslim_edildi');
            })
            ->count();
    }

    /**
     * @return array<int, int>
     */
    private function terminalServisDurumIdleri(int $firmaId): array
    {
        if (! SaaSemaYardimcisi::tabloVarMi('teknik_servis_tanim_servis_durumlari')) {
            return [];
        }

        return DB::table('teknik_servis_tanim_servis_durumlari')
            ->where(function ($q) use ($firmaId): void {
                $q->where('firma_id', $firmaId)->orWhereNull('firma_id');
            })
            ->when($this->kolonVarMi('teknik_servis_tanim_servis_durumlari', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->where(function ($q): void {
                $q->where('is_teslim_edildi', true)
                    ->orWhere('is_iptal', true)
                    ->orWhere('is_iade', true);
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function yeniSiparisSayisi(int $firmaId): int
    {
        if (! SaaSemaYardimcisi::tabloVarMi('siparisler')) {
            return 0;
        }

        return (int) DB::table('siparisler')
            ->where('firma_id', $firmaId)
            ->whereBetween('created_at', $this->bugunAraligi())
            ->count();
    }

    private function bekleyenSiparisSayisi(int $firmaId): int
    {
        if (! SaaSemaYardimcisi::tabloVarMi('siparisler')) {
            return 0;
        }

        return (int) DB::table('siparisler')
            ->where('firma_id', $firmaId)
            ->whereIn('durum', [
                Siparis::DURUM_ONAY_BEKLIYOR,
                Siparis::DURUM_EFT_ONAYI_BEKLIYOR,
                Siparis::DURUM_ODEME_BEKLENIYOR,
                Siparis::DURUM_DETAY_BEKLEYEN,
                Siparis::DURUM_BEKLEMEDE,
            ])
            ->count();
    }

    private function bugunkuSiparisTutari(int $firmaId): float
    {
        if (! SaaSemaYardimcisi::tabloVarMi('siparisler')) {
            return 0.0;
        }

        return (float) DB::table('siparisler')
            ->where('firma_id', $firmaId)
            ->whereBetween('created_at', $this->bugunAraligi())
            ->whereNotIn('durum', [Siparis::DURUM_IPTAL_EDILDI, Siparis::DURUM_IPTAL, Siparis::DURUM_BASARISIZ_ODEME])
            ->sum('genel_toplam');
    }

    private function bugunkuSiparisParaBirimiEtiketi(int $firmaId): string
    {
        if (! SaaSemaYardimcisi::tabloVarMi('siparisler')) {
            return '0,00 TRY';
        }

        $satirlar = DB::table('siparisler')
            ->where('firma_id', $firmaId)
            ->whereBetween('created_at', $this->bugunAraligi())
            ->whereNotIn('durum', [Siparis::DURUM_IPTAL_EDILDI, Siparis::DURUM_IPTAL, Siparis::DURUM_BASARISIZ_ODEME])
            ->selectRaw("UPPER(COALESCE(para_birimi, 'TRY')) as para_birimi, COALESCE(SUM(genel_toplam), 0) as toplam")
            ->groupByRaw("UPPER(COALESCE(para_birimi, 'TRY'))")
            ->orderBy('para_birimi')
            ->get();

        return $this->paraBirimiToplamEtiketi($satirlar);
    }

    private function okunmamisMesajKonusuSayisi(int $firmaId, string $konuTipi): int
    {
        if (! SaaSemaYardimcisi::tabloVarMi('ecommerce_mesaj_konulari')) {
            return 0;
        }

        return (int) DB::table('ecommerce_mesaj_konulari')
            ->where('firma_id', $firmaId)
            ->where('konu_tipi', $konuTipi)
            ->where(function ($q): void {
                $q->where('okunmamis_mi', true)
                    ->orWhere('okunmamis_mesaj_sayisi', '>', 0)
                    ->orWhereIn('durum', [
                        EcommerceMesajTanimlari::DURUM_YENI,
                        EcommerceMesajTanimlari::DURUM_OKUNMAMIS,
                        EcommerceMesajTanimlari::DURUM_MUSTERI_YANITI_GELDI,
                    ]);
            })
            ->count();
    }

    private function toplamOkunmamisMesajSayisi(int $firmaId): int
    {
        if (! SaaSemaYardimcisi::tabloVarMi('ecommerce_mesaj_konulari')) {
            return 0;
        }

        return (int) DB::table('ecommerce_mesaj_konulari')
            ->where('firma_id', $firmaId)
            ->whereIn('konu_tipi', [
                EcommerceMesajTanimlari::KONU_TIPI_MUSTERI,
                EcommerceMesajTanimlari::KONU_TIPI_URUN,
            ])
            ->where(function ($q): void {
                $q->where('okunmamis_mi', true)
                    ->orWhere('okunmamis_mesaj_sayisi', '>', 0)
                    ->orWhereIn('durum', [
                        EcommerceMesajTanimlari::DURUM_YENI,
                        EcommerceMesajTanimlari::DURUM_OKUNMAMIS,
                        EcommerceMesajTanimlari::DURUM_MUSTERI_YANITI_GELDI,
                    ]);
            })
            ->count();
    }

    private function bugunkuBarkodluSatisSayisi(int $firmaId): int
    {
        if (! SaaSemaYardimcisi::tabloVarMi('muhasebe_barkodlu_satislar')) {
            return 0;
        }

        return (int) DB::table('muhasebe_barkodlu_satislar')
            ->where('firma_id', $firmaId)
            ->whereBetween('satis_tarihi', $this->bugunAraligi())
            ->where(function ($q): void {
                $q->whereNull('durum')->orWhere('durum', '!=', 'iptal');
            })
            ->count();
    }

    private function bugunkuBarkodluSatisTutari(int $firmaId): float
    {
        if (! SaaSemaYardimcisi::tabloVarMi('muhasebe_barkodlu_satislar')) {
            return 0.0;
        }

        return (float) DB::table('muhasebe_barkodlu_satislar')
            ->where('firma_id', $firmaId)
            ->whereBetween('satis_tarihi', $this->bugunAraligi())
            ->where(function ($q): void {
                $q->whereNull('durum')->orWhere('durum', '!=', 'iptal');
            })
            ->sum('genel_toplam');
    }

    private function bugunkuBarkodluSatisParaBirimiEtiketi(int $firmaId): string
    {
        if (! SaaSemaYardimcisi::tabloVarMi('muhasebe_barkodlu_satislar')) {
            return '0,00 TRY';
        }

        $satirlar = DB::table('muhasebe_barkodlu_satislar')
            ->where('firma_id', $firmaId)
            ->whereBetween('satis_tarihi', $this->bugunAraligi())
            ->where(function ($q): void {
                $q->whereNull('durum')->orWhere('durum', '!=', 'iptal');
            })
            ->selectRaw("UPPER(COALESCE(para_birimi, 'TRY')) as para_birimi, COALESCE(SUM(genel_toplam), 0) as toplam")
            ->groupByRaw("UPPER(COALESCE(para_birimi, 'TRY'))")
            ->orderBy('para_birimi')
            ->get();

        return $this->paraBirimiToplamEtiketi($satirlar);
    }

    private function bugunkuBarkodluSatisIadeTutari(int $firmaId): float
    {
        if (! SaaSemaYardimcisi::tabloVarMi('muhasebe_barkodlu_satis_iadeler')) {
            return 0.0;
        }

        return (float) DB::table('muhasebe_barkodlu_satis_iadeler')
            ->where('firma_id', $firmaId)
            ->whereBetween('iade_tarihi', $this->bugunAraligi())
            ->sum('toplam_iade_tutari');
    }

    private function bugunkuBarkodluSatisIadeParaBirimiEtiketi(int $firmaId): string
    {
        if (! SaaSemaYardimcisi::tabloVarMi('muhasebe_barkodlu_satis_iadeler')) {
            return '0,00 TRY';
        }

        $satirlar = DB::table('muhasebe_barkodlu_satis_iadeler')
            ->join('muhasebe_barkodlu_satislar as satis', 'satis.id', '=', 'muhasebe_barkodlu_satis_iadeler.satis_id')
            ->where('firma_id', $firmaId)
            ->whereBetween('iade_tarihi', $this->bugunAraligi())
            ->selectRaw("UPPER(COALESCE(satis.para_birimi, 'TRY')) as para_birimi, COALESCE(SUM(toplam_iade_tutari), 0) as toplam")
            ->groupByRaw("UPPER(COALESCE(satis.para_birimi, 'TRY'))")
            ->orderBy('para_birimi')
            ->get();

        return $this->paraBirimiToplamEtiketi($satirlar);
    }

    private function paraBirimiToplamEtiketi(iterable $satirlar): string
    {
        $toplamlar = [];
        foreach ($satirlar as $satir) {
            $paraBirimi = strtoupper((string) ($satir->para_birimi ?? 'TRY'));
            $toplamlar[$paraBirimi] = ($toplamlar[$paraBirimi] ?? 0.0) + (float) ($satir->toplam ?? 0);
        }

        if ($toplamlar === []) {
            return '0,00 TRY';
        }

        ksort($toplamlar);

        return collect($toplamlar)
            ->map(fn (float $toplam, string $paraBirimi): string => number_format($toplam, 2, ',', '.').' '.$paraBirimi)
            ->implode(' · ');
    }

    private function bekleyenTahsilatTutari(int $firmaId): string
    {
        if (! SaaSemaYardimcisi::tabloVarMi('muhasebe_alacak_plan_taksitleri')) {
            return '0,00 TRY';
        }

        $satirlar = DB::table('muhasebe_alacak_plan_taksitleri')
            ->where('firma_id', $firmaId)
            ->when($this->kolonVarMi('muhasebe_alacak_plan_taksitleri', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->whereDate('vade_tarihi', '<=', Carbon::today())
            ->whereNotIn('durum', ['odendi', 'iptal'])
            ->selectRaw("UPPER(COALESCE(para_birimi, 'TRY')) as para_birimi, COALESCE(SUM(kalan_tutar), 0) as toplam")
            ->groupByRaw("UPPER(COALESCE(para_birimi, 'TRY'))")
            ->orderBy('para_birimi')
            ->get();

        return $this->paraBirimiToplamEtiketi($satirlar);
    }

    private function gecikenTaksitSayisi(int $firmaId): int
    {
        if (! SaaSemaYardimcisi::tabloVarMi('muhasebe_alacak_plan_taksitleri')) {
            return 0;
        }

        return (int) DB::table('muhasebe_alacak_plan_taksitleri')
            ->where('firma_id', $firmaId)
            ->when($this->kolonVarMi('muhasebe_alacak_plan_taksitleri', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->whereDate('vade_tarihi', '<=', Carbon::today())
            ->whereNotIn('durum', ['odendi', 'iptal'])
            ->count();
    }

    private function kritikStokSayisi(int $firmaId): int
    {
        if (! SaaSemaYardimcisi::tabloVarMi('stok_kartlari')) {
            return 0;
        }

        return (int) DB::table('stok_kartlari')
            ->where('firma_id', $firmaId)
            ->when($this->kolonVarMi('stok_kartlari', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->where('stok_takip', true)
            ->whereRaw('COALESCE(stok_miktari, 0) <= COALESCE(kritik_seviye_miktar, minimum_stok, 0)')
            ->count();
    }

    private function aktifUrunSayisi(int $firmaId): int
    {
        if (! SaaSemaYardimcisi::tabloVarMi('stok_kartlari')) {
            return 0;
        }

        return (int) DB::table('stok_kartlari')
            ->where('firma_id', $firmaId)
            ->when($this->kolonVarMi('stok_kartlari', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->whereIn('durum', ['aktif', 'Aktif'])
            ->count();
    }

    private function teklifSayisi(int $firmaId): int
    {
        if (! SaaSemaYardimcisi::tabloVarMi('teklifler')) {
            return 0;
        }

        return (int) DB::table('teklifler')
            ->where('firma_id', $firmaId)
            ->when($this->kolonVarMi('teklifler', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
    }

    private function onaylananTeklifSayisi(int $firmaId): int
    {
        if (! SaaSemaYardimcisi::tabloVarMi('teklifler')) {
            return 0;
        }

        return (int) DB::table('teklifler')
            ->where('firma_id', $firmaId)
            ->when($this->kolonVarMi('teklifler', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->where('created_at', '>=', now()->subDays(30))
            ->where('durum', 'onaylandi')
            ->count();
    }

    private function bekleyenTeklifSayisi(int $firmaId): int
    {
        if (! SaaSemaYardimcisi::tabloVarMi('teklifler')) {
            return 0;
        }

        return (int) DB::table('teklifler')
            ->where('firma_id', $firmaId)
            ->when($this->kolonVarMi('teklifler', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->where('created_at', '>=', now()->subDays(30))
            ->whereIn('durum', ['taslak', 'hazirlaniyor', 'gonderildi', 'revizyon_bekliyor'])
            ->count();
    }

    private function teklifTutari(int $firmaId): string
    {
        if (! SaaSemaYardimcisi::tabloVarMi('teklifler')) {
            return '0,00 TRY';
        }

        $satirlar = DB::table('teklifler')
            ->where('firma_id', $firmaId)
            ->when($this->kolonVarMi('teklifler', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->where('created_at', '>=', now()->subDays(30))
            ->select(['para_birimi', 'genel_toplam'])
            ->orderBy('para_birimi')
            ->get()
            ->groupBy(fn (object $satir): string => strtoupper((string) ($satir->para_birimi ?: 'TRY')))
            ->map(fn ($satirlar): float => (float) $satirlar->sum(fn (object $satir): float => (float) $satir->genel_toplam));

        if ($satirlar->isEmpty()) {
            return '0,00 TRY';
        }

        return $satirlar
            ->map(fn (float $toplam, string $paraBirimi): string => number_format($toplam, 2, ',', '.').' '.$paraBirimi)
            ->implode(' · ');
    }

    private function webTabloSayisi(string $tablo, ?string $aktifKolonu = null): int
    {
        if (! SaaSemaYardimcisi::tabloVarMi($tablo)) {
            return 0;
        }

        return (int) DB::table($tablo)
            ->when($this->kolonVarMi($tablo, 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->when($aktifKolonu !== null && $this->kolonVarMi($tablo, $aktifKolonu), fn ($q) => $q->where($aktifKolonu, true))
            ->count();
    }

    private function firmaTabloSayisi(string $tablo, int $firmaId): int
    {
        if (! SaaSemaYardimcisi::tabloVarMi($tablo)) {
            return 0;
        }

        return (int) DB::table($tablo)
            ->where('firma_id', $firmaId)
            ->when($this->kolonVarMi($tablo, 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->count();
    }

    /**
     * @return array<int, array{baslik:string,alt:string,deger:string,url:?string}>
     */
    private function yeniSiparisler(int $firmaId): array
    {
        if (! SaaSemaYardimcisi::tabloVarMi('siparisler')) {
            return [];
        }

        return DB::table('siparisler')
            ->where('firma_id', $firmaId)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'siparis_no', 'musteri_ad_soyad', 'durum', 'genel_toplam', 'created_at'])
            ->map(fn ($siparis): array => [
                'baslik' => (string) ($siparis->siparis_no ?: 'Sipariş #'.$siparis->id),
                'alt' => trim((string) ($siparis->musteri_ad_soyad ?: Siparis::durumEtiketi((string) $siparis->durum))),
                'deger' => $this->paraBicimle((float) $siparis->genel_toplam),
                'url' => SiparisKaynagi::getUrl('view', ['record' => (int) $siparis->id]),
            ])
            ->all();
    }

    /**
     * @return array<int, array{baslik:string,alt:string,deger:string,url:?string}>
     */
    private function bekleyenSiparisler(int $firmaId): array
    {
        if (! SaaSemaYardimcisi::tabloVarMi('siparisler')) {
            return [];
        }

        return DB::table('siparisler')
            ->where('firma_id', $firmaId)
            ->whereIn('durum', [
                Siparis::DURUM_ONAY_BEKLIYOR,
                Siparis::DURUM_EFT_ONAYI_BEKLIYOR,
                Siparis::DURUM_ODEME_BEKLENIYOR,
                Siparis::DURUM_DETAY_BEKLEYEN,
                Siparis::DURUM_BEKLEMEDE,
            ])
            ->orderBy('created_at')
            ->limit(5)
            ->get(['id', 'siparis_no', 'musteri_ad_soyad', 'durum', 'genel_toplam', 'created_at'])
            ->map(fn ($siparis): array => [
                'baslik' => (string) ($siparis->siparis_no ?: 'Sipariş #'.$siparis->id),
                'alt' => trim((string) ($siparis->musteri_ad_soyad ?: Siparis::durumEtiketi((string) $siparis->durum))),
                'deger' => $this->paraBicimle((float) $siparis->genel_toplam),
                'url' => SiparisKaynagi::getUrl('view', ['record' => (int) $siparis->id]),
            ])
            ->all();
    }

    /**
     * @return array<int, array{baslik:string,alt:string,deger:string,url:?string}>
     */
    private function acilServisler(int $firmaId): array
    {
        if (! SaaSemaYardimcisi::tabloVarMi('teknik_servis_kayitlari')) {
            return [];
        }

        $terminalIds = $this->terminalServisDurumIdleri($firmaId);

        $sorgu = DB::table('teknik_servis_kayitlari as kayit')
            ->leftJoin('teknik_servis_tanim_servis_durumlari as durum', 'durum.id', '=', 'kayit.servis_durumu_id')
            ->when(SaaSemaYardimcisi::tabloVarMi('cariler'), function ($q): void {
                $q->leftJoin('cariler as cari', function ($join): void {
                $join->on('cari.id', '=', 'kayit.cari_id')
                    ->when($this->kolonVarMi('cariler', 'deleted_at'), fn ($q) => $q->whereNull('cari.deleted_at'));
                });
            })
            ->where('kayit.firma_id', $firmaId)
            ->when($this->kolonVarMi('teknik_servis_kayitlari', 'deleted_at'), fn ($q) => $q->whereNull('kayit.deleted_at'))
            ->when($terminalIds !== [], fn ($q) => $q->whereNotIn('kayit.servis_durumu_id', $terminalIds))
            ->whereDate('kayit.kabul_tarihi', '<', Carbon::today())
            ->orderBy('kayit.kabul_tarihi');

        $kolonlar = [
                'kayit.id',
                'kayit.fis_no',
                'kayit.musteri_ad_soyad',
                'kayit.kabul_tarihi',
                'durum.ad as durum_adi',
        ];

        if (SaaSemaYardimcisi::tabloVarMi('cariler')) {
            $kolonlar[] = 'cari.ad as cari_adi';
        }

        return $sorgu
            ->limit(5)
            ->get($kolonlar)
            ->map(function ($kayit): array {
                $kabulTarihi = $kayit->kabul_tarihi ? Carbon::parse((string) $kayit->kabul_tarihi) : null;
                $gun = $kabulTarihi ? (int) $kabulTarihi->copy()->startOfDay()->diffInDays(now()->startOfDay()) : 0;

                return [
                    'baslik' => (string) ($kayit->fis_no ?: 'Servis #'.$kayit->id),
                    'alt' => trim((string) (($kayit->cari_adi ?? null) ?: ($kayit->musteri_ad_soyad ?? null) ?: 'Müşteri belirtilmemiş')),
                    'deger' => $gun > 0 ? $gun.' gün' : (string) ($kayit->durum_adi ?: '-'),
                    'url' => TeknikServisKaydiKaynagi::getUrl('edit', ['record' => (int) $kayit->id]),
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array{baslik:string,alt:string,deger:string,url:?string}>
     */
    private function teslimBekleyenServisler(int $firmaId): array
    {
        if (! SaaSemaYardimcisi::tabloVarMi('teknik_servis_kayitlari') || ! SaaSemaYardimcisi::tabloVarMi('teknik_servis_tanim_servis_durumlari')) {
            return [];
        }

        return DB::table('teknik_servis_kayitlari as kayit')
            ->join('teknik_servis_tanim_servis_durumlari as durum', 'durum.id', '=', 'kayit.servis_durumu_id')
            ->when(SaaSemaYardimcisi::tabloVarMi('cariler'), function ($q): void {
                $q->leftJoin('cariler as cari', function ($join): void {
                    $join->on('cari.id', '=', 'kayit.cari_id')
                        ->when($this->kolonVarMi('cariler', 'deleted_at'), fn ($q) => $q->whereNull('cari.deleted_at'));
                });
            })
            ->where('kayit.firma_id', $firmaId)
            ->when($this->kolonVarMi('teknik_servis_kayitlari', 'deleted_at'), fn ($q) => $q->whereNull('kayit.deleted_at'))
            ->when($this->kolonVarMi('teknik_servis_tanim_servis_durumlari', 'deleted_at'), fn ($q) => $q->whereNull('durum.deleted_at'))
            ->where(function ($q): void {
                $q->where('durum.kod', 'like', '%teslim%')
                    ->orWhere('durum.ad', 'like', '%Teslim Bekleyen%');
            })
            ->where(function ($q): void {
                $q->where('durum.is_teslim_edildi', false)->orWhereNull('durum.is_teslim_edildi');
            })
            ->orderBy('kayit.kabul_tarihi')
            ->limit(5)
            ->get(array_values(array_filter([
                'kayit.id',
                'kayit.fis_no',
                'kayit.musteri_ad_soyad',
                'kayit.kabul_tarihi',
                'durum.ad as durum_adi',
                SaaSemaYardimcisi::tabloVarMi('cariler') ? 'cari.ad as cari_adi' : null,
            ])))
            ->map(function ($kayit): array {
                $kabulTarihi = $kayit->kabul_tarihi ? Carbon::parse((string) $kayit->kabul_tarihi) : null;

                return [
                    'baslik' => (string) ($kayit->fis_no ?: 'Servis #'.$kayit->id),
                    'alt' => trim((string) (($kayit->cari_adi ?? null) ?: ($kayit->musteri_ad_soyad ?? null) ?: 'Müşteri belirtilmemiş')),
                    'deger' => $kabulTarihi?->format('d.m.Y') ?? (string) ($kayit->durum_adi ?: '-'),
                    'url' => TeknikServisKaydiKaynagi::getUrl('edit', ['record' => (int) $kayit->id]),
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array{baslik:string,alt:string,deger:string,url:?string}>
     */
    private function sonServisKayitlari(int $firmaId): array
    {
        if (! SaaSemaYardimcisi::tabloVarMi('teknik_servis_kayitlari')) {
            return [];
        }

        return DB::table('teknik_servis_kayitlari as kayit')
            ->leftJoin('teknik_servis_tanim_servis_durumlari as durum', 'durum.id', '=', 'kayit.servis_durumu_id')
            ->where('kayit.firma_id', $firmaId)
            ->when($this->kolonVarMi('teknik_servis_kayitlari', 'deleted_at'), fn ($q) => $q->whereNull('kayit.deleted_at'))
            ->orderByDesc('kayit.created_at')
            ->limit(5)
            ->get(['kayit.id', 'kayit.fis_no', 'kayit.musteri_ad_soyad', 'kayit.kabul_tarihi', 'durum.ad as durum_adi'])
            ->map(fn ($kayit): array => [
                'baslik' => (string) ($kayit->fis_no ?: 'Servis #'.$kayit->id),
                'alt' => trim((string) ($kayit->musteri_ad_soyad ?: 'Müşteri belirtilmemiş')),
                'deger' => (string) ($kayit->durum_adi ?: '-'),
                'url' => TeknikServisKaydiKaynagi::getUrl('edit', ['record' => (int) $kayit->id]),
            ])
            ->all();
    }

    /**
     * @return array<int, array{baslik:string,alt:string,deger:string,url:?string}>
     */
    private function bekleyenTahsilatlar(int $firmaId): array
    {
        if (! SaaSemaYardimcisi::tabloVarMi('muhasebe_alacak_plan_taksitleri')) {
            return [];
        }

        return DB::table('muhasebe_alacak_plan_taksitleri as taksit')
            ->leftJoin('cariler as cari', 'cari.id', '=', 'taksit.cari_id')
            ->where('taksit.firma_id', $firmaId)
            ->when($this->kolonVarMi('muhasebe_alacak_plan_taksitleri', 'deleted_at'), fn ($q) => $q->whereNull('taksit.deleted_at'))
            ->whereDate('taksit.vade_tarihi', '<=', Carbon::today())
            ->whereNotIn('taksit.durum', ['odendi', 'iptal'])
            ->orderBy('taksit.vade_tarihi')
            ->limit(5)
            ->get(['taksit.id', 'taksit.vade_tarihi', 'taksit.kalan_tutar', 'cari.ad as cari_ad'])
            ->map(fn ($taksit): array => [
                'baslik' => (string) (($taksit->cari_ad ?? null) ?: 'Cari belirtilmemiş'),
                'alt' => 'Vade: '.Carbon::parse((string) $taksit->vade_tarihi)->format('d.m.Y'),
                'deger' => $this->paraBicimle((float) $taksit->kalan_tutar),
                'url' => VadeTakipSayfasi::getUrl(),
            ])
            ->all();
    }

    /**
     * @return array<int, array{baslik:string,alt:string,deger:string,url:?string}>
     */
    private function bekleyenMesajlar(int $firmaId): array
    {
        if (! SaaSemaYardimcisi::tabloVarMi('ecommerce_mesaj_konulari')) {
            return [];
        }

        return DB::table('ecommerce_mesaj_konulari')
            ->where('firma_id', $firmaId)
            ->where(function ($q): void {
                $q->where('okunmamis_mi', true)
                    ->orWhere('okunmamis_mesaj_sayisi', '>', 0)
                    ->orWhereIn('durum', [
                        EcommerceMesajTanimlari::DURUM_YENI,
                        EcommerceMesajTanimlari::DURUM_OKUNMAMIS,
                        EcommerceMesajTanimlari::DURUM_MUSTERI_YANITI_GELDI,
                    ]);
            })
            ->orderByDesc('son_musteri_mesaji_at')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get(['id', 'konu_tipi', 'baslik', 'musteri_ad_soyad', 'okunmamis_mesaj_sayisi', 'son_musteri_mesaji_at'])
            ->map(fn ($konu): array => [
                'baslik' => (string) ($konu->baslik ?: (($konu->musteri_ad_soyad ?? null) ?: 'Mesaj konusu')),
                'alt' => (string) (($konu->musteri_ad_soyad ?? null) ?: ($konu->konu_tipi === EcommerceMesajTanimlari::KONU_TIPI_URUN ? 'Ürün mesajı' : 'Müşteri mesajı')),
                'deger' => ((int) ($konu->okunmamis_mesaj_sayisi ?? 0)).' yeni',
                'url' => $konu->konu_tipi === EcommerceMesajTanimlari::KONU_TIPI_URUN
                    ? UrunMesajlariSayfasi::getUrl()
                    : MusteriMesajlariSayfasi::getUrl(),
            ])
            ->all();
    }

    /**
     * @return array<int, array{baslik:string,alt:string,deger:string,url:?string}>
     */
    private function sonBarkodluSatislar(int $firmaId): array
    {
        if (! SaaSemaYardimcisi::tabloVarMi('muhasebe_barkodlu_satislar')) {
            return [];
        }

        return DB::table('muhasebe_barkodlu_satislar as satis')
            ->leftJoin('cariler as cari', 'cari.id', '=', 'satis.cari_id')
            ->where('satis.firma_id', $firmaId)
            ->orderByDesc('satis.satis_tarihi')
            ->limit(5)
            ->get(['satis.id', 'satis.satis_no', 'satis.satis_tarihi', 'satis.genel_toplam', 'satis.durum', 'cari.ad as cari_ad'])
            ->map(fn ($satis): array => [
                'baslik' => (string) ($satis->satis_no ?: 'Satış #'.$satis->id),
                'alt' => (string) (($satis->cari_ad ?? null) ?: ((string) ($satis->durum ?? 'Satış'))),
                'deger' => $this->paraBicimle((float) $satis->genel_toplam),
                'url' => BarkodluSatisGecmisiSayfasi::getUrl(),
            ])
            ->all();
    }

    /**
     * @return array<int, array{baslik:string,alt:string,deger:string,url:?string}>
     */
    private function kritikStoklar(int $firmaId): array
    {
        if (! SaaSemaYardimcisi::tabloVarMi('stok_kartlari')) {
            return [];
        }

        return DB::table('stok_kartlari')
            ->where('firma_id', $firmaId)
            ->when($this->kolonVarMi('stok_kartlari', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->where('stok_takip', true)
            ->whereRaw('COALESCE(stok_miktari, 0) <= COALESCE(kritik_seviye_miktar, minimum_stok, 0)')
            ->orderBy('stok_miktari')
            ->limit(5)
            ->get(['id', 'ad', 'kod', 'stok_miktari', 'kritik_seviye_miktar', 'minimum_stok', 'birim'])
            ->map(fn ($stok): array => [
                'baslik' => (string) ($stok->ad ?: 'Stok #'.$stok->id),
                'alt' => (string) ($stok->kod ?: 'Kod yok'),
                'deger' => rtrim(rtrim(number_format((float) $stok->stok_miktari, 2, ',', '.'), '0'), ',').' '.(string) ($stok->birim ?: ''),
                'url' => StokKartiKaynagi::getUrl('edit', ['record' => (int) $stok->id]),
            ])
            ->all();
    }

    private function paraBicimle(float $tutar): string
    {
        return number_format($tutar, 2, ',', '.').' TL';
    }

    private function kisaParaBicimle(float $tutar): string
    {
        $mutlakTutar = abs($tutar);

        if ($mutlakTutar >= 1000000) {
            return number_format($tutar / 1000000, 1, ',', '.').' mn';
        }

        if ($mutlakTutar >= 1000) {
            return number_format($tutar / 1000, 1, ',', '.').' bin';
        }

        return number_format($tutar, 0, ',', '.').' TL';
    }

    private function kolonVarMi(string $tablo, string $kolon): bool
    {
        return SaaSemaYardimcisi::kolonVarMi($tablo, $kolon);
    }

    /**
     * @return array<int, Carbon>
     */
    private function bugunAraligi(): array
    {
        return [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()];
    }

}
