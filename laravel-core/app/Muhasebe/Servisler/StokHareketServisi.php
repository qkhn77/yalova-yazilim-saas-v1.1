<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\StokHareketi;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\Depo;
use App\Models\Muhasebe\StokDepoBakiyesi;
use App\Models\Muhasebe\StokTransferi;
use App\Models\Muhasebe\StokParcasi;
use App\Models\Muhasebe\StokHareketiParcasi;
use App\Models\Muhasebe\StokSeriNo;
use App\Models\Muhasebe\StokHareketiSeri;
use App\Models\Muhasebe\StokOlcuBakiyesi;
use App\Models\Muhasebe\FaturaKalemi;
use App\Models\Muhasebe\Cari;
use App\Muhasebe\Enumlar\StokBelgeTuru;
use App\Muhasebe\Enumlar\StokHareketDurumu;
use App\Muhasebe\Enumlar\StokHareketIslemTuru;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Guvenlik\MuhasebeFirmaErisimDenetleyicisi;
use App\Services\SistemOlayServisi;
use App\Services\TenantContextService;
use App\Services\FirmaAyarDeposu;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class StokHareketServisi
{
    private const PARA_BASAMAK = 8;

    public function __construct(
        private readonly MuhasebeFirmaErisimDenetleyicisi $firmaDenetleyicisi,
        private readonly StokMaliyetHesaplamaServisi $stokMaliyetHesaplamaServisi,
        private readonly SistemOlayServisi $sistemOlayServisi,
        private readonly FirmaAyarDeposu $firmaAyarDeposu,
    ) {}

    /**
     * Aynı stok için bir depodan diğerine tek belgeli transfer oluşturur.
     *
     * @return StokTransferi
     */
    public function transferOlustur(int $firmaId, array $alanlar): StokTransferi
    {
        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt($firmaId);
        $stokId = (int) ($alanlar['stok_id'] ?? 0);
        $kaynakDepoId = (int) ($alanlar['kaynak_depo_id'] ?? 0);
        $hedefDepoId = (int) ($alanlar['hedef_depo_id'] ?? 0);
        if ($stokId < 1 || $kaynakDepoId < 1 || $hedefDepoId < 1 || $kaynakDepoId === $hedefDepoId) {
            throw new IsKuraliIstisnasi('Geçerli stok, kaynak depo ve farklı hedef depo seçilmelidir.');
        }

        $miktar = $this->normalizePozitifMiktar($alanlar['miktar'] ?? null);
        $parcaIds = array_values(array_unique(array_filter(array_map('intval', (array) ($alanlar['stok_parcasi_ids'] ?? [])))));
        $tarih = $alanlar['tarih'] ?? now();

        return DB::transaction(function () use ($firmaId, $alanlar, $stokId, $kaynakDepoId, $hedefDepoId, $miktar, $parcaIds, $tarih): StokTransferi {
            $depolar = Depo::query()
                ->where('firma_id', $firmaId)
                ->where('aktif_mi', true)
                ->whereIn('id', [$kaynakDepoId, $hedefDepoId])
                ->count();
            if ($depolar !== 2) {
                throw new IsKuraliIstisnasi('Depolar aktif firmaya ait olmalıdır.');
            }

            if ($parcaIds !== []) {
                throw new IsKuraliIstisnasi('Stok parçası/Parti-Lot transferi devre dışıdır. Basit stok miktarı transfer edilebilir.');
            }

            $transfer = StokTransferi::query()->create([
                'firma_id' => $firmaId,
                'transfer_no' => 'TRF-'.now()->format('YmdHisv'),
                'kaynak_depo_id' => $kaynakDepoId,
                'hedef_depo_id' => $hedefDepoId,
                'tarih' => $tarih,
                'durum' => 'tamamlandi',
                'aciklama' => (string) ($alanlar['aciklama'] ?? ''),
            ]);

            $ortak = [
                'stok_id' => $stokId,
                'miktar' => $miktar,
                'belge_turu' => StokBelgeTuru::Transfer,
                'belge_id' => (int) $transfer->id,
                'referans_tipi' => StokBelgeTuru::Transfer->value,
                'referans_id' => (int) $transfer->id,
                'tarih' => $tarih,
                'aciklama' => (string) ($alanlar['aciklama'] ?? 'Depolar arası stok transferi'),
                'negatif_stok_izinli' => (bool) ($alanlar['negatif_stok_izinli'] ?? false),
            ];

            $cikis = $this->kayitOlustur($firmaId, array_merge($ortak, [
                'depo_id' => $kaynakDepoId,
                'islem_turu' => StokHareketIslemTuru::TransferCikis,
                'parca_transferi_ids' => $parcaIds,
            ]));
            $giris = $this->kayitOlustur($firmaId, array_merge($ortak, [
                'depo_id' => $hedefDepoId,
                'islem_turu' => StokHareketIslemTuru::TransferGiris,
                'parti_devirleri' => [],
                'seri_devirleri' => $cikis->seriHareketleri()->with('seriNo')->get()
                    ->map(fn (StokHareketiSeri $seri): array => [
                        'seri_no' => (string) $seri->seriNo->seri_no,
                        'birim_maliyet' => (string) $seri->seriNo->birim_maliyet,
                        'garanti_baslangic_tarihi' => $seri->seriNo->garanti_baslangic_tarihi,
                        'garanti_bitis_tarihi' => $seri->seriNo->garanti_bitis_tarihi,
                    ])->all(),
            ]));

            return $transfer;
        });
    }

    /**
     * @param  array{
     *     stok_id:int,
     *     cari_id?:int|null,
     *     islem_turu:StokHareketIslemTuru|string,
     *     miktar:string|float|int,
     *     birim_fiyat?:string|float|int,
     *     toplam?:string|float|int,
     *     birim_maliyet?:string|float|int,
     *     toplam_maliyet?:string|float|int,
     *     belge_turu:StokBelgeTuru|string,
     *     belge_id:int,
     *     referans_tipi?:string,
     *     referans_id?:int,
     *     aciklama?:string,
     *     negatif_stok_izinli?:bool,
     *     tarih:\DateTimeInterface|string,
     * }  $alanlar
     */
    public function kayitOlustur(int $firmaId, array $alanlar, bool $eTicaretSistemCagrisi = false): StokHareketi
    {
        if ($eTicaretSistemCagrisi) {
            $this->firmaDenetleyicisi->eTicaretYazmaIcinFirmaKontrolEt($firmaId);
        } else {
            $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt($firmaId);
        }

        $islem = function () use ($firmaId, $alanlar): StokHareketi {
            $stok = StokKarti::query()->lockForUpdate()->whereKey($alanlar['stok_id'])->firstOrFail();
            if ((int) $stok->firma_id !== $firmaId) {
                throw new IsKuraliIstisnasi('Stok kartı farklı firmaya ait.');
            }

            $depoId = (int) ($alanlar['depo_id'] ?? ($stok->depo_id ?? 0));
            if ($depoId > 0 && ! Depo::query()
                ->where('firma_id', $firmaId)
                ->whereKey($depoId)
                ->where('aktif_mi', true)
                ->exists()) {
                throw new IsKuraliIstisnasi('Depo farklı firmaya ait, pasif veya bulunamadı.');
            }

            $cariId = (int) ($alanlar['cari_id'] ?? 0);
            if ($cariId > 0 && ! Cari::query()
                ->where('firma_id', $firmaId)
                ->whereKey($cariId)
                ->exists()) {
                throw new IsKuraliIstisnasi('Cari kartı farklı firmaya ait veya bulunamadı.');
            }

            $islemTuru = $alanlar['islem_turu'] instanceof StokHareketIslemTuru
                ? $alanlar['islem_turu']
                : StokHareketIslemTuru::from($alanlar['islem_turu']);

            $belgeTuru = $alanlar['belge_turu'] instanceof StokBelgeTuru
                ? $alanlar['belge_turu']
                : StokBelgeTuru::from($alanlar['belge_turu']);

            $miktar = $this->normalizePozitifMiktar($alanlar['miktar'] ?? null);
            $birimFiyat = $this->normalizeSifirVeyaPozitifTutar($alanlar['birim_fiyat'] ?? 0, 'Birim fiyat negatif olamaz.');
            $birimMaliyetGirdi = $this->normalizeSifirVeyaPozitifTutar($alanlar['birim_maliyet'] ?? $birimFiyat, 'Birim maliyet negatif olamaz.');
            $toplam = (string) ($alanlar['toplam'] ?? bcmul($miktar, $birimFiyat, self::PARA_BASAMAK));
            $referansTipi = (string) ($alanlar['referans_tipi'] ?? $belgeTuru->value);
            $referansId = (int) ($alanlar['referans_id'] ?? $alanlar['belge_id']);
            if ($referansTipi !== $belgeTuru->value) {
                throw new IsKuraliIstisnasi('Referans tipi belge türü ile aynı olmalıdır.');
            }
            if ($referansId !== (int) $alanlar['belge_id']) {
                throw new IsKuraliIstisnasi('Referans ID belge ID ile aynı olmalıdır.');
            }
            $onceki = (string) ($stok->stok_miktari ?? 0);
            $delta = $this->miktarDelta($islemTuru, $miktar);
            $sonraki = bcadd($onceki, $delta, 8);

            $this->negatifStokKontrolEt(
                $stok,
                $islemTuru,
                $onceki,
                $sonraki,
                (bool) ($alanlar['negatif_stok_izinli'] ?? false),
                $firmaId
            );

            $maliyet = $this->stokMaliyetHesaplamaServisi->hareketMaliyetiHesapla([
                'stok_takip' => (bool) ($stok->stok_takip ?? true),
                'onceki_miktar' => $onceki,
                'miktar' => $miktar,
                'birim_maliyet' => $birimMaliyetGirdi,
                'islem_turu' => $islemTuru,
                'mevcut_ortalama' => (string) ($stok->guncel_birim_maliyet ?? 0),
                'mevcut_stok_degeri' => (string) ($stok->stok_degeri ?? 0),
                'tarih' => $alanlar['tarih'],
            ]);

            $stok->update([
                'stok_miktari' => $sonraki,
                'guncel_birim_maliyet' => $maliyet['yeni_ortalama'],
                'stok_degeri' => $maliyet['yeni_stok_degeri'],
                'son_giris_maliyeti' => $maliyet['son_giris_maliyeti'] ?? $stok->son_giris_maliyeti,
                'son_hareket_tarihi' => $maliyet['son_hareket_tarihi'],
                'negative_flag' => bccomp($sonraki, '0', 8) < 0,
            ]);
            if (is_numeric((string) ($stok->minimum_stok ?? null))
                && bccomp($sonraki, (string) $stok->minimum_stok, 8) <= 0) {
                $this->sistemOlayServisi->olayKaydet('stok.kritik_stok_altinda', 'warning', 'Stok kritik seviyeye dustu.', [
                    'firma_id' => (int) $stok->firma_id,
                    'stok_id' => (int) $stok->id,
                ]);
            }

            $hareket = StokHareketi::query()->create([
                'firma_id' => $firmaId,
                'cari_id' => $cariId > 0 ? $cariId : null,
                'stok_id' => (int) $alanlar['stok_id'],
                'depo_id' => $depoId > 0 ? $depoId : null,
                'islem_turu' => $islemTuru,
                'miktar' => $miktar,
                'onceki_miktar' => $onceki,
                'sonraki_miktar' => $sonraki,
                'birim_fiyat' => $birimFiyat,
                'birim_maliyet' => $maliyet['birim_maliyet'],
                'toplam' => $toplam,
                'toplam_maliyet' => $maliyet['toplam_maliyet'],
                'belge_turu' => $belgeTuru,
                'referans_tipi' => $referansTipi,
                'belge_id' => (int) $alanlar['belge_id'],
                'referans_id' => $referansId,
                'aciklama' => (string) ($alanlar['aciklama'] ?? ''),
                'tarih' => $alanlar['tarih'],
                'islem_tarihi' => $alanlar['tarih'],
                'durum' => StokHareketDurumu::Aktif,
                'iptal_edilen_hareket_id' => null,
            ]);

            $this->seriHareketiniUygula($stok, $hareket, $depoId, $miktar, $islemTuru, $alanlar);

            $this->depoBakiyesiniGuncelle($firmaId, $depoId, (int) $stok->id, $delta);

            $this->logInfo('stok_hareketi.olustur', [
                'firma_id' => $firmaId,
                'stok_id' => (int) $alanlar['stok_id'],
                'hareket_id' => (int) $hareket->id,
                'islem_turu' => $islemTuru->value,
                'onceki_miktar' => $onceki,
                'sonraki_miktar' => $sonraki,
                'birim_maliyet' => $maliyet['birim_maliyet'],
                'toplam_maliyet' => $maliyet['toplam_maliyet'],
            ]);

            return $hareket;
        };

        $calistir = fn (): StokHareketi => $this->retryableTransaction($islem, 3, [
            'firma_id' => $firmaId,
            'islem' => 'kayit_olustur',
            'stok_id' => (int) $alanlar['stok_id'],
        ]);

        return $eTicaretSistemCagrisi
            ? app(TenantContextService::class)->sistemFirmaKapsaminda($calistir)
            : $calistir();
    }

    /**
     * Daha önce basit stok olarak açılmış mevcut miktarı yeni bir partiye bağlar.
     * Stok miktarını değiştirmez; yalnızca parti bakiyesini oluşturur.
     */
    public function mevcutStoguPartiyeAktar(int $firmaId, StokKarti $stok, array $alanlar): StokParcasi
    {
        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt($firmaId);

        return DB::transaction(function () use ($firmaId, $stok, $alanlar): StokParcasi {
            $kilitliStok = StokKarti::query()->lockForUpdate()->whereKey($stok->getKey())->firstOrFail();
            if ((int) $kilitliStok->firma_id !== $firmaId) {
                throw new IsKuraliIstisnasi('Stok kartı farklı firmaya ait.');
            }
            if (! $kilitliStok->partiTakibiAktifMi()) {
                throw new IsKuraliIstisnasi('Bu ürün için parti takibi açık değil.');
            }

            $mevcut = (string) ($kilitliStok->stok_miktari ?? '0');
            if (bccomp($mevcut, '0', 8) <= 0) {
                throw new IsKuraliIstisnasi('Partiye aktarılacak mevcut stok bulunmuyor.');
            }

            $aktifPartiVar = StokParcasi::query()
                ->where('firma_id', $firmaId)
                ->where('stok_id', $kilitliStok->id)
                ->where('kalan_miktar', '>', 0)
                ->exists();
            if ($aktifPartiVar) {
                throw new IsKuraliIstisnasi('Bu ürünün zaten aktif parti kayıtları var.');
            }

            $parcaKodu = trim((string) ($alanlar['parca_kodu'] ?? ''));
            if ($parcaKodu === '') {
                throw new IsKuraliIstisnasi('Parti / Lot No girilmelidir.');
            }

            $miktar = $this->normalizePozitifMiktar($alanlar['miktar'] ?? $mevcut);
            if (bccomp($miktar, $mevcut, 8) !== 0) {
                throw new IsKuraliIstisnasi('Aktarım miktarı mevcut stokla aynı olmalıdır.');
            }

            $depoId = (int) ($alanlar['depo_id'] ?? ($kilitliStok->depo_id ?? 0));
            if ($depoId > 0 && ! Depo::query()->where('firma_id', $firmaId)->whereKey($depoId)->where('aktif_mi', true)->exists()) {
                throw new IsKuraliIstisnasi('Seçilen depo aktif firmaya ait değil veya aktif değil.');
            }

            if (StokParcasi::query()
                ->where('firma_id', $firmaId)
                ->where('stok_id', $kilitliStok->id)
                ->where('depo_id', $depoId > 0 ? $depoId : null)
                ->where('parca_kodu', $parcaKodu)
                ->exists()) {
                throw new IsKuraliIstisnasi('Bu ürün ve depo için aynı Parti / Lot No zaten kayıtlı.');
            }

            $parti = StokParcasi::query()->create([
                'firma_id' => $firmaId,
                'stok_id' => $kilitliStok->id,
                'depo_id' => $depoId > 0 ? $depoId : null,
                'parca_kodu' => $parcaKodu,
                'parca_kodu' => $alanlar['parca_kodu'] ?? null,
                'barkod' => $alanlar['barkod'] ?? null,
                'parca_mi' => (bool) ($alanlar['parca_mi'] ?? false),
                'parca_durumu' => $alanlar['parca_durumu'] ?? (($alanlar['parca_mi'] ?? false) ? 'aktif' : null),
                'blok_no' => $alanlar['blok_no'] ?? null,
                'ocak_tedarikci' => $alanlar['ocak_tedarikci'] ?? null,
                'kalite_sinifi' => $alanlar['kalite_sinifi'] ?? null,
                'renk_desen' => $alanlar['renk_desen'] ?? null,
                'kalinlik_cm' => $alanlar['kalinlik_cm'] ?? null,
                'metrekare' => $alanlar['metrekare'] ?? null,
                'plaka_no' => $alanlar['plaka_no'] ?? null,
                'parca_no' => $alanlar['parca_no'] ?? null,
                'uretim_tarihi' => $alanlar['uretim_tarihi'] ?? null,
                'son_kullanma_tarihi' => $alanlar['son_kullanma_tarihi'] ?? null,
                'birim_maliyet' => $alanlar['birim_maliyet'] ?? ($kilitliStok->guncel_birim_maliyet ?? 0),
                'giren_miktar' => $miktar,
                'kalan_miktar' => $miktar,
            ]);

            $this->logInfo('stok.parti_mevcut_stok_aktarimi', [
                'firma_id' => $firmaId,
                'stok_id' => (int) $kilitliStok->id,
                'stok_parcasi_id' => (int) $parti->id,
                'miktar' => $miktar,
            ]);

            return $parti;
        });
    }

    /**
     * Depodaki basit stok için sayım sonucunu fark hareketiyle uygular.
     * Parti/seri takipli ürünler kendi özel sayım akışlarından ilerler.
     */
    public function depoSayiminiUygula(
        int $firmaId,
        int $stokId,
        int $depoId,
        string|int|float $hedefMiktar,
        int $belgeId,
        ?string $aciklama = null
    ): StokHareketi {
        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt($firmaId);
        if ($depoId < 1 || $belgeId < 1) {
            throw new IsKuraliIstisnasi('Sayım için depo ve belge bilgisi gereklidir.');
        }

        $hedef = str_replace(',', '.', trim((string) $hedefMiktar));
        if ($hedef === '' || ! is_numeric($hedef) || bccomp($hedef, '0', 8) < 0) {
            throw new IsKuraliIstisnasi('Sayım miktarı sıfırdan küçük olamaz.');
        }

        return $this->retryableTransaction(function () use ($firmaId, $stokId, $depoId, $hedef, $belgeId, $aciklama): StokHareketi {
            $stok = StokKarti::query()->lockForUpdate()->whereKey($stokId)->firstOrFail();
            if ((int) $stok->firma_id !== $firmaId) {
                throw new IsKuraliIstisnasi('Stok kartı farklı firmaya ait.');
            }
            if ($stok->partiTakibiAktifMi() || (string) ($stok->stok_takip_tipi ?? '') === StokKarti::STOK_TAKIP_TIPI_SERI) {
                throw new IsKuraliIstisnasi('Parti veya seri takipli ürünlerde özel sayım akışı kullanılmalıdır.');
            }

            $bakiye = StokDepoBakiyesi::query()
                ->where('firma_id', $firmaId)
                ->where('depo_id', $depoId)
                ->where('stok_id', $stokId)
                ->lockForUpdate()
                ->first();
            if (! $bakiye) {
                $bakiye = StokDepoBakiyesi::query()->create([
                    'firma_id' => $firmaId,
                    'depo_id' => $depoId,
                    'stok_id' => $stokId,
                    'miktar' => '0',
                    'rezerve_miktar' => '0',
                ]);
            }

            $mevcut = (string) ($bakiye->miktar ?? 0);
            $fark = bcsub($hedef, $mevcut, 8);
            if (bccomp($fark, '0', 8) === 0) {
                throw new IsKuraliIstisnasi('Sayım miktarı mevcut depo miktarıyla aynı.');
            }

            $giris = bccomp($fark, '0', 8) > 0;
            return $this->kayitOlustur($firmaId, [
                'stok_id' => $stokId,
                'depo_id' => $depoId,
                'islem_turu' => $giris ? StokHareketIslemTuru::Acilis : StokHareketIslemTuru::Satis,
                'miktar' => ltrim($fark, '+-'),
                'birim_fiyat' => $stok->guncel_birim_maliyet ?? 0,
                'birim_maliyet' => $stok->guncel_birim_maliyet ?? 0,
                'belge_turu' => StokBelgeTuru::Sayim,
                'belge_id' => $belgeId,
                'referans_tipi' => StokBelgeTuru::Sayim->value,
                'referans_id' => $belgeId,
                'aciklama' => $aciklama ?: 'Depo stok sayımı düzeltmesi',
                'tarih' => now(),
                'negatif_stok_izinli' => false,
            ]);
        });
    }

    /** Sayım sonucundaki parti miktarına ulaşmak için fark hareketi oluşturur. */
    public function partiMiktariniDuzelt(int $firmaId, StokParcasi $parti, string|int|float $hedefMiktar, ?string $aciklama = null): StokHareketi
    {
        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt($firmaId);
        $hedef = str_replace(',', '.', trim((string) $hedefMiktar));
        if ($hedef === '' || ! is_numeric($hedef) || bccomp($hedef, '0', 8) < 0) {
            throw new IsKuraliIstisnasi('Sayım miktarı sıfırdan küçük olamaz.');
        }

        $parti->loadMissing('stokKarti');
        if ((int) $parti->firma_id !== $firmaId || ! $parti->stokKarti || ! $parti->stokKarti->partiTakibiAktifMi()) {
            throw new IsKuraliIstisnasi('Geçerli bir parti kaydı seçilmelidir.');
        }

        $fark = bcsub($hedef, (string) $parti->kalan_miktar, 8);
        if (bccomp($fark, '0', 8) === 0) {
            throw new IsKuraliIstisnasi('Sayım miktarı mevcut parti miktarıyla aynı.');
        }

        $giris = bccomp($fark, '0', 8) > 0;
        return $this->kayitOlustur($firmaId, [
            'stok_id' => (int) $parti->stok_id,
            'depo_id' => (int) ($parti->depo_id ?? 0),
            'islem_turu' => $giris ? StokHareketIslemTuru::Acilis : StokHareketIslemTuru::Satis,
            'miktar' => ltrim($fark, '+-'),
            'birim_fiyat' => $parti->birim_maliyet,
            'birim_maliyet' => $parti->birim_maliyet,
            'parca_kodu' => $parti->parca_kodu,
            'belge_turu' => StokBelgeTuru::Duzeltme,
            'belge_id' => (int) $parti->id,
            'referans_tipi' => StokBelgeTuru::Duzeltme->value,
            'referans_id' => (int) $parti->id,
            'aciklama' => $aciklama ?: 'Parti sayım düzeltmesi',
            'tarih' => now(),
            'negatif_stok_izinli' => false,
        ]);
    }

    public function tersKayitOlustur(StokHareketi $hareket, ?string $aciklama = null): StokHareketi
    {
        return $this->retryableTransaction(function () use ($hareket, $aciklama): StokHareketi {
            $kilitliHareket = StokHareketi::query()->lockForUpdate()->whereKey($hareket->getKey())->firstOrFail();
            if ($kilitliHareket->durum !== StokHareketDurumu::Aktif) {
                throw new IsKuraliIstisnasi('Yalnızca aktif stok hareketi terslenebilir.');
            }
            $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt((int) $kilitliHareket->firma_id);
            $stok = StokKarti::query()->lockForUpdate()->whereKey($kilitliHareket->stok_id)->firstOrFail();
            $kilitliHareket->update(['durum' => StokHareketDurumu::Iptal]);

            $tersIslem = match ($kilitliHareket->islem_turu) {
                StokHareketIslemTuru::Acilis => StokHareketIslemTuru::AcilisIptali,
                StokHareketIslemTuru::AcilisIptali => StokHareketIslemTuru::Acilis,
                StokHareketIslemTuru::Alis => StokHareketIslemTuru::Satis,
                StokHareketIslemTuru::Satis => StokHareketIslemTuru::Alis,
                StokHareketIslemTuru::Iade,
                StokHareketIslemTuru::SatisIadesi => StokHareketIslemTuru::Satis,
                StokHareketIslemTuru::AlisIadesi => StokHareketIslemTuru::Alis,
                StokHareketIslemTuru::TransferGiris => StokHareketIslemTuru::TransferCikis,
                StokHareketIslemTuru::TransferCikis => StokHareketIslemTuru::TransferGiris,
            };
            $onceki = (string) ($stok->stok_miktari ?? 0);
            $delta = $this->miktarDelta($tersIslem, (string) $kilitliHareket->miktar);
            $sonraki = bcadd($onceki, $delta, 8);
            $this->negatifStokKontrolEt(
                $stok,
                $tersIslem,
                $onceki,
                $sonraki,
                false,
                (int) $kilitliHareket->firma_id
            );
            $kaynakGirisMi = in_array($kilitliHareket->islem_turu, [
                StokHareketIslemTuru::Acilis,
                StokHareketIslemTuru::Alis,
                StokHareketIslemTuru::Iade,
                StokHareketIslemTuru::SatisIadesi,
                StokHareketIslemTuru::TransferGiris,
            ], true);
            $kaynakToplamMaliyet = (string) ($kilitliHareket->toplam_maliyet ?: bcmul(
                (string) $kilitliHareket->miktar,
                (string) ($kilitliHareket->birim_maliyet ?: $kilitliHareket->birim_fiyat),
                self::PARA_BASAMAK
            ));
            $yeniStokDegeri = $kaynakGirisMi
                ? bcsub((string) ($stok->stok_degeri ?? 0), $kaynakToplamMaliyet, self::PARA_BASAMAK)
                : bcadd((string) ($stok->stok_degeri ?? 0), $kaynakToplamMaliyet, self::PARA_BASAMAK);
            if (bccomp($sonraki, '0', 8) === 0) {
                $yeniStokDegeri = '0';
                $yeniOrtalama = '0';
            } else {
                $yeniOrtalama = bcdiv($yeniStokDegeri, $sonraki, self::PARA_BASAMAK);
            }
            $sonGirisMaliyeti = $kaynakGirisMi
                ? StokHareketi::query()
                    ->where('firma_id', $kilitliHareket->firma_id)
                    ->where('stok_id', $kilitliHareket->stok_id)
                    ->where('durum', StokHareketDurumu::Aktif)
                    ->whereIn('islem_turu', [
                        StokHareketIslemTuru::Acilis->value,
                        StokHareketIslemTuru::Alis->value,
                        StokHareketIslemTuru::Iade->value,
                        StokHareketIslemTuru::SatisIadesi->value,
                        StokHareketIslemTuru::TransferGiris->value,
                    ])
                    ->latest('islem_tarihi')
                    ->latest('id')
                    ->value('birim_maliyet')
                : (string) ($kilitliHareket->birim_maliyet ?: $stok->son_giris_maliyeti);

            $stok->update([
                'stok_miktari' => $sonraki,
                'guncel_birim_maliyet' => $yeniOrtalama,
                'stok_degeri' => $yeniStokDegeri,
                'son_giris_maliyeti' => $sonGirisMaliyeti,
                'son_hareket_tarihi' => now(),
                'negative_flag' => bccomp($sonraki, '0', 8) < 0,
            ]);

            $this->depoBakiyesiniGuncelle(
                (int) $kilitliHareket->firma_id,
                (int) ($kilitliHareket->depo_id ?? 0),
                (int) $stok->id,
                $delta
            );

            $ters = StokHareketi::query()->create([
                'firma_id' => $kilitliHareket->firma_id,
                'cari_id' => $kilitliHareket->cari_id,
                'stok_id' => $kilitliHareket->stok_id,
                'depo_id' => $kilitliHareket->depo_id,
                'islem_turu' => $tersIslem,
                'miktar' => $kilitliHareket->miktar,
                'onceki_miktar' => $onceki,
                'sonraki_miktar' => $sonraki,
                'birim_fiyat' => $kilitliHareket->birim_fiyat,
                'birim_maliyet' => $kilitliHareket->birim_maliyet ?: $kilitliHareket->birim_fiyat,
                'toplam' => $kilitliHareket->toplam,
                'toplam_maliyet' => $kilitliHareket->toplam_maliyet ?: $kilitliHareket->toplam,
                'belge_turu' => $kilitliHareket->belge_turu,
                'referans_tipi' => $kilitliHareket->referans_tipi ?: $kilitliHareket->belge_turu->value,
                'belge_id' => $kilitliHareket->belge_id,
                'referans_id' => $kilitliHareket->referans_id ?: $kilitliHareket->belge_id,
                'tarih' => now(),
                'islem_tarihi' => now(),
                'durum' => StokHareketDurumu::Aktif,
                'aciklama' => $aciklama ?: 'Ters kayıt',
                'iptal_edilen_hareket_id' => $kilitliHareket->getKey(),
            ]);

            $this->tersPartiVeSeriHareketleriniUygula($kilitliHareket, $ters);

            $this->logWarning('stok_hareketi.ters_kayit', [
                'firma_id' => (int) $kilitliHareket->firma_id,
                'stok_id' => (int) $kilitliHareket->stok_id,
                'kaynak_hareket_id' => (int) $kilitliHareket->id,
                'ters_hareket_id' => (int) $ters->id,
                'onceki_miktar' => $onceki,
                'sonraki_miktar' => $sonraki,
            ]);

            return $ters;
        }, 3, [
            'firma_id' => (int) $hareket->firma_id,
            'islem' => 'ters_kayit',
            'stok_id' => (int) $hareket->stok_id,
        ]);
    }

    private function normalizePozitifMiktar(mixed $deger): string
    {
        $miktar = (string) $deger;
        if (! is_numeric($miktar) || bccomp($miktar, '0', 8) <= 0) {
            throw new IsKuraliIstisnasi('Miktar sıfırdan büyük olmalıdır.');
        }

        return $miktar;
    }

    private function normalizeSifirVeyaPozitifTutar(mixed $deger, string $hata): string
    {
        $tutar = (string) $deger;
        if (! is_numeric($tutar) || bccomp($tutar, '0', self::PARA_BASAMAK) < 0) {
            throw new IsKuraliIstisnasi($hata);
        }

        return $tutar;
    }

    private function negatifStokKontrolEt(
        StokKarti $stok,
        StokHareketIslemTuru $islemTuru,
        string $onceki,
        string $sonraki,
        bool $negatifStokIzinliOverride = false,
        ?int $firmaId = null
    ): void
    {
        if (! (bool) ($stok->stok_takip ?? true)) {
            return;
        }

        if (bccomp($sonraki, '0', 8) >= 0) {
            return;
        }

        $firmaNegatifStokIzinli = $firmaId !== null
            && (bool) $this->firmaAyarDeposu->oku($firmaId, 'negatif_stok_izinli', false);

        if ($negatifStokIzinliOverride
            || $firmaNegatifStokIzinli
            || (bool) config('muhasebe.stok.negatif_stok_izinli', false)) {
            $this->logWarning('stok.negatif_olustu', [
                'stok_id' => (int) $stok->id,
                'firma_id' => (int) $stok->firma_id,
                'islem_turu' => $islemTuru->value,
                'onceki_miktar' => $onceki,
                'sonraki_miktar' => $sonraki,
            ]);
            $this->sistemOlayServisi->olayKaydet('stok.negatif_olustu', 'error', 'Negatif stok olustu.', [
                'firma_id' => (int) $stok->firma_id,
                'stok_id' => (int) $stok->id,
            ]);
            $this->logWarning('stok.negatif_kritik', [
                'stok_id' => (int) $stok->id,
                'firma_id' => (int) $stok->firma_id,
                'miktar' => $sonraki,
            ]);
            $esik = config('muhasebe.stok.negatif_stok_kritik_esik');
            if ($esik !== null && $esik !== '' && is_numeric((string) $esik)) {
                $mutlak = ltrim($sonraki, '-');
                if (bccomp($mutlak, (string) $esik, 8) >= 0) {
                    $this->logError('stok.negatif_kritik_asildi', [
                        'stok_id' => (int) $stok->id,
                        'firma_id' => (int) $stok->firma_id,
                        'miktar' => $sonraki,
                        'esik' => (string) $esik,
                    ]);
                }
            }

            return;
        }
        $this->logWarning('stok_hareketi.negatif_stok_engellendi', [
            'stok_id' => (int) $stok->id,
            'firma_id' => (int) $stok->firma_id,
            'islem_turu' => $islemTuru->value,
            'onceki_miktar' => $onceki,
            'sonraki_miktar' => $sonraki,
        ]);
        $this->sistemOlayServisi->olayKaydet('stok.negatif_engellendi', 'warning', 'Negatif stok olusumu engellendi.', [
            'firma_id' => (int) $stok->firma_id,
            'stok_id' => (int) $stok->id,
        ]);

        throw new IsKuraliIstisnasi('Bu işlem stok miktarını negatife düşürür. Lütfen miktarı kontrol edin.');
    }

    private function miktarDelta(StokHareketIslemTuru $islemTuru, string $miktar): string
    {
        return match ($islemTuru) {
            StokHareketIslemTuru::Acilis,
            StokHareketIslemTuru::Alis,
            StokHareketIslemTuru::Iade,
            StokHareketIslemTuru::SatisIadesi,
            StokHareketIslemTuru::TransferGiris => $miktar,
            StokHareketIslemTuru::Satis,
            StokHareketIslemTuru::AcilisIptali,
            StokHareketIslemTuru::AlisIadesi,
            StokHareketIslemTuru::TransferCikis => bcmul($miktar, '-1', 8),
        };
    }

    private function depoBakiyesiniGuncelle(int $firmaId, int $depoId, int $stokId, string $delta): void
    {
        if ($depoId < 1) {
            return;
        }

        $bakiye = StokDepoBakiyesi::query()
            ->where('firma_id', $firmaId)
            ->where('depo_id', $depoId)
            ->where('stok_id', $stokId)
            ->lockForUpdate()
            ->first();

        if (! $bakiye) {
            $bakiye = StokDepoBakiyesi::query()->create([
                'firma_id' => $firmaId,
                'depo_id' => $depoId,
                'stok_id' => $stokId,
                'miktar' => '0',
                'rezerve_miktar' => '0',
            ]);
        }

        $bakiye->update(['miktar' => bcadd((string) $bakiye->miktar, $delta, 8)]);
    }

    /**
     * Parti takibi açık ürünlerde gelen hareketi partiye ekler, çıkan hareketi
     * FIFO sırasıyla partilerden düşer. Parti takibi kapalı ürünlerde no-op'tur.
     *
     * @return array<int, StokHareketiParcasi>
     */
    private function partiHareketiniUygula(
        StokKarti $stok,
        StokHareketi $hareket,
        int $depoId,
        string $miktar,
        StokHareketIslemTuru $islemTuru,
        array $alanlar
    ): array {
        // Parti/Lot ve fiziksel stok parçası hareketleri devre dışıdır.
        // Eski uygulama akışı aşağıda tutulsa da hiçbir yeni işlem bu bölüme
        // fiziksel stok tablosu sorgusu yaptırmamalıdır.
        return [];

        $parcaTransferiIds = array_values(array_unique(array_filter(array_map('intval', (array) ($alanlar['parca_transferi_ids'] ?? [])))));
        if ($islemTuru === StokHareketIslemTuru::TransferCikis && $parcaTransferiIds !== []) {
            $parcalar = StokParcasi::query()->where('firma_id', $stok->firma_id)->where('stok_id', $stok->id)
                ->where('depo_id', $depoId)->where('parca_mi', true)->where('kalan_miktar', '>', 0)
                ->whereIn('id', $parcaTransferiIds)->lockForUpdate()->get();
            if ($parcalar->count() !== count($parcaTransferiIds)) {
                throw new IsKuraliIstisnasi('Transfer için seçilen stok parçalarından biri kaynak depoda bulunmuyor.');
            }
            $toplam = $parcalar->reduce(fn (string $sum, StokParcasi $parca): string => bcadd($sum, (string) $parca->kalan_miktar, 8), '0');
            if (bccomp($toplam, $miktar, 8) !== 0) {
                throw new IsKuraliIstisnasi('Seçilen stok parçalarının toplamı transfer miktarıyla aynı olmalıdır.');
            }
            return $parcalar->map(fn (StokParcasi $parca): StokHareketiParcasi => StokHareketiParcasi::query()->create([
                'firma_id' => $stok->firma_id, 'stok_hareketi_id' => $hareket->id, 'stok_parcasi_id' => $parca->id,
                'miktar' => $parca->kalan_miktar, 'birim_maliyet' => $parca->birim_maliyet,
            ]))->all();
        }

        $girisMi = in_array($islemTuru, [
            StokHareketIslemTuru::Acilis,
            StokHareketIslemTuru::Alis,
            StokHareketIslemTuru::Iade,
            StokHareketIslemTuru::SatisIadesi,
            StokHareketIslemTuru::TransferGiris,
        ], true);

        if ($girisMi) {
            $devirler = is_array($alanlar['parti_devirleri'] ?? null)
                ? $alanlar['parti_devirleri']
                : [];
            if ($devirler !== []) {
                $sonuclar = [];
                foreach ($devirler as $devir) {
                    $sonuclar[] = $this->partiGirisiniUygula($stok, $hareket, $depoId, [
                        'parca_kodu' => $devir['parca_kodu'] ?? '',
                        'parca_kodu' => $devir['parca_kodu'] ?? null,
                        'barkod' => $devir['barkod'] ?? null,
                        'parca_mi' => (bool) ($devir['parca_mi'] ?? false),
                        'parca_durumu' => $devir['parca_durumu'] ?? null,
                        'blok_no' => $devir['blok_no'] ?? null,
                        'ocak_tedarikci' => $devir['ocak_tedarikci'] ?? null,
                        'kalite_sinifi' => $devir['kalite_sinifi'] ?? null,
                        'renk_desen' => $devir['renk_desen'] ?? null,
                        'kalinlik_cm' => $devir['kalinlik_cm'] ?? null,
                        'metrekare' => $devir['metrekare'] ?? null,
                        'plaka_no' => $devir['plaka_no'] ?? null,
                        'parca_no' => $devir['parca_no'] ?? null,
                        'uretim_tarihi' => $devir['uretim_tarihi'] ?? null,
                        'son_kullanma_tarihi' => $devir['son_kullanma_tarihi'] ?? null,
                        'birim_maliyet' => $devir['birim_maliyet'] ?? $hareket->birim_maliyet,
                        'miktar' => $devir['miktar'] ?? 0,
                    ]);
                }

                return $sonuclar;
            }

            $parcaKodu = trim((string) ($alanlar['parca_kodu'] ?? ''));
            if ($parcaKodu === '') {
                return [];
            }

            return [$this->partiGirisiniUygula($stok, $hareket, $depoId, [
                'parca_kodu' => $parcaKodu,
                'blok_no' => $alanlar['blok_no'] ?? null,
                'ocak_tedarikci' => $alanlar['ocak_tedarikci'] ?? null,
                'kalite_sinifi' => $alanlar['kalite_sinifi'] ?? null,
                'renk_desen' => $alanlar['renk_desen'] ?? null,
                'kalinlik_cm' => $alanlar['kalinlik_cm'] ?? null,
                'metrekare' => $alanlar['metrekare'] ?? null,
                'plaka_no' => $alanlar['plaka_no'] ?? null,
                'parca_no' => $alanlar['parca_no'] ?? null,
                'uretim_tarihi' => $alanlar['uretim_tarihi'] ?? null,
                'son_kullanma_tarihi' => $alanlar['son_kullanma_tarihi'] ?? null,
                'birim_maliyet' => $alanlar['birim_maliyet'] ?? $hareket->birim_maliyet,
                'miktar' => $miktar,
            ])];
        }

        $sonKullanmaEngeli = $this->sonKullanmaEngeliAktifMi($stok);
        $parcaDagilimi = is_array($alanlar['parca_dagilimi'] ?? null) ? $alanlar['parca_dagilimi'] : [];
        if ($parcaDagilimi !== []) {
            $toplamDagilim = '0';
            $sonuclar = [];
            $gorulenPartiler = [];
            foreach ($parcaDagilimi as $satir) {
                $parcaKodu = trim((string) ($satir['parca_kodu'] ?? ''));
                $satirMiktar = $this->normalizePozitifMiktar($satir['miktar'] ?? null);
                if ($parcaKodu === '') {
                    throw new IsKuraliIstisnasi('Parti dağılımında Parti / Lot No boş bırakılamaz.');
                }
                if (isset($gorulenPartiler[$parcaKodu])) {
                    throw new IsKuraliIstisnasi('Aynı Parti / Lot No dağılımda birden fazla kez kullanılamaz.');
                }
                $gorulenPartiler[$parcaKodu] = true;
                $parti = StokParcasi::query()
                    ->where('firma_id', $stok->firma_id)
                    ->where('stok_id', $stok->id)
                    ->where('depo_id', $depoId > 0 ? $depoId : null)
                    ->where('parca_kodu', $parcaKodu)
                    ->where('kalan_miktar', '>', 0)
                    ->when($sonKullanmaEngeli, fn ($query) => $query->where(function ($inner): void {
                        $inner->whereNull('son_kullanma_tarihi')->orWhereDate('son_kullanma_tarihi', '>=', now()->toDateString());
                    }))
                    ->lockForUpdate()
                    ->first();
                if (! $parti) {
                    throw new IsKuraliIstisnasi('Parti dağılımındaki '.$parcaKodu.' bu depoda stokta bulunmuyor.');
                }
                if (bccomp((string) $parti->kalan_miktar, $satirMiktar, 8) < 0) {
                    throw new IsKuraliIstisnasi('Parti dağılımındaki '.$parcaKodu.' için yeterli stok bulunmuyor.');
                }
                $this->partiKalaniniGuncelle($parti, bcsub((string) $parti->kalan_miktar, $satirMiktar, 8));
                $toplamDagilim = bcadd($toplamDagilim, $satirMiktar, 8);
                $sonuclar[] = ['parti' => $parti, 'miktar' => $satirMiktar];
            }
            if (bccomp($toplamDagilim, $miktar, 8) !== 0) {
                throw new IsKuraliIstisnasi('Parti dağılımı toplamı satır miktarıyla aynı olmalıdır.');
            }
            return array_map(fn (array $satir): StokHareketiParcasi => StokHareketiParcasi::query()->create([
                'firma_id' => $stok->firma_id,
                'stok_hareketi_id' => $hareket->id,
                'stok_parcasi_id' => $satir['parti']->id,
                'miktar' => $satir['miktar'],
                'birim_maliyet' => $satir['parti']->birim_maliyet,
            ]), $sonuclar);
        }

        // Fiziksel stok parçaları mevcutsa satışın hangi parçadan yapıldığı
        // belirsiz bırakılamaz; kullanıcı parça/parti dağılımını seçmelidir.
        $parcaTakibiVar = StokParcasi::query()
            ->where('firma_id', $stok->firma_id)
            ->where('stok_id', $stok->id)
            ->where('depo_id', $depoId > 0 ? $depoId : null)
            ->where('parca_mi', true)
            ->where('kalan_miktar', '>', 0)
            ->exists();
        if ($parcaTakibiVar && $islemTuru === StokHareketIslemTuru::Satis) {
            throw new IsKuraliIstisnasi('Bu stokta aktif stok parçaları var. Satış için en az bir stok parçası seçip dağılım miktarını girin.');
        }

        $manuelPartiNo = trim((string) ($alanlar['parca_kodu'] ?? ''));
        if ($manuelPartiNo !== '') {
            $parti = StokParcasi::query()
                ->where('firma_id', $stok->firma_id)
                ->where('stok_id', $stok->id)
                ->where('depo_id', $depoId > 0 ? $depoId : null)
                ->where('parca_kodu', $manuelPartiNo)
                ->where('kalan_miktar', '>', 0)
                ->when($sonKullanmaEngeli, fn ($query) => $query->where(function ($inner): void {
                    $inner->whereNull('son_kullanma_tarihi')->orWhereDate('son_kullanma_tarihi', '>=', now()->toDateString());
                }))
                ->lockForUpdate()
                ->first();

            if (! $parti) {
                throw new IsKuraliIstisnasi('Seçilen Parti / Lot No bu depoda stokta bulunmuyor.');
            }
            if (bccomp((string) $parti->kalan_miktar, $miktar, 8) < 0) {
                throw new IsKuraliIstisnasi('Seçilen partide yeterli stok bulunmuyor.');
            }

            $this->partiKalaniniGuncelle($parti, bcsub((string) $parti->kalan_miktar, $miktar, 8));

            return [StokHareketiParcasi::query()->create([
                'firma_id' => $stok->firma_id,
                'stok_hareketi_id' => $hareket->id,
                'stok_parcasi_id' => $parti->id,
                'miktar' => $miktar,
                'birim_maliyet' => $parti->birim_maliyet,
            ])];
        }

        $kalan = $miktar;
        $partiler = StokParcasi::query()
            ->where('firma_id', $stok->firma_id)
            ->where('stok_id', $stok->id)
            ->where('depo_id', $depoId > 0 ? $depoId : null)
            ->where('kalan_miktar', '>', 0)
            ->when($sonKullanmaEngeli, fn ($query) => $query->where(function ($inner): void {
                $inner->whereNull('son_kullanma_tarihi')->orWhereDate('son_kullanma_tarihi', '>=', now()->toDateString());
            }))
            ->lockForUpdate()
            ->orderByRaw('son_kullanma_tarihi IS NULL')
            ->orderBy('son_kullanma_tarihi')
            ->orderBy('id')
            ->get();

        $sonuclar = [];
        foreach ($partiler as $parti) {
            if (bccomp($kalan, '0', 8) <= 0) {
                break;
            }

            $dusulecek = bccomp((string) $parti->kalan_miktar, $kalan, 8) >= 0
                ? $kalan
                : (string) $parti->kalan_miktar;
            $this->partiKalaniniGuncelle($parti, bcsub((string) $parti->kalan_miktar, $dusulecek, 8));
            $sonuclar[] = StokHareketiParcasi::query()->create([
                'firma_id' => $stok->firma_id,
                'stok_hareketi_id' => $hareket->id,
                'stok_parcasi_id' => $parti->id,
                'miktar' => $dusulecek,
                'birim_maliyet' => $parti->birim_maliyet,
            ]);
            $kalan = bcsub($kalan, $dusulecek, 8);
        }

        if (bccomp($kalan, '0', 8) > 0) {
            $mesaj = $sonKullanmaEngeli
                ? 'Son kullanma tarihi uygun partilerde yeterli stok bulunmuyor.'
                : 'Seçili depodaki partiler hareket miktarını karşılamıyor.';

            throw new IsKuraliIstisnasi($mesaj);
        }

        return $sonuclar;
    }

    private function sonKullanmaEngeliAktifMi(StokKarti $stok): bool
    {
        return (string) $this->firmaAyarDeposu->oku((int) $stok->firma_id, 'stok_son_kullanma_tarihi_kurali', 'uyar') === 'engelle';
    }

    private function partiKalaniniGuncelle(StokParcasi $parti, string $yeniKalan): void
    {
        $alanlar = ['kalan_miktar' => $yeniKalan];
        if ($parti->parca_mi) {
            $alanlar['parca_durumu'] = bccomp($yeniKalan, '0', 8) <= 0
                ? 'tukenmis'
                : (bccomp($yeniKalan, (string) $parti->giren_miktar, 8) < 0 ? 'kismi' : 'aktif');
        }
        $parti->update($alanlar);
    }

    /** @param array<string, mixed> $alanlar */
    private function partiGirisiniUygula(StokKarti $stok, StokHareketi $hareket, int $depoId, array $alanlar): StokHareketiParcasi
    {
        $parcaKodu = trim((string) ($alanlar['parca_kodu'] ?? ''));
        $miktar = $this->normalizePozitifMiktar($alanlar['miktar'] ?? null);
        $parti = StokParcasi::query()
            ->where('firma_id', $stok->firma_id)
            ->where('stok_id', $stok->id)
            ->where('depo_id', $depoId > 0 ? $depoId : null)
            ->where('parca_kodu', $parcaKodu)
            ->lockForUpdate()
            ->first();

        if (! $parti) {
            $parti = StokParcasi::query()->create([
                'firma_id' => $stok->firma_id,
                'stok_id' => $stok->id,
                'depo_id' => $depoId > 0 ? $depoId : null,
                'parca_kodu' => $parcaKodu,
                'blok_no' => $alanlar['blok_no'] ?? null,
                'ocak_tedarikci' => $alanlar['ocak_tedarikci'] ?? null,
                'kalite_sinifi' => $alanlar['kalite_sinifi'] ?? null,
                'renk_desen' => $alanlar['renk_desen'] ?? null,
                'kalinlik_cm' => $alanlar['kalinlik_cm'] ?? null,
                'metrekare' => $alanlar['metrekare'] ?? null,
                'plaka_no' => $alanlar['plaka_no'] ?? null,
                'parca_no' => $alanlar['parca_no'] ?? null,
                'uretim_tarihi' => $alanlar['uretim_tarihi'] ?? null,
                'son_kullanma_tarihi' => $alanlar['son_kullanma_tarihi'] ?? null,
                'birim_maliyet' => $alanlar['birim_maliyet'] ?? $hareket->birim_maliyet,
                'giren_miktar' => $miktar,
                'kalan_miktar' => $miktar,
            ]);
        } else {
            $parti->update([
                'giren_miktar' => bcadd((string) $parti->giren_miktar, $miktar, 8),
                'kalan_miktar' => bcadd((string) $parti->kalan_miktar, $miktar, 8),
                'birim_maliyet' => $alanlar['birim_maliyet'] ?? $parti->birim_maliyet,
                'uretim_tarihi' => $parti->uretim_tarihi ?: ($alanlar['uretim_tarihi'] ?? null),
                'son_kullanma_tarihi' => $parti->son_kullanma_tarihi ?: ($alanlar['son_kullanma_tarihi'] ?? null),
                'blok_no' => $parti->blok_no ?: ($alanlar['blok_no'] ?? null),
                'ocak_tedarikci' => $parti->ocak_tedarikci ?: ($alanlar['ocak_tedarikci'] ?? null),
                'kalite_sinifi' => $parti->kalite_sinifi ?: ($alanlar['kalite_sinifi'] ?? null),
                'renk_desen' => $parti->renk_desen ?: ($alanlar['renk_desen'] ?? null),
                'kalinlik_cm' => $parti->kalinlik_cm ?: ($alanlar['kalinlik_cm'] ?? null),
                'metrekare' => $parti->metrekare ?: ($alanlar['metrekare'] ?? null),
                'plaka_no' => $parti->plaka_no ?: ($alanlar['plaka_no'] ?? null),
                'parca_no' => $parti->parca_no ?: ($alanlar['parca_no'] ?? null),
            ]);
        }

        return StokHareketiParcasi::query()->create([
            'firma_id' => $stok->firma_id,
            'stok_hareketi_id' => $hareket->id,
            'stok_parcasi_id' => $parti->id,
            'miktar' => $miktar,
            'birim_maliyet' => $parti->birim_maliyet,
        ]);
    }

    /**
     * Seri numarası takibi açık ürünlerde, girilen seri listesini stokta tutar
     * ve çıkışta seri numaralarını stoktan düşürür. Liste boşsa mevcut akış
     * bozulmaz; böylece özellik firmalar için zorunlu hale gelmez.
     */
    private function seriHareketiniUygula(
        StokKarti $stok,
        StokHareketi $hareket,
        int $depoId,
        string $miktar,
        StokHareketIslemTuru $islemTuru,
        array $alanlar
    ): void {
        if ((string) ($stok->stok_takip_tipi ?? StokKarti::STOK_TAKIP_TIPI_BASIT) !== StokKarti::STOK_TAKIP_TIPI_SERI) {
            return;
        }

        $girisMi = in_array($islemTuru, [
            StokHareketIslemTuru::Acilis,
            StokHareketIslemTuru::Alis,
            StokHareketIslemTuru::Iade,
            StokHareketIslemTuru::SatisIadesi,
            StokHareketIslemTuru::TransferGiris,
        ], true);
        $seriListesi = $this->seriListesiniNormalizeEt($alanlar['seri_nolari'] ?? null);

        if ($girisMi) {
            $devirler = is_array($alanlar['seri_devirleri'] ?? null) ? $alanlar['seri_devirleri'] : [];
            if ($devirler !== []) {
                $seriListesi = array_values(array_filter(array_map(
                    static fn (array $devir): string => trim((string) ($devir['seri_no'] ?? '')),
                    $devirler
                )));
            }
            if ($seriListesi === []) {
                return;
            }
            if (count($seriListesi) !== (int) round((float) $miktar)) {
                throw new IsKuraliIstisnasi('Seri No Barkodu adedi, stok miktarı ile aynı olmalıdır.');
            }

            foreach ($seriListesi as $index => $seriNo) {
                $seri = StokSeriNo::query()
                    ->where('firma_id', $stok->firma_id)
                    ->where('seri_no', $seriNo)
                    ->lockForUpdate()
                    ->first();
                if ($seri && ! in_array($seri->durum, ['cikti', 'satildi'], true)) {
                    throw new IsKuraliIstisnasi('Bu Seri No Barkodu zaten stokta kayıtlı: '.$seriNo);
                }
                if ($seri) {
                    $seri->update([
                        'stok_id' => $stok->id,
                        'depo_id' => $depoId > 0 ? $depoId : null,
                        'durum' => 'stokta',
                        'barkod' => $seri->barkod ?: $seriNo,
                    ]);
                } else {
                    $seri = StokSeriNo::query()->create([
                        'firma_id' => $stok->firma_id,
                        'stok_id' => $stok->id,
                        'depo_id' => $depoId > 0 ? $depoId : null,
                        'seri_no' => $seriNo,
                        'barkod' => $seriNo,
                        'durum' => 'stokta',
                        'birim_maliyet' => $alanlar['birim_maliyet'] ?? $hareket->birim_maliyet,
                        'garanti_baslangic_tarihi' => $alanlar['garanti_baslangic_tarihi'] ?? null,
                        'garanti_bitis_tarihi' => $alanlar['garanti_bitis_tarihi'] ?? null,
                    ]);
                }
                StokHareketiSeri::query()->create([
                    'firma_id' => $stok->firma_id,
                    'stok_hareketi_id' => $hareket->id,
                    'stok_seri_no_id' => $seri->id,
                ]);
            }

            return;
        }

        $mevcutSeriler = StokSeriNo::query()
            ->where('firma_id', $stok->firma_id)
            ->where('stok_id', $stok->id)
            ->where('depo_id', $depoId > 0 ? $depoId : null)
            ->where('durum', 'stokta')
            ->lockForUpdate()
            ->get(['seri_no']);

        if ($seriListesi === [] && $mevcutSeriler->isNotEmpty()) {
            throw new IsKuraliIstisnasi('Bu ürünün stokta Seri No Barkodları var. Satış için Seri No Barkodu seçmelisiniz.');
        }
        if ($seriListesi === [] || count($seriListesi) !== (int) round((float) $miktar)) {
            if ($mevcutSeriler->isEmpty() && $seriListesi === []) {
                return;
            }

            throw new IsKuraliIstisnasi('Seçilen Seri No Barkodu adedi, satış miktarı ile aynı olmalıdır.');
        }
        if ($mevcutSeriler->whereIn('seri_no', $seriListesi)->count() !== count($seriListesi)) {
            throw new IsKuraliIstisnasi('Seçilen Seri No Barkodu bu ürünün seçili deposunda stokta değil.');
        }
        if (count(array_unique($seriListesi)) !== count($seriListesi)) {
            throw new IsKuraliIstisnasi('Aynı Seri No Barkodu bir satışta birden fazla seçilemez.');
        }
        if (count($seriListesi) !== (int) round((float) $miktar)) {
            return;
        }

        $kullanilanSeriler = [];
        foreach ($seriListesi as $seriNo) {
            $seri = StokSeriNo::query()
                ->where('firma_id', $stok->firma_id)
                ->where('stok_id', $stok->id)
                ->where('depo_id', $depoId > 0 ? $depoId : null)
                ->where('seri_no', $seriNo)
                ->where('durum', 'stokta')
                ->lockForUpdate()
                ->first();
            if (! $seri) {
                continue;
            }
            $seri->update(['durum' => $islemTuru === StokHareketIslemTuru::Satis ? 'satildi' : 'cikti']);
            $kullanilanSeriler[] = (string) $seri->seri_no;
            StokHareketiSeri::query()->create([
                'firma_id' => $stok->firma_id,
                'stok_hareketi_id' => $hareket->id,
                'stok_seri_no_id' => $seri->id,
            ]);
        }

        if ($kullanilanSeriler !== [] && $hareket->belge_turu === StokBelgeTuru::Fatura) {
            $this->faturaSatirinaSerileriKaydet($stok, $hareket, $kullanilanSeriler);
        }
    }

    /** @return array<int, string> */
    private function seriListesiniNormalizeEt(mixed $deger): array
    {
        $liste = is_array($deger) ? $deger : preg_split('/[\r\n,;]+/', (string) $deger);

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $seri): string => trim((string) $seri),
            $liste ?: []
        ))));
    }

    private function tersPartiVeSeriHareketleriniUygula(StokHareketi $kaynak, StokHareketi $ters): void
    {
        $kaynakGirisMi = in_array($kaynak->islem_turu, [
            StokHareketIslemTuru::Acilis,
            StokHareketIslemTuru::Alis,
            StokHareketIslemTuru::Iade,
            StokHareketIslemTuru::SatisIadesi,
            StokHareketIslemTuru::TransferGiris,
        ], true);

        // Fiziksel parça/parti tabloları kaldırıldı. Bu nedenle ters stok
        // hareketinde artık olmayan parcaHareketleri ilişkisini çağırma;
        // ölçü dağılımları ve seri hareketleri aşağıdaki ortak akışlarla
        // terslenir.

        foreach ($kaynak->seriHareketleri()->lockForUpdate()->get() as $kaynakSeriHareketi) {
            $seri = StokSeriNo::query()->lockForUpdate()->find($kaynakSeriHareketi->stok_seri_no_id);
            if (! $seri) {
                continue;
            }
            $seri->update(['durum' => $kaynakGirisMi ? 'cikti' : 'stokta']);
            StokHareketiSeri::query()->create([
                'firma_id' => $ters->firma_id,
                'stok_hareketi_id' => $ters->id,
                'stok_seri_no_id' => $seri->id,
            ]);
        }
    }

    /** @param array<int, string> $seriNolari */
    private function faturaSatirinaSerileriKaydet(StokKarti $stok, StokHareketi $hareket, array $seriNolari): void
    {
        $kalem = FaturaKalemi::query()
            ->where('firma_id', $hareket->firma_id)
            ->where('fatura_id', $hareket->belge_id)
            ->where('stok_id', $stok->id)
            ->orderBy('id')
            ->get()
            ->first(function (FaturaKalemi $kalem): bool {
                return empty($kalem->seri_nolari);
            });

        if (! $kalem) {
            return;
        }

        $kalem->update(['seri_nolari' => array_values(array_unique($seriNolari))]);
    }

    private function logInfo(string $mesaj, array $baglam): void
    {
        $kanal = Log::channel((string) config('muhasebe.stok.log_channel', 'muhasebe'));
        $seviye = (string) config('muhasebe.stok.olusturma_log_seviyesi', 'debug');
        if ($seviye === 'info') {
            $kanal->info($mesaj, $baglam);

            return;
        }

        $kanal->debug($mesaj, $baglam);
    }

    private function logWarning(string $mesaj, array $baglam): void
    {
        Log::channel((string) config('muhasebe.stok.log_channel', 'muhasebe'))->warning($mesaj, $baglam);
    }

    private function logError(string $mesaj, array $baglam): void
    {
        Log::channel((string) config('muhasebe.stok.log_channel', 'muhasebe'))->error($mesaj, $baglam);
    }

    /**
     * @param  array<string, mixed>  $ozetBaglam
     */
    protected function retryableTransaction(callable $callback, int $maxRetry = 3, array $ozetBaglam = []): mixed
    {
        $deadlockRetries = 0;
        $attempt = 0;
        basla:
        $attempt++;
        try {
            $sonuc = DB::transaction($callback, 1);
            if ($deadlockRetries > 0) {
                try {
                    Cache::increment('muhasebe:metrics:deadlock_retry_count', $deadlockRetries);
                } catch (Throwable) {
                    // Önbellek yoksa metrik atlanır.
                }
                $this->logWarning('stok.deadlock.retry_ozet', array_merge($ozetBaglam, [
                    'deadlock_retry_count' => $deadlockRetries,
                ]));
            }

            return $sonuc;
        } catch (Throwable $e) {
            if ($attempt < $maxRetry && $this->isDeadlock($e)) {
                $deadlockRetries++;
                $this->logWarning('stok_hareketi.deadlock_retry', array_merge($ozetBaglam, [
                    'attempt' => $attempt,
                    'max_retry' => $maxRetry,
                ]));
                goto basla;
            }

            throw $e;
        }
    }

    protected function isDeadlock(Throwable $e): bool
    {
        if (! $e instanceof QueryException) {
            return false;
        }

        $sqlState = $e->errorInfo[0] ?? null;
        $driverCode = (int) ($e->errorInfo[1] ?? 0);
        $msg = mb_strtolower($e->getMessage());

        return in_array($sqlState, ['40001', '40P01'], true)
            || in_array($driverCode, [1205, 1213], true)
            || str_contains($msg, 'deadlock')
            || str_contains($msg, 'serialization failure');
    }
}
