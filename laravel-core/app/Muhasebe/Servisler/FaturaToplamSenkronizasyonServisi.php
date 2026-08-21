<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\Fatura;

/**
 * Fatura başlık toplamlarını kalemlerden tek bir kuralla üretir.
 * Onay akışında eski/yarım kalmış kayıtların cari hareket üretmeden önce
 * doğrulanabilmesi için de kullanılır.
 */
class FaturaToplamSenkronizasyonServisi
{
    private const SCALE = 8;

    public function senkronla(Fatura $fatura): Fatura
    {
        // CreateRecord/Repeater akışında ilişki daha önce boş olarak yüklenmiş
        // olabilir. loadMissing() bu durumda tekrar sorgu çalıştırmadığı için
        // başlık toplamları sıfır kalabiliyordu. Onaydan hemen önce kalemleri
        // veritabanından mutlaka yeniden oku.
        $fatura->unsetRelation('kalemler')->setRelation('kalemler', $fatura->onayKalemleri()->get());

        $araToplam = '0.00000000';
        $kdvToplam = '0.00000000';

        foreach ($fatura->kalemler as $kalem) {
            $brut = bcmul((string) ($kalem->miktar ?? 0), (string) ($kalem->birim_fiyat ?? 0), self::SCALE);
            $indirim = (string) ($kalem->satir_indirim_tutari ?? $kalem->indirim_tutari ?? '0');
            $araToplam = bcadd($araToplam, bcsub($brut, $indirim, self::SCALE), self::SCALE);
            $kdvToplam = bcadd($kdvToplam, (string) ($kalem->kdv_tutari ?? 0), self::SCALE);
        }

        $genelIndirim = (string) ($fatura->genel_indirim_tutari ?? 0);
        $genelToplam = bcsub(bcadd($araToplam, $kdvToplam, self::SCALE), $genelIndirim, self::SCALE);
        $tevkifatOrani = (string) ($fatura->tevkifat_orani ?? 0);
        $tevkifatTutari = bcmul($kdvToplam, bcdiv($tevkifatOrani, '100', self::SCALE), self::SCALE);
        $odenecek = bcsub($genelToplam, $tevkifatTutari, self::SCALE);
        $acik = bcsub($odenecek, (string) ($fatura->odendi_tutari ?? 0), self::SCALE);
        $kur = (string) ($fatura->doviz_kuru ?: '1');

        $fatura->forceFill([
            'ara_toplam' => $araToplam,
            'kdv_toplam' => $kdvToplam,
            'genel_toplam' => $genelToplam,
            'odenecek_tutar' => $odenecek,
            'acik_tutar' => $acik,
            'baz_genel_toplam' => bcmul($genelToplam, $kur, self::SCALE),
            'baz_odenecek_tutar' => bcmul($odenecek, $kur, self::SCALE),
            'baz_acik_tutar' => bcmul($acik, $kur, self::SCALE),
        ])->save();

        return $fatura->refresh();
    }
}
