<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\FaturaKalemi;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\StokOlcusu;
use App\Models\Proje\IsletmeProjesi;
use App\Muhasebe\Enumlar\CariHareketBelgeTuru;
use App\Muhasebe\Enumlar\CariHareketDurumu;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FaturaSinifi;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Muhasebe\Enumlar\FinansHareketDurumu;
use App\Muhasebe\Enumlar\OlculuStokTakipTuru;
use App\Muhasebe\Enumlar\StokBelgeTuru;
use App\Muhasebe\Enumlar\StokHareketDurumu;
use App\Muhasebe\Enumlar\StokHareketIslemTuru;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Guvenlik\MuhasebeFirmaErisimDenetleyicisi;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FaturaIslemServisi
{
    public function __construct(
        private readonly CariHareketServisi $cariHareketServisi,
        private readonly StokHareketServisi $stokHareketServisi,
        private readonly FaturaToplamDogrulamaServisi $faturaToplamDogrulamaServisi,
        private readonly FaturaToplamSenkronizasyonServisi $faturaToplamSenkronizasyonServisi,
        private readonly MuhasebeFirmaErisimDenetleyicisi $firmaDenetleyicisi,
        private readonly FaturaNumaraUreticiServisi $faturaNumaraUreticiServisi,
        private readonly FaturaFinansKapamaServisi $faturaFinansKapamaServisi,
        private readonly MasrafFaturaBaglantiServisi $masrafFaturaBaglantiServisi,
        private readonly FaturaOlcuKalemiServisi $faturaOlcuKalemiServisi,
    ) {}

    /**
     * Onay transaction’ı commit olduktan sonra avans mahsubunu dener (iç içe transaction / kilit çakışması olmaması için).
     */
    private function onaySonrasiAvansMahsupDene(int $faturaId): void
    {
        DB::afterCommit(function () use ($faturaId): void {
            $f = Fatura::query()->find($faturaId);
            if ($f) {
                $this->faturaFinansKapamaServisi->faturayaUygunAvansMahsupEt($f);
            }
        });
    }

    /**
     * Taslak faturayı onaylar; cari + stok hareketlerini üretir (proforma hariç).
     */
    public function faturayiOnayla(Fatura $fatura): void
    {
        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt((int) $fatura->firma_id);
        DB::transaction(function () use ($fatura): void {
            $fatura = Fatura::query()->lockForUpdate()->whereKey($fatura->getKey())->firstOrFail();
            // Eski kayıtlarda kalemler yazılmış, başlık toplamları boş kalmış
            // olabilir. Cari/stok hareketi üretmeden önce başlığı tek kuralla
            // senkronlayıp sonra doğruluyoruz.
            $fatura = $this->faturaToplamSenkronizasyonServisi->senkronla($fatura);
            if ($fatura->isletme_proje_id !== null && ! IsletmeProjesi::query()
                ->where('firma_id', $fatura->firma_id)
                ->whereKey($fatura->isletme_proje_id)
                ->exists()) {
                throw new IsKuraliIstisnasi('Fatura projesi aynı firmaya ait olmalıdır.');
            }
            $tur = $fatura->tur->kanonik();

            if ($fatura->durum === FaturaDurumu::Onayli) {
                // Eski/yarım kalmış senkronlarda fatura onaylı işaretlenmiş,
                // ancak cari hareketi oluşturulmamış olabilir. Onaylı kaydı
                // idempotent kabul ederken bu muhasebe invariantını da doğrula.
                if ($fatura->tur->kayitUretirMi()
                    && $fatura->cari_id !== null
                    && ! $fatura->cariHareketleri()
                        ->where('durum', CariHareketDurumu::Aktif)
                        ->exists()) {
                    if (! $fatura->cari()->where('firma_id', $fatura->firma_id)->exists()) {
                        throw new IsKuraliIstisnasi('Onaylı faturanın carisi aktif firmaya ait değil.');
                    }

                    $this->faturaToplamDogrulamaServisi->dogrula($fatura);
                    $borcAlacak = $this->cariBorcVeAlacak($fatura, $tur);
                    $this->cariHareketServisi->kayitOlustur((int) $fatura->firma_id, [
                        'cari_id' => (int) $fatura->cari_id,
                        'isletme_proje_id' => $fatura->isletme_proje_id,
                        'belge_turu' => CariHareketBelgeTuru::Fatura,
                        'belge_id' => (int) $fatura->getKey(),
                        'islem_tarihi' => $fatura->tarih,
                        'vade_tarihi' => $fatura->vade_tarihi,
                        'borc' => $borcAlacak['borc'],
                        'alacak' => $borcAlacak['alacak'],
                        'para_birimi' => $fatura->para_birimi,
                        'aciklama' => $fatura->aciklama,
                    ]);
                    $this->logWarning('fatura.onay.eksik_cari_hareket_tamamlandi', [
                        'fatura_id' => (int) $fatura->id,
                        'firma_id' => (int) $fatura->firma_id,
                    ]);
                }

                if ($this->faturaNumarasiniGerekirseAta($fatura)) {
                    $this->logWarning('fatura.onay.numara_eksik_tamamlandi', [
                        'fatura_id' => (int) $fatura->id,
                        'firma_id' => (int) $fatura->firma_id,
                    ]);
                }

                // Aynı faturanın tekrar onay akışına düşmesi halinde duplicate kayıt üretilmez.
                return;
            }
            if (! in_array($fatura->durum, [FaturaDurumu::Taslak, FaturaDurumu::Beklemede], true)) {
                throw new IsKuraliIstisnasi('Yalnızca taslak/beklemede fatura onaylanabilir.');
            }
            if (! $fatura->tur->kayitUretirMi()) {
                $fatura->update(['durum' => FaturaDurumu::Onayli]);
                $this->logInfo('fatura.onay', ['fatura_id' => (int) $fatura->id, 'firma_id' => (int) $fatura->firma_id, 'cari_stok_uretim' => false]);

                return;
            }

            if ($fatura->cari_id === null) {
                throw new IsKuraliIstisnasi('Proforma dışındaki faturalarda cari zorunludur.');
            }
            app(FaturaParaBirimiDogrulamaServisi::class)->dogrula(
                (int) $fatura->firma_id,
                (int) $fatura->cari_id,
                (string) $fatura->para_birimi,
            );
            $this->faturaToplamDogrulamaServisi->dogrula($fatura);
            $this->olculuKalemleriDogrula($fatura, $tur);
            $this->olculuIadeKaynaklariniDogrula($fatura, $tur);


            $aktifCariVar = $fatura->cariHareketleri()->where('durum', CariHareketDurumu::Aktif)->exists();
            $aktifStokVar = $fatura->stokHareketleri()->where('durum', StokHareketDurumu::Aktif)->exists();
            if ($aktifCariVar xor $aktifStokVar) {
                $this->logWarning('fatura.onay.idempotent_tutarsizlik', [
                    'fatura_id' => (int) $fatura->id,
                    'firma_id' => (int) $fatura->firma_id,
                    'aktif_cari_var' => $aktifCariVar,
                    'aktif_stok_var' => $aktifStokVar,
                ]);
                if ((bool) config('muhasebe.fatura.idempotent_tutarsizlik_hata', false)) {
                    throw new IsKuraliIstisnasi('Fatura için kısmi işlenmiş kayıt tespit edildi. İnceleme gerekli.');
                }
            }
            if ($aktifCariVar || $aktifStokVar) {
                $this->faturaNumarasiniGerekirseAta($fatura);
                $fatura->update(['durum' => FaturaDurumu::Onayli]);
                $this->logWarning('fatura.onay.idempotent_atla', ['fatura_id' => (int) $fatura->id, 'firma_id' => (int) $fatura->firma_id]);
                $this->onaySonrasiAvansMahsupDene((int) $fatura->id);

                return;
            }

            $this->faturaNumarasiniGerekirseAta($fatura);

            $borcAlacak = $this->cariBorcVeAlacak($fatura, $tur);
            $this->cariHareketServisi->kayitOlustur((int) $fatura->firma_id, [
                'cari_id' => (int) $fatura->cari_id,
                'isletme_proje_id' => $fatura->isletme_proje_id,
                'belge_turu' => CariHareketBelgeTuru::Fatura,
                'belge_id' => (int) $fatura->getKey(),
                'islem_tarihi' => $fatura->tarih,
                'vade_tarihi' => $fatura->vade_tarihi,
                'borc' => $borcAlacak['borc'],
                'alacak' => $borcAlacak['alacak'],
                'para_birimi' => $fatura->para_birimi,
                'aciklama' => $fatura->aciklama,
            ]);

            foreach ($fatura->onayKalemleri()->get() as $kalem) {
                if ($kalem->hizmet_mi || $kalem->stok_id === null) {
                    continue;
                }
                $stok = StokKarti::query()
                    ->where('firma_id', $fatura->firma_id)
                    ->whereKey((int) $kalem->stok_id)
                    ->first();
                if (! $stok) {
                    throw new IsKuraliIstisnasi('Fatura kalemindeki stok kartı aktif firmaya ait olmalıdır.');
                }
                $stokIslem = $this->stokIslemTuruFaturadan($fatura, $tur);
                if ($stokIslem === null) {
                    continue;
                }
                $hareketDepoId = $kalem->depo_id ?? $stok->depo_id ?? 0;
                // Ölçülü kalemlerde ana miktar dağılım servisi tarafından
                // m²/adet dönüşümüyle hesaplanır. Tekrar adet katsayısıyla
                // çarpmak, m² ile yapılan kısmi satışlarda stoktan fazla
                // düşülmesine neden olur.
                $anaMiktar = $stok->olculu_takip_turu instanceof OlculuStokTakipTuru
                    && $stok->olculu_takip_turu->olculuMu()
                    && filled($kalem->ana_miktar)
                    ? (string) $kalem->ana_miktar
                    : $this->stokAnaMiktariniHesapla($stok, (string) $kalem->miktar);
                if ($anaMiktar !== (string) $kalem->miktar) {
                    $guncelleme = ['ana_miktar' => $anaMiktar];
                    $mevcutSnapshot = json_decode((string) $kalem->olcu_donusum_snapshot, true);
                    $fiyatSnapshotiVar = is_array($mevcutSnapshot)
                        && array_key_exists('fiyat_miktari', $mevcutSnapshot)
                        && array_key_exists('birim_fiyat', $mevcutSnapshot);
                    if (! $fiyatSnapshotiVar) {
                        $guncelleme['olcu_donusum_snapshot'] = json_encode([
                            'ana_miktar' => $anaMiktar,
                            'girilen_miktar' => (string) $kalem->miktar,
                        ]);
                    }
                    $kalem->update($guncelleme);
                }
                $hareket = $this->stokHareketServisi->kayitOlustur((int) $fatura->firma_id, [
                    'stok_id' => (int) $kalem->stok_id,
                    'depo_id' => (int) $hareketDepoId,
                    'cari_id' => $fatura->cari_id,
                    'islem_turu' => $stokIslem,
                    'miktar' => $anaMiktar,
                    'birim_fiyat' => $kalem->birim_fiyat,
                    'toplam' => $kalem->toplam,
                    'seri_nolari' => $kalem->seri_nolari,
                    'garanti_baslangic_tarihi' => $kalem->garanti_baslangic_tarihi,
                    'garanti_bitis_tarihi' => $kalem->garanti_bitis_tarihi,
                    'belge_turu' => StokBelgeTuru::Fatura,
                    'belge_id' => (int) $fatura->getKey(),
                    'tarih' => $fatura->tarih,
                ]);

                // Ölçülü stoklarda ana stok miktarına ek olarak seçilen ölçü
                // bakiyelerini ve hareket dağılım snapshot'ını da onay anında
                // kilitleyip güncelle.
                if ($kalem->olcuDagilimlari()->exists()) {
                    $giris = in_array($stokIslem, [
                        StokHareketIslemTuru::Alis,
                        StokHareketIslemTuru::SatisIadesi,
                    ], true);
                    $this->faturaOlcuKalemiServisi->onaydaUygula($kalem, $hareket, $giris);
                }
            }

            $fatura->update(['durum' => FaturaDurumu::Onayli]);
            $this->logInfo('fatura.onay', ['fatura_id' => (int) $fatura->id, 'firma_id' => (int) $fatura->firma_id, 'cari_stok_uretim' => true]);
            $this->onaySonrasiAvansMahsupDene((int) $fatura->id);
        }, 3);
    }

    private function faturaNumarasiniGerekirseAta(Fatura $fatura): bool
    {
        if (! $fatura->tur->kayitUretirMi() || trim((string) $fatura->fatura_no) !== '') {
            return false;
        }

        $yil = (int) Carbon::parse((string) $fatura->tarih)->year;
        $no = $this->faturaNumaraUreticiServisi->sonrakiNumarayiUretKilitle((int) $fatura->firma_id, $yil);
        $fatura->update(['fatura_no' => $no]);
        $fatura->refresh();

        return true;
    }

    /**
     * Faturayı iptal eder. Onaylı ise cari/stok hareketleri terslenir (silme yok).
     */
    public function faturayiIptalEt(Fatura $fatura): void
    {
        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt((int) $fatura->firma_id);

        DB::transaction(function () use ($fatura): void {
            $fatura = Fatura::query()->lockForUpdate()->whereKey($fatura->getKey())->firstOrFail();
            if ($fatura->durum === FaturaDurumu::Iptal) {
                if ($fatura->tur === FaturaTuru::AlisIadesi
                    && $fatura->onayKalemleri()->whereHas('olcuDagilimlari')->exists()) {
                    throw new IsKuraliIstisnasi('Ölçülü alış iadesi zaten iptal edilmiş.');
                }

                return;
            }
            $aktifFinansKapamaVar = $fatura->finansKapatmalari()
                ->whereHas('finansHareketi', fn ($q) => $q->where('durum', FinansHareketDurumu::Aktif))
                ->exists();
            if ($aktifFinansKapamaVar) {
                throw new IsKuraliIstisnasi('Fatura iptal edilmeden önce bağlı tahsilat/ödeme hareketleri terslenmelidir.');
            }

            if ($fatura->durum === FaturaDurumu::Onayli && $fatura->tur !== FaturaTuru::Proforma) {
                $fatura->cariHareketleri()
                    ->where('durum', CariHareketDurumu::Aktif)
                    ->get()
                    ->each(fn ($h) => $this->cariHareketServisi->tersKayitOlustur($h));

                $fatura->stokHareketleri()
                    ->where('durum', StokHareketDurumu::Aktif)
                    ->get()
                    ->each(function ($h): void {
                        $ters = $this->stokHareketServisi->tersKayitOlustur($h);
                        $this->faturaOlcuKalemiServisi->tersHareketUygula($h, $ters, $this->tersOlcuGirisMi($h));
                    });
            }

            $fatura->update(['durum' => FaturaDurumu::Iptal, 'odeme_durumu' => 'iptal', 'acik_tutar' => '0.00']);
            $this->masrafFaturaBaglantiServisi->faturayaBagliMasraflariIptalEt($fatura);
            $this->logWarning('fatura.iptal', ['fatura_id' => (int) $fatura->id, 'firma_id' => (int) $fatura->firma_id]);
        }, 3);
    }

    public function faturaIadeEt(Fatura $fatura, ?string $aciklama = null): void
    {
        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt((int) $fatura->firma_id);
        DB::transaction(function () use ($fatura, $aciklama): void {
            $fatura = Fatura::query()->lockForUpdate()->whereKey($fatura->getKey())->firstOrFail();
            if ($fatura->durum === FaturaDurumu::Iade) {
                return;
            }
            if ($fatura->durum !== FaturaDurumu::Onayli) {
                throw new IsKuraliIstisnasi('Yalnızca onaylı fatura iade edilebilir.');
            }
            $aktifFinansKapamaVar = $fatura->finansKapatmalari()
                ->whereHas('finansHareketi', fn ($q) => $q->where('durum', FinansHareketDurumu::Aktif))
                ->exists();
            if ($aktifFinansKapamaVar) {
                throw new IsKuraliIstisnasi('Fatura iade edilmeden önce bağlı tahsilat/ödeme hareketleri terslenmelidir.');
            }

            $fatura->cariHareketleri()
                ->where('durum', CariHareketDurumu::Aktif)
                ->get()
                ->each(fn ($h) => $this->cariHareketServisi->tersKayitOlustur($h, $aciklama));
            $fatura->stokHareketleri()
                ->where('durum', StokHareketDurumu::Aktif)
                ->get()
                ->each(function ($h) use ($aciklama): void {
                    $ters = $this->stokHareketServisi->tersKayitOlustur($h, $aciklama);
                    $this->faturaOlcuKalemiServisi->tersHareketUygula($h, $ters, $this->tersOlcuGirisMi($h));
                });
            $fatura->update(['durum' => FaturaDurumu::Iade, 'odeme_durumu' => 'iade']);
            $this->logWarning('fatura.iade', ['fatura_id' => (int) $fatura->id, 'firma_id' => (int) $fatura->firma_id]);
        }, 3);
    }

    /**
     * @return array{borc: string, alacak: string}
     */
    private function cariBorcVeAlacak(Fatura $fatura, FaturaTuru $tur): array
    {
        $gt = (string) $fatura->genel_toplam;

        return match ($tur->cariYonu()) {
            'borc' => ['borc' => $gt, 'alacak' => '0'],
            'alacak' => ['borc' => '0', 'alacak' => $gt],
            default => throw new IsKuraliIstisnasi('Bu tür cari hareketi üretmez.'),
        };
    }

    private function stokIslemTuruFaturadan(Fatura $fatura, FaturaTuru $tur): ?StokHareketIslemTuru
    {
        if ($tur->kanonik() === FaturaTuru::Gelen
            && $fatura->fatura_sinifi === FaturaSinifi::Gider) {
            return null;
        }

        return match ($tur->kanonik()) {
            FaturaTuru::Giden => StokHareketIslemTuru::Satis,
            FaturaTuru::Gelen => StokHareketIslemTuru::Alis,
            // Gider faturası stok maliyeti üretmez; finans/cari gider akışıyla izlenir.
            FaturaTuru::Gider => null,
            FaturaTuru::SatisIadesi => StokHareketIslemTuru::SatisIadesi,
            FaturaTuru::AlisIadesi => StokHareketIslemTuru::AlisIadesi,
            default => null,
        };
    }

    private function tersOlcuGirisMi($hareket): bool
    {
        $tur = $hareket->islem_turu instanceof StokHareketIslemTuru ? $hareket->islem_turu : StokHareketIslemTuru::tryFrom((string) $hareket->islem_turu);

        return in_array($tur, [StokHareketIslemTuru::Satis, StokHareketIslemTuru::AlisIadesi], true);
    }

    private function stokAnaMiktariniHesapla(StokKarti $stok, string $miktar): string
    {
        if (! ($stok->olculu_takip_turu instanceof OlculuStokTakipTuru) || ! $stok->olculu_takip_turu->olculuMu()) {
            return $miktar;
        }

        $olcu = StokOlcusu::query()->where('stok_id', $stok->getKey())->where('aktif_mi', true)->sole();
        $katsayi = (string) ($olcu->bir_adet_ana_miktar ?? '0');
        if (bccomp($katsayi, '0', 8) <= 0) {
            throw new IsKuraliIstisnasi('Stok kartındaki ölçülerden ana birim dönüşümü hesaplanamadı. Gerekli ölçü değerlerini tamamlayın.');
        }

        return bcmul($miktar, $katsayi, 8);
    }

    private function olculuKalemleriDogrula(Fatura $fatura, FaturaTuru $tur): void
    {
        $cikisMi = in_array($tur, [FaturaTuru::Giden, FaturaTuru::AlisIadesi], true);

        foreach ($fatura->onayKalemleri()->get() as $kalem) {
            if ($kalem->hizmet_mi || $kalem->stok_id === null) {
                continue;
            }

            $stok = StokKarti::query()
                ->where('firma_id', $fatura->firma_id)
                ->whereKey((int) $kalem->stok_id)
                ->first();
            if (! $stok || ! ($stok->olculu_takip_turu instanceof OlculuStokTakipTuru && $stok->olculu_takip_turu->olculuMu())) {
                continue;
            }

            $satir = (int) ($kalem->satir_no ?: $kalem->getKey());
            if ($kalem->olcuDagilimlari()->doesntExist()) {
                throw new IsKuraliIstisnasi(
                    'Ölçülü stok kaleminde en az bir ölçü dağılımı seçilmelidir (satır #'.$satir.'). '
                    .($cikisMi ? 'Ölçü, stok bakiyesi ve miktarı girin.' : 'Ölçü ve miktarı girin.')
                );
            }

            if (bccomp((string) ($kalem->ana_miktar ?? '0'), '0', 8) <= 0) {
                throw new IsKuraliIstisnasi('Ölçülü stok kaleminin ana miktarı sıfırdan büyük olmalıdır (satır #'.$satir.').');
            }
        }
    }

    private function olculuIadeKaynaklariniDogrula(Fatura $fatura, FaturaTuru $tur): void
    {
        if (! in_array($tur, [FaturaTuru::SatisIadesi, FaturaTuru::AlisIadesi], true)) {
            return;
        }

        $kaynakTur = $tur === FaturaTuru::SatisIadesi ? FaturaTuru::Giden : FaturaTuru::Gelen;
        $olculuKalemVar = false;

        foreach ($fatura->onayKalemleri()->get() as $kalem) {
            if ($kalem->hizmet_mi || $kalem->stok_id === null) {
                continue;
            }

            $stok = StokKarti::query()
                ->where('firma_id', $fatura->firma_id)
                ->whereKey((int) $kalem->stok_id)
                ->first();
            $olculu = $stok?->olculu_takip_turu instanceof OlculuStokTakipTuru
                && $stok->olculu_takip_turu->olculuMu();
            if (! $olculu) {
                continue;
            }

            $olculuKalemVar = true;
            if ($fatura->bagli_fatura_id === null || $kalem->kaynak_fatura_kalemi_id === null) {
                throw new IsKuraliIstisnasi('Ölçülü iadede kaynak fatura ve kaynak fatura kalemi zorunludur.');
            }

            $kaynak = Fatura::query()->lockForUpdate()->find((int) $fatura->bagli_fatura_id);
            if (! $kaynak || (int) $kaynak->firma_id !== (int) $fatura->firma_id) {
                throw new IsKuraliIstisnasi('İade kaynağı aynı firmaya ait olmalıdır.');
            }
            if ($kaynak->durum !== FaturaDurumu::Onayli || $kaynak->tur->kanonik() !== $kaynakTur) {
                throw new IsKuraliIstisnasi('Ölçülü iade yalnızca onaylı uygun kaynak faturadan yapılabilir.');
            }
            if ((int) $kaynak->cari_id !== (int) $fatura->cari_id) {
                throw new IsKuraliIstisnasi('İade carisi kaynak fatura carisiyle aynı olmalıdır.');
            }

            $kaynakKalem = $kaynak->onayKalemleri()
                ->whereKey((int) $kalem->kaynak_fatura_kalemi_id)
                ->where('stok_id', $kalem->stok_id)
                ->first();
            if (! $kaynakKalem || $kaynakKalem->hizmet_mi || $kaynakKalem->olcuDagilimlari()->doesntExist()) {
                throw new IsKuraliIstisnasi('Ölçülü iade kalemi kaynak ölçü dağılımına bağlı olmalıdır.');
            }
            if (bccomp((string) ($kalem->ana_miktar ?? '0'), (string) ($kaynakKalem->ana_miktar ?? '0'), 8) !== 0) {
                throw new IsKuraliIstisnasi('Ölçülü tam iadede ana miktar kaynak kalemle aynı olmalıdır.');
            }

            $aktifAyniKaynak = FaturaKalemi::query()
                ->where('kaynak_fatura_kalemi_id', $kaynakKalem->getKey())
                ->whereHas('fatura', function ($sorgu) use ($fatura): void {
                    $sorgu->where('firma_id', $fatura->firma_id)
                        ->where('bagli_fatura_id', $fatura->bagli_fatura_id)
                        ->where('durum', FaturaDurumu::Onayli)
                        ->whereIn('tur', [FaturaTuru::SatisIadesi->value, FaturaTuru::AlisIadesi->value]);
                })
                ->exists();
            if ($aktifAyniKaynak) {
                throw new IsKuraliIstisnasi('Bu ölçülü kaynak kalem için daha önce onaylı iade oluşturulmuş.');
            }

            $kaynakDagilimlari = $kaynakKalem->olcuDagilimlari()->lockForUpdate()->get()->keyBy('id');
            $iadeDagilimlari = $kalem->olcuDagilimlari()->lockForUpdate()->get();
            if ($iadeDagilimlari->count() !== $kaynakDagilimlari->count()) {
                throw new IsKuraliIstisnasi('Ölçülü tam iadede kaynak dağılımların tamamı gönderilmelidir.');
            }
            $kullanilanKaynaklar = [];
            foreach ($iadeDagilimlari as $dagilim) {
                $kaynakDagilimId = (int) ($dagilim->kaynak_olcu_dagilimi_id ?? 0);
                $kaynakDagilim = $kaynakDagilimlari->get($kaynakDagilimId);
                if ($kaynakDagilimId < 1 || ! $kaynakDagilim || isset($kullanilanKaynaklar[$kaynakDagilimId])) {
                    throw new IsKuraliIstisnasi('İade ölçü dağılımı kaynak ölçü dağılımına bağlı olmalıdır.');
                }
                $kullanilanKaynaklar[$kaynakDagilimId] = true;
                foreach (['stok_id', 'stok_olcusu_id', 'stok_olcu_bakiyesi_id', 'depo_id', 'islem_birimi_id'] as $alan) {
                    if ((int) ($dagilim->{$alan} ?? 0) !== (int) ($kaynakDagilim->{$alan} ?? 0)) {
                        throw new IsKuraliIstisnasi('İade ölçü dağılımı kaynak stok, depo veya ölçüyle aynı olmalıdır.');
                    }
                }
                foreach (['girilen_miktar', 'ana_miktar', 'adet_esdegeri'] as $alan) {
                    if (bccomp((string) ($dagilim->{$alan} ?? '0'), (string) ($kaynakDagilim->{$alan} ?? '0'), 8) !== 0) {
                        throw new IsKuraliIstisnasi('Ölçülü tam iadede dağılım miktarı kaynak dağılımla aynı olmalıdır.');
                    }
                }
            }
        }

        if ($olculuKalemVar && $fatura->bagli_fatura_id === null) {
            throw new IsKuraliIstisnasi('Ölçülü iade için kaynak fatura seçilmelidir.');
        }
    }

    private function logInfo(string $message, array $context): void
    {
        Log::channel((string) config('muhasebe.fatura.log_channel', 'muhasebe'))->info($message, $context);
    }

    private function logWarning(string $message, array $context): void
    {
        Log::channel((string) config('muhasebe.fatura.log_channel', 'muhasebe'))->warning($message, $context);
    }
}
