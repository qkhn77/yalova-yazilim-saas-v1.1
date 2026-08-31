<?php

namespace App\TeknikServis\Servisler;

use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TeknikServisRaporServisi
{
    /**
     * @return array<string, mixed>
     */
    public function karlilik(int $firmaId, Carbon $baslangic, Carbon $bitis): array
    {
        $kayitOzetleri = DB::table('teknik_servis_kayitlari as k')
            ->where('k.firma_id', $firmaId)
            ->whereNull('k.deleted_at')
            ->whereBetween('k.kabul_tarihi', [$baslangic, $bitis])
            ->selectRaw("COALESCE(NULLIF(k.tahsilat_para_birimi, ''), 'TRY') as para_birimi")
            ->selectRaw('COUNT(*) as servis_sayisi')
            ->selectRaw('COALESCE(SUM(k.toplam_tutar), 0) as servis_toplami')
            ->selectRaw('COALESCE(SUM(k.odenen_tutar), 0) as kayit_odenen_toplami')
            ->groupBy('k.tahsilat_para_birimi')
            ->get()
            ->keyBy('para_birimi');

        $kalemOzetleri = DB::table('teknik_servis_kalemleri as kalem')
            ->join('teknik_servis_kayitlari as k', 'k.id', '=', 'kalem.teknik_servis_kaydi_id')
            ->where('kalem.firma_id', $firmaId)
            ->whereNull('kalem.deleted_at')
            ->whereNull('k.deleted_at')
            ->whereBetween('k.kabul_tarihi', [$baslangic, $bitis])
            ->selectRaw("COALESCE(NULLIF(kalem.para_birimi, ''), COALESCE(NULLIF(k.tahsilat_para_birimi, ''), 'TRY')) as para_birimi")
            ->selectRaw("COALESCE(SUM(CASE WHEN kalem.kalem_rolu = 'satis' THEN kalem.satir_toplami ELSE 0 END), 0) as satis_toplami")
            ->selectRaw("COALESCE(SUM(CASE WHEN kalem.kalem_rolu = 'gider' THEN kalem.satir_toplami ELSE 0 END), 0) as gider_toplami")
            ->groupBy('kalem.para_birimi', 'k.tahsilat_para_birimi')
            ->get()
            ->keyBy('para_birimi');

        $tahsilatOzetleri = $this->aktifTahsilatSorgusu($firmaId)
            ->whereBetween('t.tarih', [$baslangic, $bitis])
            ->selectRaw("COALESCE(NULLIF(t.kaynak_para_birimi, ''), 'TRY') as para_birimi")
            ->selectRaw('COALESCE(SUM(t.tutar), 0) as toplam_tutar')
            ->groupBy('t.kaynak_para_birimi')
            ->get()
            ->keyBy('para_birimi');

        $paraBirimleri = $kayitOzetleri->keys()->merge($kalemOzetleri->keys())->merge($tahsilatOzetleri->keys())->unique();
        $gelirDagilimi = [];
        $giderDagilimi = [];
        $karDagilimi = [];
        foreach ($paraBirimleri as $paraBirimi) {
            $kayit = $kayitOzetleri->get($paraBirimi);
            $kalem = $kalemOzetleri->get($paraBirimi);
            $gelir = max((float) ($kayit?->servis_toplami ?? 0), (float) ($kalem?->satis_toplami ?? 0));
            $gider = (float) ($kalem?->gider_toplami ?? 0);
            $gelirDagilimi[$paraBirimi] = $gelir;
            $giderDagilimi[$paraBirimi] = $gider;
            $karDagilimi[$paraBirimi] = $gelir - $gider;
        }

        return [
            'kartlar' => [
                ['etiket' => 'Servis adedi', 'deger' => (string) (int) $kayitOzetleri->sum('servis_sayisi'), 'alt' => $this->tarihAraligi($baslangic, $bitis)],
                ['etiket' => 'Gelir toplamı', 'deger' => $this->paraDagilimi($gelirDagilimi), 'alt' => 'Para birimi bazında'],
                ['etiket' => 'Gider toplamı', 'deger' => $this->paraDagilimi($giderDagilimi), 'alt' => 'Para birimi bazında'],
                ['etiket' => 'Brüt kar', 'deger' => $this->paraDagilimi($karDagilimi), 'alt' => 'Aynı para birimi içinde hesaplandı'],
                ['etiket' => 'Tahsilat', 'deger' => $this->paraDagilimi($tahsilatOzetleri->mapWithKeys(fn (object $satir): array => [(string) $satir->para_birimi => (float) $satir->toplam_tutar])->all()), 'alt' => 'Dönem içi aktif tahsilat'],
            ],
            'tablolar' => [
                [
                    'baslik' => 'Servis tipine göre karlılık',
                    'kolonlar' => [
                        ['key' => 'tip', 'label' => 'Servis tipi'],
                        ['key' => 'servis', 'label' => 'Servis', 'align' => 'right'],
                        ['key' => 'gelir', 'label' => 'Gelir', 'align' => 'right'],
                        ['key' => 'gider', 'label' => 'Gider', 'align' => 'right'],
                        ['key' => 'kar', 'label' => 'Kar', 'align' => 'right'],
                    ],
                    'satirlar' => $this->servisTipiKarlilikSatirlari($firmaId, $baslangic, $bitis),
                    'bos' => 'Bu dönemde servis kaydı yok.',
                ],
                [
                    'baslik' => 'En yüksek tutarlı servisler',
                    'kolonlar' => [
                        ['key' => 'fis', 'label' => 'Fiş no'],
                        ['key' => 'musteri', 'label' => 'Müşteri'],
                        ['key' => 'durum', 'label' => 'Durum'],
                        ['key' => 'toplam', 'label' => 'Toplam', 'align' => 'right'],
                        ['key' => 'tahsilat', 'label' => 'Ödenen', 'align' => 'right'],
                    ],
                    'satirlar' => $this->enYuksekTutarliServisler($firmaId, $baslangic, $bitis),
                    'bos' => 'Tutarı olan servis kaydı yok.',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function personelPerformansi(int $firmaId, Carbon $baslangic, Carbon $bitis): array
    {
        $gorevler = DB::table('teknik_servis_gorev_atamalari as g')
            ->leftJoin('users as u', 'u.id', '=', 'g.atanan_kullanici_id')
            ->where('g.firma_id', $firmaId)
            ->whereBetween('g.baslangic_tarihi', [$baslangic, $bitis])
            ->selectRaw("COALESCE(NULLIF(u.ad_soyad, ''), u.name, CONCAT('#', g.atanan_kullanici_id)) as personel")
            ->selectRaw('COUNT(*) as gorev_sayisi')
            ->selectRaw("SUM(CASE WHEN g.durum = 'aktif' THEN 1 ELSE 0 END) as aktif_gorev")
            ->selectRaw("SUM(CASE WHEN g.durum <> 'aktif' OR g.bitis_tarihi IS NOT NULL THEN 1 ELSE 0 END) as tamamlanan_gorev")
            ->selectRaw('MIN(g.baslangic_tarihi) as ilk_gorev')
            ->selectRaw('MAX(COALESCE(g.bitis_tarihi, g.baslangic_tarihi)) as son_hareket')
            ->groupBy('g.atanan_kullanici_id', 'u.ad_soyad', 'u.name')
            ->orderByDesc('gorev_sayisi')
            ->limit(15)
            ->get();

        $kayitAcanlar = DB::table('teknik_servis_kayitlari as k')
            ->leftJoin('users as u', 'u.id', '=', 'k.olusturan_id')
            ->where('k.firma_id', $firmaId)
            ->whereNull('k.deleted_at')
            ->whereBetween('k.kabul_tarihi', [$baslangic, $bitis])
            ->selectRaw("COALESCE(NULLIF(u.ad_soyad, ''), u.name, CONCAT('#', k.olusturan_id)) as personel")
            ->selectRaw("COALESCE(NULLIF(k.tahsilat_para_birimi, ''), 'TRY') as para_birimi")
            ->selectRaw('COUNT(*) as kayit_sayisi')
            ->selectRaw('COALESCE(SUM(k.toplam_tutar), 0) as toplam_tutar')
            ->groupBy('k.olusturan_id', 'u.ad_soyad', 'u.name', 'k.tahsilat_para_birimi')
            ->orderByDesc('kayit_sayisi')
            ->limit(15)
            ->get();

        $teslimEdenler = DB::table('teknik_servis_kayitlari as k')
            ->leftJoin('users as u', 'u.id', '=', 'k.teslim_eden_kullanici_id')
            ->where('k.firma_id', $firmaId)
            ->whereNull('k.deleted_at')
            ->whereNotNull('k.teslim_eden_kullanici_id')
            ->whereBetween('k.teslim_tarihi', [$baslangic, $bitis])
            ->selectRaw("COALESCE(NULLIF(u.ad_soyad, ''), u.name, CONCAT('#', k.teslim_eden_kullanici_id)) as personel")
            ->selectRaw("COALESCE(NULLIF(k.tahsilat_para_birimi, ''), 'TRY') as para_birimi")
            ->selectRaw('COUNT(*) as teslim_sayisi')
            ->selectRaw('COALESCE(SUM(k.toplam_tutar), 0) as toplam_tutar')
            ->groupBy('k.teslim_eden_kullanici_id', 'u.ad_soyad', 'u.name', 'k.tahsilat_para_birimi')
            ->orderByDesc('teslim_sayisi')
            ->limit(15)
            ->get();

        return [
            'kartlar' => [
                ['etiket' => 'Atanan görev', 'deger' => (string) (int) $gorevler->sum('gorev_sayisi'), 'alt' => $this->tarihAraligi($baslangic, $bitis)],
                ['etiket' => 'Aktif görev', 'deger' => (string) (int) $gorevler->sum('aktif_gorev'), 'alt' => 'Dönemde atanan'],
                ['etiket' => 'Tamamlanan görev', 'deger' => (string) (int) $gorevler->sum('tamamlanan_gorev'), 'alt' => 'Bitiş/durum bilgisine göre'],
                ['etiket' => 'Kayıt açan kullanıcı', 'deger' => (string) $kayitAcanlar->pluck('personel')->unique()->count(), 'alt' => (int) $kayitAcanlar->sum('kayit_sayisi').' kayıt'],
                ['etiket' => 'Teslim eden kullanıcı', 'deger' => (string) $teslimEdenler->pluck('personel')->unique()->count(), 'alt' => (int) $teslimEdenler->sum('teslim_sayisi').' teslim'],
            ],
            'tablolar' => [
                [
                    'baslik' => 'Görev performansı',
                    'kolonlar' => [
                        ['key' => 'personel', 'label' => 'Personel'],
                        ['key' => 'gorev', 'label' => 'Görev', 'align' => 'right'],
                        ['key' => 'aktif', 'label' => 'Aktif', 'align' => 'right'],
                        ['key' => 'tamamlanan', 'label' => 'Tamamlanan', 'align' => 'right'],
                        ['key' => 'son', 'label' => 'Son hareket'],
                    ],
                    'satirlar' => $gorevler->map(fn (object $satir): array => [
                        'personel' => (string) $satir->personel.' ('.strtoupper((string) ($satir->para_birimi ?: 'TRY')).')',
                        'gorev' => (string) (int) $satir->gorev_sayisi,
                        'aktif' => (string) (int) $satir->aktif_gorev,
                        'tamamlanan' => (string) (int) $satir->tamamlanan_gorev,
                        'son' => $this->tarihSaat($satir->son_hareket),
                    ])->all(),
                    'bos' => 'Bu dönemde görev ataması yok.',
                ],
                [
                    'baslik' => 'Kayıt açan kullanıcılar',
                    'kolonlar' => [
                        ['key' => 'personel', 'label' => 'Kullanıcı'],
                        ['key' => 'kayit', 'label' => 'Kayıt', 'align' => 'right'],
                        ['key' => 'toplam', 'label' => 'Toplam', 'align' => 'right'],
                    ],
                    'satirlar' => $kayitAcanlar->map(fn (object $satir): array => [
                        'personel' => (string) $satir->personel,
                        'kayit' => (string) (int) $satir->kayit_sayisi,
                        'toplam' => $this->para((float) $satir->toplam_tutar, (string) ($satir->para_birimi ?: 'TRY')),
                    ])->all(),
                    'bos' => 'Bu dönemde kullanıcı bazlı kayıt yok.',
                ],
                [
                    'baslik' => 'Teslim performansı',
                    'kolonlar' => [
                        ['key' => 'personel', 'label' => 'Kullanıcı'],
                        ['key' => 'teslim', 'label' => 'Teslim', 'align' => 'right'],
                        ['key' => 'toplam', 'label' => 'Toplam', 'align' => 'right'],
                    ],
                    'satirlar' => $teslimEdenler->map(fn (object $satir): array => [
                        'personel' => (string) $satir->personel.' ('.strtoupper((string) ($satir->para_birimi ?: 'TRY')).')',
                        'teslim' => (string) (int) $satir->teslim_sayisi,
                        'toplam' => $this->para((float) $satir->toplam_tutar, (string) ($satir->para_birimi ?: 'TRY')),
                    ])->all(),
                    'bos' => 'Bu dönemde teslim kaydı yok.',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function durumBazli(int $firmaId, Carbon $baslangic, Carbon $bitis): array
    {
        $durumlar = DB::table('teknik_servis_kayitlari as k')
            ->leftJoin('teknik_servis_tanim_servis_durumlari as d', 'd.id', '=', 'k.servis_durumu_id')
            ->where('k.firma_id', $firmaId)
            ->whereNull('k.deleted_at')
            ->whereBetween('k.kabul_tarihi', [$baslangic, $bitis])
            ->selectRaw("COALESCE(d.ad, 'Durumsuz') as durum")
            ->selectRaw('COALESCE(d.is_teslim_edildi, 0) as teslim')
            ->selectRaw('COALESCE(d.is_iptal, 0) as iptal')
            ->selectRaw('COALESCE(d.is_iade, 0) as iade')
            ->selectRaw("COALESCE(NULLIF(k.tahsilat_para_birimi, ''), 'TRY') as para_birimi")
            ->selectRaw('COUNT(*) as servis_sayisi')
            ->selectRaw('COALESCE(SUM(k.toplam_tutar), 0) as toplam_tutar')
            ->selectRaw('COALESCE(SUM(k.odenen_tutar), 0) as odenen_tutar')
            ->selectRaw('MIN(k.kabul_tarihi) as ilk_kabul')
            ->groupBy('k.servis_durumu_id', 'd.ad', 'd.is_teslim_edildi', 'd.is_iptal', 'd.is_iade', 'k.tahsilat_para_birimi')
            ->orderByDesc('servis_sayisi')
            ->get();

        $toplamServis = (int) $durumlar->sum('servis_sayisi');
        $teslim = (int) $durumlar->where('teslim', 1)->sum('servis_sayisi');
        $iptalIade = (int) $durumlar->filter(fn (object $satir): bool => (int) $satir->iptal === 1 || (int) $satir->iade === 1)->sum('servis_sayisi');
        $acik = max(0, $toplamServis - $teslim - $iptalIade);

        return [
            'kartlar' => [
                ['etiket' => 'Toplam servis', 'deger' => (string) $toplamServis, 'alt' => $this->tarihAraligi($baslangic, $bitis)],
                ['etiket' => 'Açık servis', 'deger' => (string) $acik, 'alt' => 'Teslim/iptal/iade hariç'],
                ['etiket' => 'Teslim edilen', 'deger' => (string) $teslim, 'alt' => 'Durum bayrağına göre'],
                ['etiket' => 'İptal / iade', 'deger' => (string) $iptalIade, 'alt' => 'Operasyon dışı kayıtlar'],
                ['etiket' => 'Toplam tutar', 'deger' => $this->paraDagilimi($durumlar->groupBy('para_birimi')->mapWithKeys(fn ($satirlar, $paraBirimi): array => [(string) $paraBirimi => (float) $satirlar->sum('toplam_tutar')])->all()), 'alt' => 'Para birimi bazında'],
            ],
            'tablolar' => [
                [
                    'baslik' => 'Durum dağılımı',
                    'kolonlar' => [
                        ['key' => 'durum', 'label' => 'Durum'],
                        ['key' => 'servis', 'label' => 'Servis', 'align' => 'right'],
                        ['key' => 'oran', 'label' => 'Oran', 'align' => 'right'],
                        ['key' => 'toplam', 'label' => 'Toplam', 'align' => 'right'],
                        ['key' => 'odenen', 'label' => 'Ödenen', 'align' => 'right'],
                    ],
                    'satirlar' => $durumlar->map(fn (object $satir): array => [
                        'durum' => (string) $satir->durum,
                        'servis' => (string) (int) $satir->servis_sayisi,
                        'oran' => $this->yuzde($toplamServis > 0 ? ((int) $satir->servis_sayisi / $toplamServis) * 100 : 0),
                'toplam' => $this->para((float) $satir->toplam_tutar, (string) ($satir->para_birimi ?: 'TRY')),
                'odenen' => $this->para((float) $satir->odenen_tutar, (string) ($satir->para_birimi ?: 'TRY')),
                    ])->all(),
                    'bos' => 'Bu dönemde durum raporu için kayıt yok.',
                ],
                $this->dagilimTablosu($firmaId, $baslangic, $bitis, 'servis_tipi', 'Servis tipi dağılımı', 'Servis tipi'),
                $this->dagilimTablosu($firmaId, $baslangic, $bitis, 'oncelik', 'Öncelik dağılımı', 'Öncelik'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function garantiBakim(int $firmaId, Carbon $baslangic, Carbon $bitis): array
    {
        $bugun = now()->startOfDay();

        $garantiBiten = DB::table('teknik_servis_kayitlari')
            ->where('firma_id', $firmaId)
            ->whereNull('deleted_at')
            ->whereNotNull('garanti_bitis_tarihi')
            ->whereBetween('garanti_bitis_tarihi', [$baslangic->toDateString(), $bitis->toDateString()])
            ->count();

        $bakimGelen = DB::table('teknik_servis_kayitlari')
            ->where('firma_id', $firmaId)
            ->whereNull('deleted_at')
            ->whereNotNull('bakim_tarihi')
            ->whereBetween('bakim_tarihi', [$baslangic->toDateString(), $bitis->toDateString()])
            ->count();

        $aktifGaranti = DB::table('teknik_servis_kayitlari')
            ->where('firma_id', $firmaId)
            ->whereNull('deleted_at')
            ->whereDate('garanti_bitis_tarihi', '>=', $bugun->toDateString())
            ->count();

        $gecmisGaranti = DB::table('teknik_servis_kayitlari')
            ->where('firma_id', $firmaId)
            ->whereNull('deleted_at')
            ->whereDate('garanti_bitis_tarihi', '<', $bugun->toDateString())
            ->count();

        return [
            'kartlar' => [
                ['etiket' => 'Garanti bitişi', 'deger' => (string) $garantiBiten, 'alt' => $this->tarihAraligi($baslangic, $bitis)],
                ['etiket' => 'Bakımı gelen', 'deger' => (string) $bakimGelen, 'alt' => 'Bakım tarihi dönem içinde'],
                ['etiket' => 'Aktif garanti', 'deger' => (string) $aktifGaranti, 'alt' => 'Bugün ve sonrası'],
                ['etiket' => 'Garanti süresi dolan', 'deger' => (string) $gecmisGaranti, 'alt' => 'Bugünden önce biten'],
            ],
            'tablolar' => [
                [
                    'baslik' => 'Garanti bitiş takibi',
                    'kolonlar' => [
                        ['key' => 'fis', 'label' => 'Fiş no'],
                        ['key' => 'musteri', 'label' => 'Müşteri'],
                        ['key' => 'cihaz', 'label' => 'Cihaz'],
                        ['key' => 'garanti', 'label' => 'Garanti bitiş'],
                        ['key' => 'kalan', 'label' => 'Kalan gün', 'align' => 'right'],
                    ],
                    'satirlar' => $this->garantiBakimSatirlari($firmaId, $baslangic, $bitis, 'garanti_bitis_tarihi'),
                    'bos' => 'Bu aralıkta garanti bitişi yok.',
                ],
                [
                    'baslik' => 'Bakım planı',
                    'kolonlar' => [
                        ['key' => 'fis', 'label' => 'Fiş no'],
                        ['key' => 'musteri', 'label' => 'Müşteri'],
                        ['key' => 'cihaz', 'label' => 'Cihaz'],
                        ['key' => 'bakim', 'label' => 'Bakım tarihi'],
                        ['key' => 'periyot', 'label' => 'Periyot'],
                    ],
                    'satirlar' => $this->garantiBakimSatirlari($firmaId, $baslangic, $bitis, 'bakim_tarihi'),
                    'bos' => 'Bu aralıkta bakım planı yok.',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function tahsilatServis(int $firmaId, Carbon $baslangic, Carbon $bitis): array
    {
        $tahsilatlar = $this->aktifTahsilatSorgusu($firmaId)
            ->whereBetween('t.tarih', [$baslangic, $bitis]);

        $tahsilatSayisi = (clone $tahsilatlar)->count();
        $tahsilatOzetleri = (clone $tahsilatlar)
            ->selectRaw("COALESCE(NULLIF(t.kaynak_para_birimi, ''), 'TRY') as para_birimi")
            ->selectRaw('COALESCE(SUM(t.tutar), 0) as toplam_tutar')
            ->groupBy('t.kaynak_para_birimi')
            ->get();
        $servisSayisi = DB::table('teknik_servis_kayitlari')
            ->where('firma_id', $firmaId)
            ->whereNull('deleted_at')
            ->whereBetween('kabul_tarihi', [$baslangic, $bitis])
            ->count();
        $acikBakiyeOzetleri = DB::table('teknik_servis_kayitlari')
            ->where('firma_id', $firmaId)
            ->whereNull('deleted_at')
            ->whereBetween('kabul_tarihi', [$baslangic, $bitis])
            ->selectRaw("COALESCE(NULLIF(tahsilat_para_birimi, ''), 'TRY') as para_birimi")
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(toplam_tutar, 0) > COALESCE(odenen_tutar, 0) THEN COALESCE(toplam_tutar, 0) - COALESCE(odenen_tutar, 0) ELSE 0 END), 0) as toplam_tutar')
            ->groupBy('tahsilat_para_birimi')
            ->get();

        return [
            'kartlar' => [
                ['etiket' => 'Servis adedi', 'deger' => (string) $servisSayisi, 'alt' => $this->tarihAraligi($baslangic, $bitis)],
                ['etiket' => 'Tahsilat işlemi', 'deger' => (string) $tahsilatSayisi, 'alt' => 'Aktif tahsilatlar'],
                ['etiket' => 'Tahsilat toplamı', 'deger' => $this->paraDagilimi($tahsilatOzetleri->mapWithKeys(fn (object $satir): array => [(string) $satir->para_birimi => (float) $satir->toplam_tutar])->all()), 'alt' => 'Kaynak para birimi bazında'],
                ['etiket' => 'Açık bakiye', 'deger' => $this->paraDagilimi($acikBakiyeOzetleri->mapWithKeys(fn (object $satir): array => [(string) $satir->para_birimi => (float) $satir->toplam_tutar])->all()), 'alt' => 'Dönem servisleri'],
            ],
            'tablolar' => [
                [
                    'baslik' => 'Tahsilat kanalları',
                    'kolonlar' => [
                        ['key' => 'kanal', 'label' => 'Kanal'],
                        ['key' => 'islem', 'label' => 'İşlem', 'align' => 'right'],
                        ['key' => 'tutar', 'label' => 'Tutar', 'align' => 'right'],
                    ],
                    'satirlar' => $this->tahsilatKanalSatirlari($firmaId, $baslangic, $bitis),
                    'bos' => 'Bu dönemde tahsilat yok.',
                ],
                $this->odemeDurumuTablosu($firmaId, $baslangic, $bitis),
                [
                    'baslik' => 'Son tahsilatlar',
                    'kolonlar' => [
                        ['key' => 'tarih', 'label' => 'Tarih'],
                        ['key' => 'fis', 'label' => 'Fiş no'],
                        ['key' => 'musteri', 'label' => 'Müşteri'],
                        ['key' => 'kanal', 'label' => 'Kanal'],
                        ['key' => 'tutar', 'label' => 'Tutar', 'align' => 'right'],
                    ],
                    'satirlar' => $this->sonTahsilatlar($firmaId, $baslangic, $bitis),
                    'bos' => 'Bu dönemde tahsilat detayı yok.',
                ],
            ],
        ];
    }

    private function aktifTahsilatSorgusu(int $firmaId): \Illuminate\Database\Query\Builder
    {
        return DB::table('teknik_servis_tahsilatlari as t')
            ->where('t.firma_id', $firmaId)
            ->whereNull('t.deleted_at')
            ->where('t.durum', 'aktif');
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function servisTipiKarlilikSatirlari(int $firmaId, Carbon $baslangic, Carbon $bitis): array
    {
        $kayitlar = DB::table('teknik_servis_kayitlari as k')
            ->where('k.firma_id', $firmaId)
            ->whereNull('k.deleted_at')
            ->whereBetween('k.kabul_tarihi', [$baslangic, $bitis])
            ->selectRaw('k.servis_tipi')
            ->selectRaw("COALESCE(NULLIF(k.tahsilat_para_birimi, ''), 'TRY') as para_birimi")
            ->selectRaw('COUNT(*) as servis_sayisi')
            ->selectRaw('COALESCE(SUM(k.toplam_tutar), 0) as kayit_toplami')
            ->groupBy('k.servis_tipi', 'k.tahsilat_para_birimi')
            ->orderByDesc('servis_sayisi')
            ->get()
            ->keyBy('servis_tipi');

        $kalemler = DB::table('teknik_servis_kalemleri as kalem')
            ->join('teknik_servis_kayitlari as k', 'k.id', '=', 'kalem.teknik_servis_kaydi_id')
            ->where('kalem.firma_id', $firmaId)
            ->whereNull('kalem.deleted_at')
            ->whereNull('k.deleted_at')
            ->whereBetween('k.kabul_tarihi', [$baslangic, $bitis])
            ->selectRaw('k.servis_tipi')
            ->selectRaw("COALESCE(NULLIF(kalem.para_birimi, ''), COALESCE(NULLIF(k.tahsilat_para_birimi, ''), 'TRY')) as para_birimi")
            ->selectRaw("COALESCE(SUM(CASE WHEN kalem.kalem_rolu = 'satis' THEN kalem.satir_toplami ELSE 0 END), 0) as gelir")
            ->selectRaw("COALESCE(SUM(CASE WHEN kalem.kalem_rolu = 'gider' THEN kalem.satir_toplami ELSE 0 END), 0) as gider")
            ->groupBy('k.servis_tipi', 'kalem.para_birimi', 'k.tahsilat_para_birimi')
            ->get()
            ->keyBy(fn (object $satir): string => (string) $satir->servis_tipi.'|'.(string) $satir->para_birimi);

        return $kayitlar
            ->map(function (object $satir) use ($kalemler): array {
                $paraBirimi = strtoupper((string) ($satir->para_birimi ?: 'TRY'));
                $kalem = $kalemler->get((string) $satir->servis_tipi.'|'.$paraBirimi);
                $gelir = max((float) ($kalem?->gelir ?? 0), (float) $satir->kayit_toplami);
                $gider = (float) ($kalem?->gider ?? 0);

                return [
                    'tip' => $this->servisTipi((string) $satir->servis_tipi).' ('.$paraBirimi.')',
                    'servis' => (string) (int) $satir->servis_sayisi,
                    'gelir' => $this->para($gelir, $paraBirimi),
                    'gider' => $this->para($gider, $paraBirimi),
                    'kar' => $this->para($gelir - $gider, $paraBirimi),
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function enYuksekTutarliServisler(int $firmaId, Carbon $baslangic, Carbon $bitis): array
    {
        return DB::table('teknik_servis_kayitlari as k')
            ->leftJoin('cariler as c', function ($join): void {
                $join->on('c.id', '=', 'k.cari_id')->whereNull('c.deleted_at');
            })
            ->leftJoin('teknik_servis_tanim_servis_durumlari as d', 'd.id', '=', 'k.servis_durumu_id')
            ->where('k.firma_id', $firmaId)
            ->whereNull('k.deleted_at')
            ->whereBetween('k.kabul_tarihi', [$baslangic, $bitis])
            ->orderByDesc('k.toplam_tutar')
            ->limit(12)
            ->get(['k.id', 'k.fis_no', 'k.musteri_ad_soyad', 'k.toplam_tutar', 'k.odenen_tutar', 'k.tahsilat_para_birimi', 'c.ad as cari_adi', 'd.ad as durum_adi'])
            ->map(fn (object $satir): array => [
                '_url' => TeknikServisKaydiKaynagi::getUrl('edit', ['record' => (int) $satir->id]),
                'fis' => (string) ($satir->fis_no ?: '-'),
                'musteri' => (string) (($satir->cari_adi ?? null) ?: ($satir->musteri_ad_soyad ?? null) ?: '-'),
                'durum' => (string) ($satir->durum_adi ?: '-'),
                'toplam' => $this->para((float) $satir->toplam_tutar, (string) ($satir->tahsilat_para_birimi ?: 'TRY')),
                'tahsilat' => $this->para((float) $satir->odenen_tutar, (string) ($satir->tahsilat_para_birimi ?: 'TRY')),
            ])
            ->all();
    }

    /**
     * @return array{baslik:string,kolonlar:array<int,array<string,string>>,satirlar:array<int,array<string,string>>,bos:string}
     */
    private function dagilimTablosu(int $firmaId, Carbon $baslangic, Carbon $bitis, string $kolon, string $baslik, string $etiket): array
    {
        $satirlar = DB::table('teknik_servis_kayitlari')
            ->where('firma_id', $firmaId)
            ->whereNull('deleted_at')
            ->whereBetween('kabul_tarihi', [$baslangic, $bitis])
            ->selectRaw($kolon.' as grup')
            ->selectRaw("COALESCE(NULLIF(tahsilat_para_birimi, ''), 'TRY') as para_birimi")
            ->selectRaw('COUNT(*) as servis_sayisi')
            ->selectRaw('COALESCE(SUM(toplam_tutar), 0) as toplam_tutar')
            ->groupBy($kolon, 'tahsilat_para_birimi')
            ->orderByDesc('servis_sayisi')
            ->get();

        return [
            'baslik' => $baslik,
            'kolonlar' => [
                ['key' => 'grup', 'label' => $etiket],
                ['key' => 'servis', 'label' => 'Servis', 'align' => 'right'],
                ['key' => 'toplam', 'label' => 'Toplam', 'align' => 'right'],
            ],
            'satirlar' => $satirlar->map(fn (object $satir): array => [
                'grup' => $kolon === 'servis_tipi' ? $this->servisTipi((string) $satir->grup) : $this->etiket((string) $satir->grup),
                'servis' => (string) (int) $satir->servis_sayisi,
                'toplam' => $this->para((float) $satir->toplam_tutar, (string) ($satir->para_birimi ?: 'TRY')),
            ])->all(),
            'bos' => $baslik.' için kayıt yok.',
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function garantiBakimSatirlari(int $firmaId, Carbon $baslangic, Carbon $bitis, string $tarihKolonu): array
    {
        return DB::table('teknik_servis_kayitlari as k')
            ->leftJoin('cariler as c', function ($join): void {
                $join->on('c.id', '=', 'k.cari_id')->whereNull('c.deleted_at');
            })
            ->leftJoin('teknik_servis_tanim_cihazlar as cihaz', 'cihaz.id', '=', 'k.cihaz_id')
            ->leftJoin('teknik_servis_tanim_markalar as marka', 'marka.id', '=', 'k.marka_id')
            ->where('k.firma_id', $firmaId)
            ->whereNull('k.deleted_at')
            ->whereNotNull('k.'.$tarihKolonu)
            ->whereBetween('k.'.$tarihKolonu, [$baslangic->toDateString(), $bitis->toDateString()])
            ->orderBy('k.'.$tarihKolonu)
            ->limit(20)
            ->get([
                'k.id',
                'k.fis_no',
                'k.musteri_ad_soyad',
                'k.'.$tarihKolonu.' as takip_tarihi',
                'k.bakim_periyot_ay',
                'c.ad as cari_adi',
                'cihaz.ad as cihaz_adi',
                'marka.ad as marka_adi',
            ])
            ->map(function (object $satir) use ($tarihKolonu): array {
                $takip = $satir->takip_tarihi ? Carbon::parse((string) $satir->takip_tarihi) : null;
                $cihaz = trim((string) (($satir->marka_adi ? $satir->marka_adi.' ' : '').($satir->cihaz_adi ?? '')));

                return [
                    '_url' => TeknikServisKaydiKaynagi::getUrl('edit', ['record' => (int) $satir->id]),
                    'fis' => (string) ($satir->fis_no ?: '-'),
                    'musteri' => (string) (($satir->cari_adi ?? null) ?: ($satir->musteri_ad_soyad ?? null) ?: '-'),
                    'cihaz' => $cihaz !== '' ? $cihaz : '-',
                    'garanti' => $tarihKolonu === 'garanti_bitis_tarihi' ? $this->tarih($takip) : '',
                    'bakim' => $tarihKolonu === 'bakim_tarihi' ? $this->tarih($takip) : '',
                    'kalan' => $takip ? (string) now()->startOfDay()->diffInDays($takip->copy()->startOfDay(), false) : '-',
                    'periyot' => $satir->bakim_periyot_ay ? ((int) $satir->bakim_periyot_ay).' ay' : '-',
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function tahsilatKanalSatirlari(int $firmaId, Carbon $baslangic, Carbon $bitis): array
    {
        return $this->aktifTahsilatSorgusu($firmaId)
            ->whereBetween('t.tarih', [$baslangic, $bitis])
            ->selectRaw('t.kanal')
            ->selectRaw("COALESCE(NULLIF(t.kaynak_para_birimi, ''), 'TRY') as para_birimi")
            ->selectRaw('COUNT(*) as islem_sayisi')
            ->selectRaw('COALESCE(SUM(t.tutar), 0) as toplam_tutar')
            ->groupBy('t.kanal', 't.kaynak_para_birimi')
            ->orderByDesc('toplam_tutar')
            ->get()
            ->map(fn (object $satir): array => [
                'kanal' => $this->kanal((string) $satir->kanal),
                'islem' => (string) (int) $satir->islem_sayisi,
                'tutar' => $this->para((float) $satir->toplam_tutar, (string) ($satir->para_birimi ?: 'TRY')),
            ])
            ->all();
    }

    /**
     * @return array{baslik:string,kolonlar:array<int,array<string,string>>,satirlar:array<int,array<string,string>>,bos:string}
     */
    private function odemeDurumuTablosu(int $firmaId, Carbon $baslangic, Carbon $bitis): array
    {
        $satirlar = DB::table('teknik_servis_kayitlari')
            ->where('firma_id', $firmaId)
            ->whereNull('deleted_at')
            ->whereBetween('kabul_tarihi', [$baslangic, $bitis])
            ->selectRaw('odeme_durumu')
            ->selectRaw("COALESCE(NULLIF(tahsilat_para_birimi, ''), 'TRY') as para_birimi")
            ->selectRaw('COUNT(*) as servis_sayisi')
            ->selectRaw('COALESCE(SUM(toplam_tutar), 0) as toplam_tutar')
            ->selectRaw('COALESCE(SUM(odenen_tutar), 0) as odenen_tutar')
            ->groupBy('odeme_durumu', 'tahsilat_para_birimi')
            ->orderByDesc('servis_sayisi')
            ->get();

        return [
            'baslik' => 'Ödeme durumu',
            'kolonlar' => [
                ['key' => 'durum', 'label' => 'Durum'],
                ['key' => 'servis', 'label' => 'Servis', 'align' => 'right'],
                ['key' => 'toplam', 'label' => 'Toplam', 'align' => 'right'],
                ['key' => 'odenen', 'label' => 'Ödenen', 'align' => 'right'],
                ['key' => 'kalan', 'label' => 'Kalan', 'align' => 'right'],
            ],
            'satirlar' => $satirlar->map(fn (object $satir): array => [
                'durum' => $this->etiket((string) $satir->odeme_durumu),
                'servis' => (string) (int) $satir->servis_sayisi,
                'toplam' => $this->para((float) $satir->toplam_tutar, (string) ($satir->para_birimi ?: 'TRY')),
                'odenen' => $this->para((float) $satir->odenen_tutar, (string) ($satir->para_birimi ?: 'TRY')),
                'kalan' => $this->para(max(0, (float) $satir->toplam_tutar - (float) $satir->odenen_tutar), (string) ($satir->para_birimi ?: 'TRY')),
            ])->all(),
            'bos' => 'Bu dönemde ödeme durumu verisi yok.',
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function sonTahsilatlar(int $firmaId, Carbon $baslangic, Carbon $bitis): array
    {
        return $this->aktifTahsilatSorgusu($firmaId)
            ->join('teknik_servis_kayitlari as k', 'k.id', '=', 't.teknik_servis_kaydi_id')
            ->leftJoin('cariler as c', function ($join): void {
                $join->on('c.id', '=', 'k.cari_id')->whereNull('c.deleted_at');
            })
            ->whereNull('k.deleted_at')
            ->whereBetween('t.tarih', [$baslangic, $bitis])
            ->orderByDesc('t.tarih')
            ->limit(20)
            ->get(['k.id', 'k.fis_no', 'k.musteri_ad_soyad', 'c.ad as cari_adi', 't.tarih', 't.kanal', 't.tutar', 't.hedef_tutar', 't.kaynak_para_birimi'])
            ->map(fn (object $satir): array => [
                '_url' => TeknikServisKaydiKaynagi::getUrl('edit', ['record' => (int) $satir->id]),
                'tarih' => $this->tarihSaat($satir->tarih),
                'fis' => (string) ($satir->fis_no ?: '-'),
                'musteri' => (string) (($satir->cari_adi ?? null) ?: ($satir->musteri_ad_soyad ?? null) ?: '-'),
                'kanal' => $this->kanal((string) $satir->kanal),
                'tutar' => $this->para((float) ($satir->hedef_tutar ?? $satir->tutar ?? 0), (string) ($satir->kaynak_para_birimi ?: 'TRY')),
            ])
            ->all();
    }

    private function para(float $deger, string $paraBirimi = 'TRY'): string
    {
        return number_format($deger, 2, ',', '.').' '.strtoupper($paraBirimi ?: 'TRY');
    }

    /** @param array<string,float|int> $dagilim */
    private function paraDagilimi(array $dagilim): string
    {
        return collect($dagilim)
            ->filter(fn ($tutar): bool => abs((float) $tutar) > 0.000001)
            ->map(fn ($tutar, $paraBirimi): string => $this->para((float) $tutar, (string) $paraBirimi))
            ->implode(' · ') ?: $this->para(0);
    }

    private function yuzde(float $deger): string
    {
        return number_format($deger, 2, ',', '.').'%';
    }

    private function tarih(Carbon|string|null $deger): string
    {
        return $deger ? Carbon::parse($deger)->format('d.m.Y') : '-';
    }

    private function tarihSaat(mixed $deger): string
    {
        return $deger ? Carbon::parse((string) $deger)->format('d.m.Y H:i') : '-';
    }

    private function tarihAraligi(Carbon $baslangic, Carbon $bitis): string
    {
        return $baslangic->format('d.m.Y').' - '.$bitis->format('d.m.Y');
    }

    private function servisTipi(string $deger): string
    {
        return match ($deger) {
            'arizali_cihaz' => 'Arızalı cihaz',
            'dis_servis' => 'Dış servis',
            'bakim' => 'Bakım',
            default => $this->etiket($deger),
        };
    }

    private function kanal(string $deger): string
    {
        return match ($deger) {
            'nakit', 'kasa' => 'Nakit / kasa',
            'banka', 'havale', 'eft' => 'Banka',
            'pos', 'kart' => 'POS / kart',
            default => $this->etiket($deger),
        };
    }

    private function etiket(string $deger): string
    {
        $deger = trim($deger);
        if ($deger === '') {
            return '-';
        }

        return str($deger)->replace('_', ' ')->title()->toString();
    }
}
