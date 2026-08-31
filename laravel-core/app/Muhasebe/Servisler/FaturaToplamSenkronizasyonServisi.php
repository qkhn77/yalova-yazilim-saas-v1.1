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

    public function __construct(
        private readonly ParaBirimiDonusumServisi $paraBirimiDonusumServisi,
    ) {}

    public function senkronla(Fatura $fatura): Fatura
    {
        // CreateRecord/Repeater akışında ilişki daha önce boş olarak yüklenmiş
        // olabilir. loadMissing() bu durumda tekrar sorgu çalıştırmadığı için
        // başlık toplamları sıfır kalabiliyordu. Onaydan hemen önce kalemleri
        // veritabanından mutlaka yeniden oku.
        $fatura->unsetRelation('kalemler')->setRelation('kalemler', $fatura->onayKalemleri()->get());

        $araToplam = '0.00000000';
        $kdvToplam = '0.00000000';
        $kdvDahil = (bool) $fatura->kdv_dahil_fiyatlandirma_mi;

        foreach ($fatura->kalemler as $kalem) {
            $brut = bcmul((string) ($kalem->miktar ?? 0), (string) ($kalem->birim_fiyat ?? 0), self::SCALE);
            $indirim = (string) ($kalem->satir_indirim_tutari ?? $kalem->indirim_tutari ?? '0');
            $satirGenel = bcsub($brut, $indirim, self::SCALE);
            $oran = bcdiv((string) ($kalem->kdv_orani ?? 0), '100', self::SCALE);
            if ($kdvDahil) {
                $bolen = bcadd('1', $oran, self::SCALE);
                $satirNet = bcdiv($satirGenel, $bolen, self::SCALE);
                $satirKdv = bcsub($satirGenel, $satirNet, self::SCALE);
            } else {
                $satirNet = $satirGenel;
                $satirKdv = bcmul($satirNet, $oran, self::SCALE);
                $satirGenel = bcadd($satirNet, $satirKdv, self::SCALE);
            }

            // Eski veya elle oluşturulmuş satırlarda ayrıntı alanları boş/0
            // kalabilir. Onay öncesi tek hesap kuralını satır snapshot'ına da
            // yazarak başlık ve kalem doğrulamasının aynı sonucu kullanmasını
            // sağlarız.
            $kalem->forceFill([
                'net_tutar' => $satirNet,
                'kdv_tutari' => $satirKdv,
                'toplam' => $satirGenel,
                'satir_toplami' => $satirNet,
                'satir_genel_toplam' => $satirGenel,
            ])->save();

            $araToplam = bcadd($araToplam, $satirNet, self::SCALE);
            $kdvToplam = bcadd($kdvToplam, $satirKdv, self::SCALE);
        }

        $genelIndirim = (string) ($fatura->genel_indirim_tutari ?? 0);
        $genelToplam = bcsub(bcadd($araToplam, $kdvToplam, self::SCALE), $genelIndirim, self::SCALE);
        $tevkifatOrani = (string) ($fatura->tevkifat_orani ?? 0);
        $tevkifatTutari = bcmul($kdvToplam, bcdiv($tevkifatOrani, '100', self::SCALE), self::SCALE);
        $odenecek = bcsub($genelToplam, $tevkifatTutari, self::SCALE);
        $acik = bcsub($odenecek, (string) ($fatura->odendi_tutari ?? 0), self::SCALE);
        $paraBirimi = strtoupper((string) ($fatura->para_birimi ?: 'TRY'));
        $donusum = $this->paraBirimiDonusumServisi->tutariBazParaBirimineHazirla(
            (int) $fatura->firma_id,
            $genelToplam,
            $paraBirimi,
            $fatura->tarih,
        );
        $kur = (string) $donusum['kur'];
        $bazParaBirimi = (string) $donusum['baz_para_birimi'];
        $odendiDonusum = $this->paraBirimiDonusumServisi->tutariBazParaBirimineHazirla(
            (int) $fatura->firma_id,
            (string) ($fatura->odendi_tutari ?? '0'),
            $paraBirimi,
            $fatura->tarih,
        );

        foreach ($fatura->kalemler as $kalem) {
            $kalem->forceFill([
                'para_birimi' => $paraBirimi,
                'baz_para_birimi' => $bazParaBirimi,
                'baz_birim_fiyat' => $this->bazTutar((string) ($kalem->birim_fiyat ?? '0'), $kur),
                'baz_indirim_tutari' => $this->bazTutar((string) ($kalem->satir_indirim_tutari ?? $kalem->indirim_tutari ?? '0'), $kur),
                'baz_net_tutar' => $this->bazTutar((string) ($kalem->net_tutar ?? '0'), $kur),
                'baz_kdv_tutari' => $this->bazTutar((string) ($kalem->kdv_tutari ?? '0'), $kur),
                'baz_satir_toplami' => $this->bazTutar((string) ($kalem->satir_toplami ?? '0'), $kur),
                'baz_satir_genel_toplam' => $this->bazTutar((string) ($kalem->satir_genel_toplam ?? '0'), $kur),
            ])->save();
        }

        $fatura->forceFill([
            'para_birimi' => $paraBirimi,
            'baz_para_birimi' => $bazParaBirimi,
            'doviz_kuru' => $kur,
            'ara_toplam' => $araToplam,
            'baz_ara_toplam' => $this->bazTutar($araToplam, $kur),
            'kdv_toplam' => $kdvToplam,
            'baz_kdv_toplam' => $this->bazTutar($kdvToplam, $kur),
            'genel_toplam' => $genelToplam,
            'baz_genel_toplam' => $donusum['baz_tutar'],
            'odenecek_tutar' => $odenecek,
            'baz_odenecek_tutar' => $this->bazTutar($odenecek, $kur),
            'acik_tutar' => $acik,
            'odendi_tutari' => (string) ($fatura->odendi_tutari ?? '0'),
            'baz_odendi_tutari' => $odendiDonusum['baz_tutar'],
            'baz_acik_tutar' => $this->bazTutar($acik, $kur),
        ])->save();

        return $fatura->refresh();
    }

    private function bazTutar(string $tutar, string $kur): string
    {
        return bcmul($tutar, $kur, self::SCALE);
    }
}
