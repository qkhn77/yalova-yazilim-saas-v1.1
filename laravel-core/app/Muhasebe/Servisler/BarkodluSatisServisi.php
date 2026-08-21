<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\BarkodluSatis;
use App\Models\Muhasebe\BarkodluSatisIade;
use App\Models\Muhasebe\BarkodluSatisKalemi;
use App\Models\Muhasebe\BankaHareketi;
use App\Models\Muhasebe\BankaHesabi;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\Muhasebe\KasaHareketi;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\PosHareketi;
use App\Models\Muhasebe\PosHesabi;
use App\Models\Muhasebe\StokHareketi;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\Depo;
use App\Muhasebe\Enumlar\FinansHareketDurumu;
use App\Muhasebe\Enumlar\FinansHareketTuru;
use App\Muhasebe\Enumlar\HareketDurumu;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\StokBelgeTuru;
use App\Muhasebe\Enumlar\StokHareketDurumu;
use App\Muhasebe\Enumlar\StokHareketIslemTuru;
use App\Muhasebe\Yardimcilar\FinansAuditBaglami;
use App\Services\BarkodluSatisTelegramBildirimServisi;
use App\Services\FirmaAyarDeposu;
use App\Services\SistemOlayServisi;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class BarkodluSatisServisi
{
    private const PERAKENDE_CARI_KOD = 'PERAKENDE-MUSTERI';

    private const VARSAYILAN_PERAKENDE_CARI_ADI = 'Perakende Musteri';

    public function __construct(
        private readonly SistemOlayServisi $sistemOlayServisi,
        private readonly FirmaAyarDeposu $firmaAyarDeposu,
    ) {}

    /**
     * @param array{
     *   satis_tarihi:string,
     *   cari_id?:int|null,
     *   odeme_tipi:string,
     *   para_birimi:string,
     *   not?:string|null,
     *   kalemler:array<int,array{
     *      stok_id:int,
     *      barkod?:string|null,
     *      stok_adi:string,
     *      birim:string,
     *      miktar:float|int|string,
     *      birim_fiyat:float|int|string,
     *      iskonto_tutari?:float|int|string,
     *      kdv_orani?:float|int|string
     *   }>
     * } $veri
     */
    public function satisTamamla(int $firmaId, int $kullaniciId, array $veri): BarkodluSatis
    {
        try {
            $satis = DB::transaction(function () use ($firmaId, $kullaniciId, $veri): BarkodluSatis {
                $kalemler = array_values(array_filter((array) ($veri['kalemler'] ?? []), fn ($k): bool => is_array($k)));
                if (count($kalemler) === 0) {
                    throw new InvalidArgumentException('Satis icin en az bir kalem gereklidir.');
                }

                $gecerliKalemler = array_values(array_filter(
                    $kalemler,
                    fn (array $kalem): bool => (float) ($kalem['miktar'] ?? 0) > 0
                ));
                if (count($gecerliKalemler) === 0) {
                    throw new InvalidArgumentException('Satis icin en az bir gecerli kalem gereklidir.');
                }

                $odemeTipi = strtolower(trim((string) ($veri['odeme_tipi'] ?? 'nakit')));
                if (in_array($odemeTipi, ['veresiye', 'taksitli'], true) && (int) ($veri['cari_id'] ?? 0) < 1) {
                    throw new InvalidArgumentException('Veresiye veya taksitli satis icin cari secimi zorunludur.');
                }

                $eksiStokIzinli = (bool) ($veri['eksi_stok_izinli'] ?? false);
                $stoklar = $this->stokHaritasi($firmaId, $gecerliKalemler, true);
                $this->stokMusaitliginiTopluDogrula($gecerliKalemler, $stoklar, $eksiStokIzinli);
                $ozet = $this->ozetHesapla($gecerliKalemler);
                $cariId = $this->satisCariIdBelirle($firmaId, $veri);

                $satis = BarkodluSatis::query()->create([
                    'firma_id' => $firmaId,
                    'satis_no' => $this->sonrakiSatisNo($firmaId),
                    'satis_tarihi' => Carbon::parse((string) ($veri['satis_tarihi'] ?? now()->toDateTimeString()))->toDateTimeString(),
                    'cari_id' => $cariId,
                    'odeme_tipi' => (string) ($veri['odeme_tipi'] ?? 'nakit'),
                    'para_birimi' => strtoupper((string) ($veri['para_birimi'] ?? 'TRY')),
                    'ara_toplam' => $ozet['ara_toplam'],
                    'iskonto_toplami' => $ozet['iskonto_toplami'],
                    'kdv_toplami' => $ozet['kdv_toplami'],
                    'genel_toplam' => $ozet['genel_toplam'],
                    'durum' => 'tamamlandi',
                    'not' => filled($veri['not'] ?? null) ? (string) $veri['not'] : null,
                    'olusturan_id' => $kullaniciId,
                ]);

                foreach ($gecerliKalemler as $kalem) {
                    $miktar = (float) ($kalem['miktar'] ?? 0);

                    $stokId = (int) ($kalem['stok_id'] ?? 0);
                    /** @var StokKarti|null $stok */
                    $stok = $stoklar[$stokId] ?? null;
                    if (! $stok) {
                        throw new InvalidArgumentException('Gecersiz stok kalemi tespit edildi.');
                    }

                    if (! $eksiStokIzinli && (bool) $stok->stok_takip && $stok->musaitStokMiktari() < $miktar) {
                        throw new InvalidArgumentException('Yetersiz stok: '.$stok->ad);
                    }

                    $birimFiyat = (float) ($kalem['birim_fiyat'] ?? 0);
                    $iskonto = (float) ($kalem['iskonto_tutari'] ?? 0);
                    $kdvOrani = (float) ($kalem['kdv_orani'] ?? 0);
                    $satirAraToplam = max(0, ($miktar * $birimFiyat) - $iskonto);
                    $kdvTutari = round($satirAraToplam * ($kdvOrani / 100), 2);
                    $satirToplami = round($satirAraToplam + $kdvTutari, 2);
                    $depoId = $this->stokDepoId($firmaId, $stok);

                    $satisKalemi = $satis->kalemler()->create([
                        'firma_id' => $firmaId,
                        'stok_id' => $stokId,
                        'depo_id' => $depoId,
                        'barkod' => (string) ($stok->barkod ?: $stok->kod ?: ''),
                        'seri_nolari' => array_values(array_filter(array_map('trim', (array) ($kalem['seri_nolari'] ?? [])))),
                        'stok_adi' => (string) $stok->ad,
                        'birim' => (string) ($stok->birim ?: 'AD'),
                        'miktar' => $miktar,
                        'birim_fiyat' => $birimFiyat,
                        'iskonto_tutari' => $iskonto,
                        'kdv_orani' => $kdvOrani,
                        'kdv_tutari' => $kdvTutari,
                        'satir_toplami' => $satirToplami,
                    ]);

                    $stokHareketi = app(StokHareketServisi::class)->kayitOlustur($firmaId, [
                        'stok_id' => (int) $stok->getKey(),
                        'depo_id' => (int) ($depoId ?? 0),
                        'cari_id' => $satis->cari_id,
                        'islem_turu' => StokHareketIslemTuru::Satis,
                        'miktar' => $miktar,
                        'birim_fiyat' => $birimFiyat,
                        'toplam' => $satirToplami,
                        'belge_turu' => StokBelgeTuru::Duzeltme,
                        'belge_id' => (int) $satis->getKey(),
                        'aciklama' => 'Barkodlu satis #'.$satis->satis_no,
                        'negatif_stok_izinli' => $eksiStokIzinli,
                        'tarih' => $veri['satis_tarihi'],
                        'seri_nolari' => array_values(array_filter(array_map('trim', (array) ($kalem['seri_nolari'] ?? [])))),
                        'parca_dagilimi' => array_values((array) ($kalem['parca_dagilimi'] ?? [])),
                    ]);

                    $stok->increment('satis_adedi', $miktar);
                }

                app(AlacakPlanServisi::class)->barkodluSatisIcinOlustur($satis, $veri);

                $odemeTipi = strtolower(trim((string) ($satis->odeme_tipi ?? '')));
                $vadeliMi = in_array($odemeTipi, ['veresiye', 'taksitli'], true);
                $pesinatVarMi = (float) ($veri['pesinat_tutari'] ?? 0) > 0;
                if (! $vadeliMi || $pesinatVarMi) {
                    if (! $this->satisTahsilatiniKaydet($satis, $veri)) {
                        throw new InvalidArgumentException(
                            'Satış tamamlanamadı: finans hareketi oluşturulamadı. Kasa, banka veya POS hesabını kontrol edin.'
                        );
                    }
                }

                return $satis;
            });

            $this->olayLogla(
                'barkodlu_satis.satis_olusturuldu',
                'info',
                'Barkodlu satis olusturuldu.',
                [
                    'firma_id' => $firmaId,
                    'kullanici_id' => $kullaniciId,
                    'satis_id' => (int) $satis->id,
                    'satis_no' => (string) $satis->satis_no,
                    'kalem_sayisi' => (int) $satis->kalemler()->count(),
                    'genel_toplam' => (float) $satis->genel_toplam,
                    'tahsilat_kaydi' => FinansHareketi::query()
                        ->where('firma_id', (int) $satis->firma_id)
                        ->where('referans_turu', 'barkodlu_satis')
                        ->where('referans_id', (int) $satis->id)
                        ->where('durum', FinansHareketDurumu::Aktif->value)
                        ->exists(),
                ]
            );
            $this->metrikArtir('muhasebe:metrics:barkodlu_satis:olusturuldu');
            app(BarkodluSatisTelegramBildirimServisi::class)->satisTamamlandi($satis);

            return $satis;
        } catch (Throwable $e) {
            $this->hataLogla(
                'barkodlu_satis.satis_hatasi',
                'Barkodlu satis olusturma hatasi.',
                [
                    'firma_id' => $firmaId,
                    'kullanici_id' => $kullaniciId,
                ],
                $e
            );
            $this->metrikArtir('muhasebe:metrics:barkodlu_satis:hata');

            throw $e;
        }
    }

    /**
     * @param array<int,array<string,mixed>> $kalemler
     * @return array{ara_toplam:float,iskonto_toplami:float,kdv_toplami:float,genel_toplam:float}
     */
    public function ozetHesapla(array $kalemler): array
    {
        $araToplam = 0.0;
        $iskontoToplami = 0.0;
        $kdvToplami = 0.0;
        $genelToplam = 0.0;

        foreach ($kalemler as $kalem) {
            $miktar = (float) ($kalem['miktar'] ?? 0);
            $birimFiyat = (float) ($kalem['birim_fiyat'] ?? 0);
            $iskonto = max(0, (float) ($kalem['iskonto_tutari'] ?? 0));
            $kdvOrani = max(0, (float) ($kalem['kdv_orani'] ?? 0));

            if ($miktar <= 0) {
                continue;
            }

            $brut = $miktar * $birimFiyat;
            $net = max(0, $brut - $iskonto);
            $kdv = round($net * ($kdvOrani / 100), 2);

            $araToplam += $net;
            $iskontoToplami += $iskonto;
            $kdvToplami += $kdv;
            $genelToplam += ($net + $kdv);
        }

        return [
            'ara_toplam' => round($araToplam, 2),
            'iskonto_toplami' => round($iskontoToplami, 2),
            'kdv_toplami' => round($kdvToplami, 2),
            'genel_toplam' => round($genelToplam, 2),
        ];
    }

    public function satisIptalEt(int $firmaId, int $satisId, int $kullaniciId, ?string $iptalNedeni = null): BarkodluSatis
    {
        try {
            $satis = DB::transaction(function () use ($firmaId, $satisId, $kullaniciId, $iptalNedeni): BarkodluSatis {
                /** @var BarkodluSatis|null $satis */
                $satis = BarkodluSatis::query()
                    ->where('firma_id', $firmaId)
                    ->whereKey($satisId)
                    ->lockForUpdate()
                    ->first();

                if (! $satis) {
                    throw new InvalidArgumentException('Satis kaydi bulunamadi.');
                }

                if ((string) ($satis->durum ?? 'tamamlandi') === 'iptal') {
                    throw new InvalidArgumentException('Bu satis daha once iptal edilmis.');
                }

                app(AlacakPlanServisi::class)->kaynakPlaniniIptalEt(
                    $firmaId,
                    'barkodlu_satis',
                    $satisId,
                    'Barkodlu satis iptal #'.$satis->satis_no
                );

                $hareketler = StokHareketi::query()
                    ->where('firma_id', $firmaId)
                    ->where('belge_turu', StokBelgeTuru::Duzeltme->value)
                    ->where('belge_id', $satisId)
                    ->where('islem_turu', StokHareketIslemTuru::Satis->value)
                    ->where('durum', StokHareketDurumu::Aktif->value)
                    ->orderByDesc('id')
                    ->get();

                foreach ($hareketler as $hareket) {
                    app(StokHareketServisi::class)->tersKayitOlustur(
                        hareket: $hareket,
                        aciklama: 'Barkodlu satis iptal #'.$satis->satis_no
                    );
                }

                $this->satisTahsilatiniTersle($satis);

                $satis->update([
                    'durum' => 'iptal',
                    'iptal_tarihi' => now(),
                    'iptal_nedeni' => filled($iptalNedeni) ? trim((string) $iptalNedeni) : null,
                    'iptal_eden_id' => $kullaniciId,
                ]);

                return $satis->fresh() ?? $satis;
            });

            $this->olayLogla(
                'barkodlu_satis.satis_iptal_edildi',
                'warning',
                'Barkodlu satis iptal edildi.',
                [
                    'firma_id' => $firmaId,
                    'kullanici_id' => $kullaniciId,
                    'satis_id' => (int) $satisId,
                    'satis_no' => (string) $satis->satis_no,
                ]
            );
            $this->metrikArtir('muhasebe:metrics:barkodlu_satis:iptal');
            app(BarkodluSatisTelegramBildirimServisi::class)->satisIptalEdildi($satis);

            return $satis;
        } catch (Throwable $e) {
            $this->hataLogla(
                'barkodlu_satis.satis_iptal_hatasi',
                'Barkodlu satis iptal hatasi.',
                [
                    'firma_id' => $firmaId,
                    'kullanici_id' => $kullaniciId,
                    'satis_id' => $satisId,
                ],
                $e
            );
            $this->metrikArtir('muhasebe:metrics:barkodlu_satis:hata');

            throw $e;
        }
    }

    public function satisKalemiIadeEt(
        int $firmaId,
        int $satisId,
        int $satisKalemId,
        float $iadeMiktari,
        int $kullaniciId,
        ?string $neden = null,
        ?string $seriNoBarkodu = null
    ): BarkodluSatisIade {
        try {
            $sonuc = DB::transaction(function () use ($firmaId, $satisId, $satisKalemId, $iadeMiktari, $kullaniciId, $neden, $seriNoBarkodu): array {
                if ($iadeMiktari <= 0) {
                    throw new InvalidArgumentException('Iade miktari sifirdan buyuk olmalidir.');
                }

                /** @var BarkodluSatis|null $satis */
                $satis = BarkodluSatis::query()
                    ->where('firma_id', $firmaId)
                    ->whereKey($satisId)
                    ->lockForUpdate()
                    ->first();

                if (! $satis) {
                    throw new InvalidArgumentException('Satis kaydi bulunamadi.');
                }

                if ((string) ($satis->durum ?? '') === 'iptal') {
                    throw new InvalidArgumentException('Iptal edilen satis icin iade islemi yapilamaz.');
                }

                /** @var BarkodluSatisKalemi|null $kalem */
                $kalem = BarkodluSatisKalemi::query()
                    ->where('firma_id', $firmaId)
                    ->where('satis_id', $satisId)
                    ->whereKey($satisKalemId)
                    ->first();

                if (! $kalem) {
                    throw new InvalidArgumentException('Iade edilecek satis kalemi bulunamadi.');
                }

                $oncekiIadeler = DB::table('muhasebe_barkodlu_satis_iade_kalemleri')
                    ->where('firma_id', $firmaId)
                    ->where('satis_kalem_id', $satisKalemId)
                    ->sum('miktar');

                $maksIadeMiktari = max(0.0, (float) $kalem->miktar - (float) $oncekiIadeler);
                if ($iadeMiktari > $maksIadeMiktari + 0.0001) {
                    throw new InvalidArgumentException('Iade miktari kalemdeki kalan miktardan fazla olamaz.');
                }

                $stok = StokKarti::query()
                    ->where('firma_id', $firmaId)
                    ->whereKey((int) $kalem->stok_id)
                    ->first();
                if (! $stok) {
                    throw new InvalidArgumentException('Iade edilecek stok karti bulunamadi.');
                }

                $seriListesi = array_values(array_filter(array_map('trim', preg_split('/[\r\n,;]+/', (string) ($seriNoBarkodu ?? '')) ?: [])));
                $satisSerileri = array_values(array_filter(array_map('trim', (array) ($kalem->seri_nolari ?? []))));
                if ((string) ($stok->stok_takip_tipi ?? '') === StokKarti::STOK_TAKIP_TIPI_SERI && $satisSerileri !== []) {
                    if ($seriListesi === [] && abs($iadeMiktari - count($satisSerileri)) < 0.0001) {
                        $seriListesi = $satisSerileri;
                    }
                    if (count($seriListesi) !== (int) round($iadeMiktari)) {
                        throw new InvalidArgumentException('Seri No Barkodu adedi, iade miktari ile ayni olmalidir.');
                    }
                    if (count(array_unique($seriListesi)) !== count($seriListesi) || array_diff($seriListesi, $satisSerileri) !== []) {
                        throw new InvalidArgumentException('Iade edilen Seri No Barkodlari bu satis kaleminde bulunmuyor.');
                    }
                }

                $parcaDagilimi = $this->iadePartiDagilimi($kalem, $iadeMiktari);

                $net = max(0, ($iadeMiktari * (float) $kalem->birim_fiyat));
                $kdv = round($net * (((float) $kalem->kdv_orani) / 100), 2);
                $satirToplami = round($net + $kdv, 2);

                $iade = BarkodluSatisIade::query()->create([
                    'firma_id' => $firmaId,
                    'satis_id' => $satisId,
                    'iade_no' => $this->sonrakiIadeNo($firmaId),
                    'dogrulama_kodu' => $this->dogrulamaKoduUret($firmaId),
                    'iade_tarihi' => now()->toDateTimeString(),
                    'neden' => filled($neden) ? trim((string) $neden) : null,
                    'toplam_iade_tutari' => $satirToplami,
                    'olusturan_id' => $kullaniciId,
                ]);

                $iade->kalemler()->create([
                    'firma_id' => $firmaId,
                    'satis_kalem_id' => $satisKalemId,
                    'stok_id' => (int) $kalem->stok_id,
                    'parca_kodu' => count($parcaDagilimi) === 1 ? $parcaDagilimi[0]['parca_kodu'] : null,
                    'parca_dagilimi' => $parcaDagilimi,
                    'seri_nolari' => $seriListesi,
                    'miktar' => $iadeMiktari,
                    'birim_fiyat' => (float) $kalem->birim_fiyat,
                    'kdv_orani' => (float) $kalem->kdv_orani,
                    'kdv_tutari' => $kdv,
                    'satir_toplami' => $satirToplami,
                ]);

                app(StokHareketServisi::class)->kayitOlustur($firmaId, [
                    'stok_id' => (int) $kalem->stok_id,
                    'depo_id' => (int) ($satis->kalemler()->whereKey($satisKalemId)->value('depo_id') ?? 0),
                    'cari_id' => $satis->cari_id,
                    'islem_turu' => StokHareketIslemTuru::SatisIadesi,
                    'miktar' => $iadeMiktari,
                    'parca_dagilimi' => $parcaDagilimi,
                    'seri_nolari' => $seriListesi,
                    'birim_fiyat' => (float) $kalem->birim_fiyat,
                    'toplam' => $satirToplami,
                    'belge_turu' => StokBelgeTuru::Duzeltme,
                    'belge_id' => (int) $iade->getKey(),
                    'aciklama' => 'Barkodlu satis iade #'.$iade->iade_no,
                    'tarih' => now()->toDateTimeString(),
                ]);

                $finansIadeKaydi = $this->satisIadeOdemesiniKaydet($satis, $iade, $neden);

                return [
                    'iade' => $iade,
                    'finans_iade_kaydi' => $finansIadeKaydi,
                ];
            });
            /** @var BarkodluSatisIade $iade */
            $iade = $sonuc['iade'];
            $finansIadeKaydi = (bool) ($sonuc['finans_iade_kaydi'] ?? false);

            $this->olayLogla(
                'barkodlu_satis.satis_iade_olusturuldu',
                'info',
                'Barkodlu satis iade kaydi olusturuldu.',
                [
                    'firma_id' => $firmaId,
                    'kullanici_id' => $kullaniciId,
                    'satis_id' => $satisId,
                    'satis_kalem_id' => $satisKalemId,
                    'iade_id' => (int) $iade->id,
                    'iade_no' => (string) $iade->iade_no,
                    'iade_miktari' => $iadeMiktari,
                    'finans_iade_kaydi' => $finansIadeKaydi,
                ]
            );
            $this->metrikArtir('muhasebe:metrics:barkodlu_satis:iade');
            app(BarkodluSatisTelegramBildirimServisi::class)->iadeOlusturuldu($iade);

            return $iade;
        } catch (Throwable $e) {
            $this->hataLogla(
                'barkodlu_satis.satis_iade_hatasi',
                'Barkodlu satis iade hatasi.',
                [
                    'firma_id' => $firmaId,
                    'kullanici_id' => $kullaniciId,
                    'satis_id' => $satisId,
                    'satis_kalem_id' => $satisKalemId,
                    'iade_miktari' => $iadeMiktari,
                ],
                $e
            );
            $this->metrikArtir('muhasebe:metrics:barkodlu_satis:hata');

            throw $e;
        }
    }

    /** @return array<int, array{parca_kodu:string,miktar:float}> */
    private function iadePartiDagilimi(BarkodluSatisKalemi $kalem, float $iadeMiktari): array
    {
        $kaynak = (array) ($kalem->parca_dagilimi ?? []);
        if ($kaynak === [] && filled($kalem->parca_kodu ?? null)) {
            $kaynak = [['parca_kodu' => (string) $kalem->parca_kodu, 'miktar' => (float) $kalem->miktar]];
        }
        if ($kaynak === []) {
            return [];
        }

        $kalan = $iadeMiktari;
        $sonuc = [];
        foreach ($kaynak as $satir) {
            $parcaKodu = trim((string) ($satir['parca_kodu'] ?? ''));
            $miktar = max(0, (float) ($satir['miktar'] ?? 0));
            if ($parcaKodu === '' || $miktar <= 0 || $kalan <= 0.0001) {
                continue;
            }

            $secilecek = min($miktar, $kalan);
            $sonuc[] = ['parca_kodu' => $parcaKodu, 'miktar' => round($secilecek, 4)];
            $kalan = round($kalan - $secilecek, 4);
        }

        if ($kalan > 0.0001) {
            throw new InvalidArgumentException('Iade miktari satis kaydindaki parti / lot dagilimini asiyor.');
        }

        return $sonuc;
    }

    private function stokDepoId(int $firmaId, StokKarti $stok): ?int
    {
        if (! (bool) $this->firmaAyarDeposu->oku($firmaId, 'stok_depo_modulu_aktif_mi', false)) {
            return null;
        }

        $adaylar = [
            (int) ($stok->depo_id ?? 0),
            (int) ($this->firmaAyarDeposu->oku($firmaId, 'stok_varsayilan_depo_id', 0) ?? 0),
        ];

        foreach ($adaylar as $depoId) {
            if ($depoId > 0 && Depo::tenantScopeOlmadan(fn () => Depo::query()
                ->where('firma_id', $firmaId)
                ->whereKey($depoId)
                ->where('aktif_mi', true)
                ->exists())) {
                return $depoId;
            }
        }

        return Depo::tenantScopeOlmadan(fn () => Depo::query()
            ->where('firma_id', $firmaId)
            ->where('aktif_mi', true)
            ->where('varsayilan_mi', true)
            ->value('id'));
    }

    public function iadeKaydiniGeriAl(
        int $firmaId,
        int $iadeId,
        int $kullaniciId,
        ?string $neden = null
    ): void {
        try {
            DB::transaction(function () use ($firmaId, $iadeId, $neden): void {
                /** @var BarkodluSatisIade|null $iade */
                $iade = BarkodluSatisIade::query()
                    ->where('firma_id', $firmaId)
                    ->whereKey($iadeId)
                    ->lockForUpdate()
                    ->first();

                if (! $iade) {
                    throw new InvalidArgumentException('Geri alinacak iade kaydi bulunamadi.');
                }

                $hareketler = StokHareketi::query()
                    ->where('firma_id', $firmaId)
                    ->where('belge_turu', StokBelgeTuru::Duzeltme->value)
                    ->where('belge_id', $iadeId)
                    ->where('islem_turu', StokHareketIslemTuru::SatisIadesi->value)
                    ->where('durum', StokHareketDurumu::Aktif->value)
                    ->orderByDesc('id')
                    ->get();

                foreach ($hareketler as $hareket) {
                    app(StokHareketServisi::class)->tersKayitOlustur(
                        hareket: $hareket,
                        aciklama: 'Barkodlu satis iade geri al #'.$iade->iade_no
                    );
                }

                $this->satisIadeOdemesiniTersle($iade);
                $iade->delete();
            });

            $this->olayLogla(
                'barkodlu_satis.satis_iade_geri_alindi',
                'warning',
                'Barkodlu satis iade kaydi geri alindi.',
                [
                    'firma_id' => $firmaId,
                    'kullanici_id' => $kullaniciId,
                    'iade_id' => $iadeId,
                    'neden' => $neden,
                ]
            );
            $this->metrikArtir('muhasebe:metrics:barkodlu_satis:iade_geri_al');
        } catch (Throwable $e) {
            $this->hataLogla(
                'barkodlu_satis.satis_iade_geri_alma_hatasi',
                'Barkodlu satis iade geri alma hatasi.',
                [
                    'firma_id' => $firmaId,
                    'kullanici_id' => $kullaniciId,
                    'iade_id' => $iadeId,
                ],
                $e
            );
            $this->metrikArtir('muhasebe:metrics:barkodlu_satis:hata');

            throw $e;
        }
    }

    /**
     * @param  array<string,mixed>  $context
     */
    private function olayLogla(string $tip, string $seviye, string $mesaj, array $context): void
    {
        $this->sistemOlayServisi->olayKaydet($tip, $seviye, $mesaj, $context);
        Log::channel((string) config('muhasebe.sistem.log_channel', config('muhasebe.stok.log_channel', 'muhasebe')))
            ->{$seviye}($tip, $context);
    }

    /**
     * @param  array<string,mixed>  $context
     */
    private function hataLogla(string $tip, string $mesaj, array $context, Throwable $e): void
    {
        $this->olayLogla($tip, 'error', $mesaj, $context + [
            'hata' => $e->getMessage(),
            'hata_tipi' => $e::class,
        ]);
    }

    private function metrikArtir(string $anahtar, int $adet = 1): void
    {
        try {
            Cache::increment($anahtar, $adet);
        } catch (Throwable) {
            // Metrik yazimi is akisina etki etmez.
        }
    }

    private function satisTahsilatiniKaydet(BarkodluSatis $satis, array $veri): bool
    {
        $odemeTipi = strtolower(trim((string) $satis->odeme_tipi));
        $vadeliOdemeMi = in_array($odemeTipi, ['veresiye', 'taksitli'], true);
        if ($vadeliOdemeMi) {
            $tutar = number_format(max(0, (float) ($veri['pesinat_tutari'] ?? 0)), 2, '.', '');
            if (bccomp($tutar, '0', 2) <= 0) {
                return false;
            }

            $odemeTipi = strtolower(trim((string) ($veri['pesinat_odeme_tipi'] ?? 'nakit')));
        }

        if (! in_array($odemeTipi, ['nakit', 'kart', 'havale'], true)) {
            return false;
        }

        $paraBirimi = strtoupper((string) ($satis->para_birimi ?: 'TRY'));
        $tutar = $vadeliOdemeMi
            ? $tutar
            : number_format((float) $satis->genel_toplam, 2, '.', '');
        if (bccomp($tutar, '0', 2) <= 0) {
            return false;
        }
        $tahsilatAciklamasi = $vadeliOdemeMi
            ? 'Barkodlu satis pesinat #'.$satis->satis_no
            : 'Barkodlu satis tahsilat #'.$satis->satis_no;

        $mevcut = FinansHareketi::query()
            ->where('firma_id', (int) $satis->firma_id)
            ->where('referans_turu', 'barkodlu_satis')
            ->where('referans_id', (int) $satis->id)
            ->where('durum', FinansHareketDurumu::Aktif->value)
            ->exists();
        if ($mevcut) {
            return true;
        }

        try {
            if ($odemeTipi === 'nakit') {
                $kasaHesapId = (int) ($veri['kasa_hesap_id'] ?? 0);
                $kasa = $kasaHesapId > 0
                    ? KasaHesabi::query()
                        ->where('firma_id', (int) $satis->firma_id)
                        ->where('durum', HesapDurumu::Aktif->value)
                        ->where('para_birimi', $paraBirimi)
                        ->whereKey($kasaHesapId)
                        ->first()
                    : KasaHesabi::query()
                    ->where('firma_id', (int) $satis->firma_id)
                    ->where('durum', HesapDurumu::Aktif->value)
                    ->where('para_birimi', $paraBirimi)
                    ->orderBy('id')
                    ->first();

                if (! $kasa) {
                    throw new InvalidArgumentException('Nakit tahsilat icin aktif kasa hesabi bulunamadi.');
                }

                if ((int) ($satis->cari_id ?? 0) > 0) {
                    app(FinansHareketServisi::class)->tahsilatKasadanKaydet(
                        firmaId: (int) $satis->firma_id,
                        cariId: (int) $satis->cari_id,
                        kasaHesapId: (int) $kasa->id,
                        tutar: $tutar,
                        paraBirimi: $paraBirimi,
                        tarih: (string) $satis->satis_tarihi,
                        aciklama: $tahsilatAciklamasi,
                        referansTuru: 'barkodlu_satis',
                        referansId: (int) $satis->id,
                    );

                    return true;
                }

                $finans = $this->perakendeFinansKaydiOlustur($satis, $tutar, $paraBirimi);
                KasaHareketi::query()->create([
                    'firma_id' => (int) $satis->firma_id,
                    'finans_hareket_id' => (int) $finans->id,
                    'kasa_hesap_id' => (int) $kasa->id,
                    'tutar' => $tutar,
                    'para_birimi' => $paraBirimi,
                    'durum' => HareketDurumu::Aktif,
                    'iptal_edilen_hareket_id' => null,
                ]);

                return true;
            }

            if ($odemeTipi === 'havale') {
                $bankaHesapId = (int) ($veri['banka_hesap_id'] ?? 0);
                $banka = $bankaHesapId > 0
                    ? BankaHesabi::query()
                        ->where('firma_id', (int) $satis->firma_id)
                        ->where('durum', HesapDurumu::Aktif->value)
                        ->where('para_birimi', $paraBirimi)
                        ->whereKey($bankaHesapId)
                        ->first()
                    : BankaHesabi::query()
                        ->where('firma_id', (int) $satis->firma_id)
                        ->where('durum', HesapDurumu::Aktif->value)
                        ->where('para_birimi', $paraBirimi)
                        ->orderBy('id')
                        ->first();

                if (! $banka) {
                    throw new InvalidArgumentException('Havale tahsilati icin aktif banka hesabi bulunamadi.');
                }

                if ((int) ($satis->cari_id ?? 0) > 0) {
                    app(FinansHareketServisi::class)->tahsilatBankadanKaydet(
                        firmaId: (int) $satis->firma_id,
                        cariId: (int) $satis->cari_id,
                        bankaHesapId: (int) $banka->id,
                        tutar: $tutar,
                        paraBirimi: $paraBirimi,
                        tarih: (string) $satis->satis_tarihi,
                        aciklama: $tahsilatAciklamasi,
                        referansTuru: 'barkodlu_satis',
                        referansId: (int) $satis->id,
                    );

                    return true;
                }

                $finans = $this->perakendeFinansKaydiOlustur($satis, $tutar, $paraBirimi);
                BankaHareketi::query()->create([
                    'firma_id' => (int) $satis->firma_id,
                    'finans_hareket_id' => (int) $finans->id,
                    'banka_hesap_id' => (int) $banka->id,
                    'tutar' => $tutar,
                    'para_birimi' => $paraBirimi,
                    'durum' => HareketDurumu::Aktif,
                    'iptal_edilen_hareket_id' => null,
                ]);

                return true;
            }

            $posHesapId = (int) ($veri['pos_hesap_id'] ?? 0);
            $pos = $posHesapId > 0
                ? PosHesabi::query()
                    ->where('firma_id', (int) $satis->firma_id)
                    ->where('durum', HesapDurumu::Aktif->value)
                    ->where('para_birimi', $paraBirimi)
                    ->whereKey($posHesapId)
                    ->first()
                : PosHesabi::query()
                    ->where('firma_id', (int) $satis->firma_id)
                    ->where('durum', HesapDurumu::Aktif->value)
                    ->where('para_birimi', $paraBirimi)
                    ->orderBy('id')
                    ->first();

            if (! $pos) {
                throw new InvalidArgumentException('Kart tahsilati icin aktif POS hesabi bulunamadi.');
            }

            if ((int) ($satis->cari_id ?? 0) > 0) {
                app(FinansHareketServisi::class)->tahsilatPosKaydet(
                    firmaId: (int) $satis->firma_id,
                    cariId: (int) $satis->cari_id,
                    posHesapId: (int) $pos->id,
                    tutar: $tutar,
                    paraBirimi: $paraBirimi,
                    tarih: (string) $satis->satis_tarihi,
                    aciklama: $tahsilatAciklamasi,
                    referansTuru: 'barkodlu_satis',
                    referansId: (int) $satis->id,
                );

                return true;
            }

            $finans = $this->perakendeFinansKaydiOlustur($satis, $tutar, $paraBirimi);
            PosHareketi::query()->create([
                'firma_id' => (int) $satis->firma_id,
                'finans_hareket_id' => (int) $finans->id,
                'pos_hesap_id' => (int) $pos->id,
                'tutar' => $tutar,
                'para_birimi' => $paraBirimi,
                'durum' => HareketDurumu::Aktif,
                'iptal_edilen_hareket_id' => null,
            ]);

            return true;
        } catch (Throwable $e) {
            $this->olayLogla(
                'barkodlu_satis.tahsilat_kayit_hatasi',
                'warning',
                'Barkodlu satis tahsilat kaydi olusturulamadi.',
                [
                    'firma_id' => (int) $satis->firma_id,
                    'satis_id' => (int) $satis->id,
                    'satis_no' => (string) $satis->satis_no,
                    'odeme_tipi' => $odemeTipi,
                    'hata' => $e->getMessage(),
                ]
            );
            $this->metrikArtir('muhasebe:metrics:barkodlu_satis:tahsilat_hata');

            return false;
        }
    }

    private function perakendeFinansKaydiOlustur(BarkodluSatis $satis, string $tutar, string $paraBirimi): FinansHareketi
    {
        return FinansHareketi::query()->create(array_merge(
            FinansAuditBaglami::otomatikFinansAlanlari(),
            [
                'firma_id' => (int) $satis->firma_id,
                'tur' => FinansHareketTuru::Tahsilat,
                'tarih' => (string) $satis->satis_tarihi,
                'vade_tarihi' => null,
                'tutar' => $tutar,
                'baz_tutar' => $tutar,
                'para_birimi' => $paraBirimi,
                'baz_para_birimi' => $paraBirimi,
                'kur' => 1,
                'cari_id' => null,
                'aciklama' => 'Barkodlu satis tahsilat #'.$satis->satis_no,
                'referans_turu' => 'barkodlu_satis',
                'referans_id' => (int) $satis->id,
                'durum' => FinansHareketDurumu::Aktif,
                'iptal_edilen_hareket_id' => null,
            ]
        ));
    }

    private function satisTahsilatiniTersle(BarkodluSatis $satis): void
    {
        $finansHareketleri = FinansHareketi::query()
            ->where('firma_id', (int) $satis->firma_id)
            ->where('referans_turu', 'barkodlu_satis')
            ->where('referans_id', (int) $satis->id)
            ->where('durum', FinansHareketDurumu::Aktif->value)
            ->get();

        foreach ($finansHareketleri as $finans) {
            app(FinansHareketServisi::class)->tersKayitOlustur(
                $finans,
                'Barkodlu satis iptal tahsilat tersi #'.$satis->satis_no
            );
        }
    }

    private function satisIadeOdemesiniKaydet(BarkodluSatis $satis, BarkodluSatisIade $iade, ?string $neden = null): bool
    {
        $iadeTutar = number_format((float) $iade->toplam_iade_tutari, 2, '.', '');
        if (bccomp($iadeTutar, '0', 2) <= 0) {
            return false;
        }

        $mevcut = FinansHareketi::query()
            ->where('firma_id', (int) $satis->firma_id)
            ->where('referans_turu', 'barkodlu_satis_iade')
            ->where('referans_id', (int) $iade->id)
            ->where('durum', FinansHareketDurumu::Aktif->value)
            ->exists();
        if ($mevcut) {
            return true;
        }

        $tahsilat = FinansHareketi::query()
            ->where('firma_id', (int) $satis->firma_id)
            ->where('referans_turu', 'barkodlu_satis')
            ->where('referans_id', (int) $satis->id)
            ->where('durum', FinansHareketDurumu::Aktif->value)
            ->with(['kasaHareketleri', 'bankaHareketleri', 'posHareketleri'])
            ->latest('id')
            ->first();

        if (! $tahsilat) {
            $this->olayLogla(
                'barkodlu_satis.iade_finans_kaydi_atlandi',
                'warning',
                'Iade finans kaydi icin kaynak tahsilat bulunamadi.',
                [
                    'firma_id' => (int) $satis->firma_id,
                    'satis_id' => (int) $satis->id,
                    'iade_id' => (int) $iade->id,
                ]
            );

            return false;
        }

        $cariId = (int) ($satis->cari_id ?? 0);
        if ($cariId < 1) {
            return false;
        }

        $aciklama = 'Barkodlu satis iade odemesi #'.$iade->iade_no;
        if (filled($neden)) {
            $aciklama .= ' | Neden: '.trim((string) $neden);
        }

        $kasaHareket = $tahsilat->kasaHareketleri()
            ->where('durum', HareketDurumu::Aktif->value)
            ->latest('id')
            ->first();
        if ($kasaHareket) {
            app(FinansHareketServisi::class)->odemeKasadanKaydet(
                firmaId: (int) $satis->firma_id,
                cariId: $cariId,
                kasaHesapId: (int) $kasaHareket->kasa_hesap_id,
                tutar: $iadeTutar,
                paraBirimi: strtoupper((string) $satis->para_birimi),
                tarih: (string) ($iade->iade_tarihi ?? now()->toDateTimeString()),
                aciklama: $aciklama,
                referansTuru: 'barkodlu_satis_iade',
                referansId: (int) $iade->id,
            );

            return true;
        }

        $bankaHareket = $tahsilat->bankaHareketleri()
            ->where('durum', HareketDurumu::Aktif->value)
            ->latest('id')
            ->first();
        if ($bankaHareket) {
            app(FinansHareketServisi::class)->odemeBankadanKaydet(
                firmaId: (int) $satis->firma_id,
                cariId: $cariId,
                bankaHesapId: (int) $bankaHareket->banka_hesap_id,
                tutar: $iadeTutar,
                paraBirimi: strtoupper((string) $satis->para_birimi),
                tarih: (string) ($iade->iade_tarihi ?? now()->toDateTimeString()),
                aciklama: $aciklama,
                referansTuru: 'barkodlu_satis_iade',
                referansId: (int) $iade->id,
            );

            return true;
        }

        $posHareket = $tahsilat->posHareketleri()
            ->where('durum', HareketDurumu::Aktif->value)
            ->latest('id')
            ->first();
        if ($posHareket) {
            app(FinansHareketServisi::class)->posIadeKaydet(
                firmaId: (int) $satis->firma_id,
                cariId: $cariId,
                posHesapId: (int) $posHareket->pos_hesap_id,
                tutar: $iadeTutar,
                paraBirimi: strtoupper((string) $satis->para_birimi),
                tarih: (string) ($iade->iade_tarihi ?? now()->toDateTimeString()),
                aciklama: $aciklama,
                referansTuru: 'barkodlu_satis_iade',
                referansId: (int) $iade->id,
            );

            return true;
        }

        return false;
    }

    private function satisIadeOdemesiniTersle(BarkodluSatisIade $iade): void
    {
        $finansHareketleri = FinansHareketi::query()
            ->where('firma_id', (int) $iade->firma_id)
            ->where('referans_turu', 'barkodlu_satis_iade')
            ->where('referans_id', (int) $iade->id)
            ->where('durum', FinansHareketDurumu::Aktif->value)
            ->get();

        foreach ($finansHareketleri as $finans) {
            app(FinansHareketServisi::class)->tersKayitOlustur(
                $finans,
                'Barkodlu satis iade geri al finans tersi #'.$iade->iade_no
            );
        }
    }

    private function sonrakiSatisNo(int $firmaId): string
    {
        $prefix = 'BS-'.now()->format('Y');
        $max = (int) BarkodluSatis::query()
            ->where('firma_id', $firmaId)
            ->where('satis_no', 'like', $prefix.'-%')
            ->get()
            ->map(function (BarkodluSatis $satis) use ($prefix): int {
                if (preg_match('/^'.preg_quote($prefix, '/').'-(\d+)$/', (string) $satis->satis_no, $eslesme)) {
                    return (int) $eslesme[1];
                }

                return 0;
            })
            ->max();

        return $prefix.'-'.str_pad((string) ($max + 1), 6, '0', STR_PAD_LEFT);
    }

    private function sonrakiIadeNo(int $firmaId): string
    {
        $prefix = 'BSI-'.now()->format('Y');
        $max = (int) BarkodluSatisIade::query()
            ->where('firma_id', $firmaId)
            ->where('iade_no', 'like', $prefix.'-%')
            ->get()
            ->map(function (BarkodluSatisIade $iade) use ($prefix): int {
                if (preg_match('/^'.preg_quote($prefix, '/').'-(\d+)$/', (string) $iade->iade_no, $eslesme)) {
                    return (int) $eslesme[1];
                }

                return 0;
            })
            ->max();

        return $prefix.'-'.str_pad((string) ($max + 1), 6, '0', STR_PAD_LEFT);
    }

    private function dogrulamaKoduUret(int $firmaId): string
    {
        do {
            $kod = 'IAD-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
            $varMi = BarkodluSatisIade::query()
                ->where('firma_id', $firmaId)
                ->where('dogrulama_kodu', $kod)
                ->exists();
        } while ($varMi);

        return $kod;
    }

    /**
     * @param array<string,mixed> $veri
     */
    private function satisCariIdBelirle(int $firmaId, array $veri): int
    {
        $cariId = (int) ($veri['cari_id'] ?? 0);
        $paraBirimi = strtoupper((string) ($veri['para_birimi'] ?? 'TRY'));
        if ($cariId > 0) {
            $cari = Cari::query()
                ->where('firma_id', $firmaId)
                ->whereKey($cariId)
                ->first();
            if (! $cari) {
                throw new InvalidArgumentException('Secilen cari bu firmaya ait degil.');
            }
            if (strtoupper((string) ($cari->para_birimi ?: 'TRY')) !== $paraBirimi) {
                throw new InvalidArgumentException('Secilen cari para birimi ile satis para birimi uyusmuyor.');
            }

            return $cariId;
        }

        return $this->perakendeCariIdGetirVeyaOlustur($firmaId, $paraBirimi);
    }

    private function perakendeCariIdGetirVeyaOlustur(int $firmaId, string $paraBirimi): int
    {
        $perakendeCariAdi = $this->perakendeCariAdi($firmaId);

        $mevcut = Cari::query()
            ->where('firma_id', $firmaId)
            ->where(function ($query): void {
                $query->where('kod', self::PERAKENDE_CARI_KOD)
                    ->orWhere('ad', self::VARSAYILAN_PERAKENDE_CARI_ADI);
            })
            ->orderBy('id')
            ->first();

        if ($mevcut) {
            if ((string) $mevcut->durum !== CariDurumu::Aktif->value) {
                $mevcut->durum = CariDurumu::Aktif->value;
            }
            if (blank($mevcut->para_birimi)) {
                $mevcut->para_birimi = $paraBirimi;
            }
            if (trim((string) ($mevcut->ad ?? '')) !== $perakendeCariAdi) {
                $mevcut->ad = $perakendeCariAdi;
            }
            $mevcut->save();

            return (int) $mevcut->id;
        }

        $yeni = Cari::query()->create([
            'firma_id' => $firmaId,
            'kod' => self::PERAKENDE_CARI_KOD,
            'ad' => $perakendeCariAdi,
            'tur' => CariTuru::Musteri->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => $paraBirimi,
            'aciklama' => 'Barkodlu satis cari secimsiz islemler icin otomatik olusturuldu.',
        ]);

        return (int) $yeni->id;
    }

    private function perakendeCariAdi(int $firmaId): string
    {
        $ad = trim((string) $this->firmaAyarDeposu->oku(
            $firmaId,
            'barkodlu_satis_perakende_cari_ad',
            self::VARSAYILAN_PERAKENDE_CARI_ADI
        ));

        if ($ad === '') {
            return self::VARSAYILAN_PERAKENDE_CARI_ADI;
        }

        return mb_substr($ad, 0, 255);
    }

    /**
     * @param  array<int,array<string,mixed>>  $kalemler
     * @return array<int, StokKarti>
     */
    private function stokHaritasi(int $firmaId, array $kalemler, bool $kilitle = false): array
    {
        $stokIdleri = collect($kalemler)
            ->map(fn (array $kalem): int => (int) ($kalem['stok_id'] ?? 0))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($stokIdleri->isEmpty()) {
            throw new InvalidArgumentException('Satis kalemlerinde stok secimi bulunamadi.');
        }

        $sorgu = StokKarti::query()
            ->where('firma_id', $firmaId)
            ->whereIn('id', $stokIdleri->all())
            ->orderBy('id');
        if ($kilitle) {
            $sorgu->lockForUpdate();
        }

        /** @var array<int, StokKarti> $stoklar */
        $stoklar = $sorgu->get()->keyBy('id')->all();

        if (count($stoklar) !== $stokIdleri->count()) {
            throw new InvalidArgumentException('Kalemlerde firma disi veya silinmis stok tespit edildi.');
        }

        return $stoklar;
    }

    /**
     * @param  array<int,array<string,mixed>>  $kalemler
     * @param  array<int, StokKarti>  $stoklar
     */
    private function stokMusaitliginiTopluDogrula(array $kalemler, array $stoklar, bool $eksiStokIzinli = false): void
    {
        if ($eksiStokIzinli) {
            return;
        }

        $stokBazliMiktar = [];
        foreach ($kalemler as $kalem) {
            $stokId = (int) ($kalem['stok_id'] ?? 0);
            if ($stokId < 1) {
                continue;
            }

            $stokBazliMiktar[$stokId] = (float) ($stokBazliMiktar[$stokId] ?? 0) + (float) ($kalem['miktar'] ?? 0);
        }

        foreach ($stokBazliMiktar as $stokId => $toplamMiktar) {
            $stok = $stoklar[$stokId] ?? null;
            if (! $stok) {
                throw new InvalidArgumentException('Gecersiz stok kalemi tespit edildi.');
            }

            if ((bool) $stok->stok_takip && $stok->musaitStokMiktari() + 0.0001 < $toplamMiktar) {
                throw new InvalidArgumentException('Yetersiz stok: '.$stok->ad);
            }
        }
    }
}
