<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\CariHareketi;
use App\Models\Muhasebe\FinansHareketi;
use App\Muhasebe\Enumlar\CariHareketBelgeTuru;
use App\Muhasebe\Enumlar\CariHareketDurumu;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Guvenlik\MuhasebeFirmaErisimDenetleyicisi;
use Illuminate\Support\Facades\DB;

class CariHareketServisi
{
    public function __construct(
        private readonly MuhasebeFirmaErisimDenetleyicisi $firmaDenetleyicisi,
        private readonly ParaBirimiDonusumServisi $paraBirimiDonusumServisi,
        private readonly CariHareketFifoEslestirmeServisi $fifoEslestirmeServisi,
    ) {}

    /**
     * @param  array{
     *     cari_id:int,
     *     isletme_proje_id?:int|null,
     *     belge_turu:CariHareketBelgeTuru|string,
     *     belge_id:int,
     *     islem_tarihi:\DateTimeInterface|string,
     *     vade_tarihi?:\DateTimeInterface|string|null,
     *     borc:string|float|int,
     *     alacak:string|float|int,
     *     para_birimi:string,
     *     aciklama?:string|null,
     * }  $alanlar
     */
    public function kayitOlustur(int $firmaId, array $alanlar, bool $eTicaretSistemCagrisi = false): CariHareketi
    {
        if ($eTicaretSistemCagrisi) {
            $this->firmaDenetleyicisi->eTicaretYazmaIcinFirmaKontrolEt($firmaId);
        } else {
            $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt($firmaId);
        }

        $cariSorgu = Cari::query();
        if ($eTicaretSistemCagrisi) {
            $cariSorgu = $cariSorgu->withoutGlobalScopes();
        }

        $cari = $cariSorgu->whereKey($alanlar['cari_id'])->firstOrFail();
        if ((int) $cari->firma_id !== $firmaId) {
            throw new IsKuraliIstisnasi('Cari farkli firmaya ait.');
        }

        $belgeTuru = $alanlar['belge_turu'] instanceof CariHareketBelgeTuru
            ? $alanlar['belge_turu']
            : CariHareketBelgeTuru::from($alanlar['belge_turu']);

        $projeId = $alanlar['isletme_proje_id'] ?? null;
        if ($projeId === null && in_array($belgeTuru, [CariHareketBelgeTuru::Tahsilat, CariHareketBelgeTuru::Odeme, CariHareketBelgeTuru::Mahsup], true)) {
            $projeId = FinansHareketi::query()
                ->where('firma_id', $firmaId)
                ->whereKey((int) $alanlar['belge_id'])
                ->value('isletme_proje_id');
        }
        if ($projeId !== null && ! \App\Models\Proje\IsletmeProjesi::query()->where('firma_id', $firmaId)->whereKey($projeId)->exists()) {
            throw new IsKuraliIstisnasi('Cari hareketi projesi aynı firmaya ait olmalıdır.');
        }

        $islemTarihi = is_string($alanlar['islem_tarihi'] ?? null) || ($alanlar['islem_tarihi'] ?? null) instanceof \DateTimeInterface
            ? $alanlar['islem_tarihi']
            : null;
        $paraBirimi = (string) ($alanlar['para_birimi'] ?? 'TRY');

        $borcDonusum = $this->paraBirimiDonusumServisi->tutariBazParaBirimineHazirla(
            $firmaId,
            (string) ($alanlar['borc'] ?? '0'),
            $paraBirimi,
            $islemTarihi
        );
        $alacakDonusum = $this->paraBirimiDonusumServisi->tutariBazParaBirimineHazirla(
            $firmaId,
            (string) ($alanlar['alacak'] ?? '0'),
            $paraBirimi,
            $islemTarihi
        );

        $hareket = CariHareketi::query()->create([
            'firma_id' => $firmaId,
            'isletme_proje_id' => $projeId,
            'cari_id' => (int) $alanlar['cari_id'],
            'belge_turu' => $belgeTuru,
            'belge_id' => (int) $alanlar['belge_id'],
            'islem_tarihi' => $alanlar['islem_tarihi'],
            'vade_tarihi' => $alanlar['vade_tarihi'] ?? null,
            'borc' => $alanlar['borc'],
            'alacak' => $alanlar['alacak'],
            'para_birimi' => $borcDonusum['para_birimi'],
            'baz_borc' => $borcDonusum['baz_tutar'],
            'baz_alacak' => $alacakDonusum['baz_tutar'],
            'baz_para_birimi' => $borcDonusum['baz_para_birimi'],
            'kur' => $borcDonusum['kur'],
            'aciklama' => $alanlar['aciklama'] ?? null,
            'durum' => CariHareketDurumu::Aktif,
            'iptal_edilen_hareket_id' => null,
        ]);

        $this->fifoEslestirmeServisi->yeniHareketSonrasiOtomatikEsle($hareket);

        return $hareket;
    }

    /**
     * Silme yok: kaynak hareket iptal, ters yonlu yeni satir uretilir.
     */
    public function tersKayitOlustur(CariHareketi $hareket, ?string $aciklama = null): CariHareketi
    {
        if ($hareket->durum !== CariHareketDurumu::Aktif) {
            throw new IsKuraliIstisnasi('Yalnizca aktif cari hareketi terslenebilir.');
        }

        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt((int) $hareket->firma_id);

        return DB::transaction(function () use ($hareket, $aciklama): CariHareketi {
            $this->fifoEslestirmeServisi->iptalEdilenHareketEslesmeleriniSil($hareket);

            $hareket->update(['durum' => CariHareketDurumu::Iptal]);

            $donusumBorc = $this->paraBirimiDonusumServisi->tutariBazParaBirimineHazirla(
                (int) $hareket->firma_id,
                (string) $hareket->alacak,
                (string) $hareket->para_birimi,
                now()
            );
            $donusumAlacak = $this->paraBirimiDonusumServisi->tutariBazParaBirimineHazirla(
                (int) $hareket->firma_id,
                (string) $hareket->borc,
                (string) $hareket->para_birimi,
                now()
            );

            $yeni = CariHareketi::query()->create([
                'firma_id' => $hareket->firma_id,
                'isletme_proje_id' => $hareket->isletme_proje_id,
                'cari_id' => $hareket->cari_id,
                'belge_turu' => $hareket->belge_turu,
                'belge_id' => $hareket->belge_id,
                'islem_tarihi' => now(),
                'vade_tarihi' => $hareket->vade_tarihi,
                'borc' => $hareket->alacak,
                'alacak' => $hareket->borc,
                'para_birimi' => $hareket->para_birimi,
                'baz_borc' => $donusumBorc['baz_tutar'],
                'baz_alacak' => $donusumAlacak['baz_tutar'],
                'baz_para_birimi' => $donusumBorc['baz_para_birimi'],
                'kur' => $donusumBorc['kur'],
                'aciklama' => $aciklama ?? ('Ters kayit: #'.$hareket->getKey()),
                'durum' => CariHareketDurumu::Aktif,
                'iptal_edilen_hareket_id' => $hareket->getKey(),
            ]);

            $this->fifoEslestirmeServisi->yeniHareketSonrasiOtomatikEsle($yeni);

            return $yeni;
        });
    }

    public function kaydiIptalEt(CariHareketi $hareket, ?string $aciklama = null): CariHareketi
    {
        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt((int) $hareket->firma_id);

        return DB::transaction(function () use ($hareket, $aciklama): CariHareketi {
            $kilitliHareket = CariHareketi::query()
                ->lockForUpdate()
                ->whereKey($hareket->getKey())
                ->firstOrFail();

            if ($kilitliHareket->durum !== CariHareketDurumu::Aktif) {
                throw new IsKuraliIstisnasi('Yalnizca aktif cari hareketi iptal edilebilir.');
            }

            $this->fifoEslestirmeServisi->iptalEdilenHareketEslesmeleriniSil($kilitliHareket);

            $kilitliHareket->update([
                'durum' => CariHareketDurumu::Iptal,
                'aciklama' => trim((string) (($kilitliHareket->aciklama ?? '').($aciklama ? ' | '.$aciklama : ''))) ?: $kilitliHareket->aciklama,
            ]);

            return $kilitliHareket->fresh() ?? $kilitliHareket;
        });
    }
}
