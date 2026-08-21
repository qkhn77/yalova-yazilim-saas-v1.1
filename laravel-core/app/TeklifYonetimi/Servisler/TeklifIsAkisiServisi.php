<?php

namespace App\TeklifYonetimi\Servisler;

use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\FaturaKalemi;
use App\Models\Muhasebe\Teklif;
use App\Models\Muhasebe\TeklifKalemi;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TeklifIsAkisiServisi
{
    public function durumDegistir(Teklif $teklif, string $durum): Teklif
    {
        if (! array_key_exists($durum, Teklif::DURUMLAR)) {
            throw new IsKuraliIstisnasi('Geçersiz teklif durumu.');
        }

        return DB::transaction(function () use ($teklif, $durum): Teklif {
            /** @var Teklif $kilitli */
            $kilitli = Teklif::tenantScopeOlmadan(fn () => Teklif::query()
                ->whereKey($teklif->getKey())
                ->lockForUpdate()
                ->firstOrFail());

            $alanlar = ['durum' => $durum];

            if ($durum === 'gonderildi' && blank($kilitli->gonderildi_at)) {
                $alanlar['gonderildi_at'] = now();
            }

            if (in_array($durum, ['onaylandi', 'reddedildi', 'revizyon_bekliyor'], true)) {
                $alanlar['yanitlandi_at'] = now();
            }

            $kilitli->forceFill($alanlar)->save();

            return $kilitli->refresh();
        }, 3);
    }

    public function bekleyenFaturaOlustur(Teklif $teklif): Fatura
    {
        return DB::transaction(function () use ($teklif): Fatura {
            /** @var Teklif $kilitli */
            $kilitli = Teklif::tenantScopeOlmadan(fn () => Teklif::query()
                ->whereKey($teklif->getKey())
                ->lockForUpdate()
                ->firstOrFail());

            if ((string) $kilitli->durum !== 'onaylandi') {
                throw new IsKuraliIstisnasi('Fatura oluşturmak için teklif önce onaylanmalıdır.');
            }

            if ((int) ($kilitli->cari_id ?? 0) < 1) {
                throw new IsKuraliIstisnasi('Fatura oluşturmak için teklifin carisi olmalıdır.');
            }

            if ((int) ($kilitli->faturaya_donustu_fatura_id ?? 0) > 0) {
                $mevcut = Fatura::tenantScopeOlmadan(fn () => Fatura::query()
                    ->where('firma_id', (int) $kilitli->firma_id)
                    ->whereKey((int) $kilitli->faturaya_donustu_fatura_id)
                    ->first());

                if ($mevcut instanceof Fatura) {
                    return $mevcut;
                }
            }

            $kalemler = TeklifKalemi::tenantScopeOlmadan(fn () => $kilitli->kalemler()->orderBy('id')->get());
            if ($kalemler->isEmpty()) {
                throw new IsKuraliIstisnasi('Fatura oluşturmak için teklif kalemi bulunmalıdır.');
            }

            $ozet = $this->faturaKalemleriniHazirla($kalemler, (string) ($kilitli->para_birimi ?: 'TRY'));
            if ($ozet['kalemler'] === []) {
                throw new IsKuraliIstisnasi('Fatura oluşturmak için geçerli teklif kalemi bulunmalıdır.');
            }

            $fatura = Fatura::query()->create([
                'firma_id' => (int) $kilitli->firma_id,
                'cari_id' => (int) $kilitli->cari_id,
                'belge_no' => (string) ($kilitli->teklif_no ?: 'Teklif #'.$kilitli->getKey()),
                'tur' => FaturaTuru::BekleyenFatura->value,
                'durum' => FaturaDurumu::Beklemede->value,
                'tarih' => Carbon::parse($kilitli->yanitlandi_at ?: now()),
                'vade_tarihi' => $kilitli->gecerlilik_tarihi,
                'doviz_kuru' => 1,
                'ara_toplam' => $ozet['ara_toplam'],
                'toplam_indirim' => $ozet['toplam_indirim'],
                'kdv_toplam' => $ozet['kdv_toplam'],
                'tevkifat_orani' => 0,
                'genel_toplam' => $ozet['genel_toplam'],
                'odenecek_tutar' => $ozet['genel_toplam'],
                'odendi_tutari' => '0.00',
                'acik_tutar' => $ozet['genel_toplam'],
                'genel_indirim_tutari' => '0.00',
                'kdv_dahil_fiyatlandirma_mi' => false,
                'odeme_durumu' => 'beklemede',
                'para_birimi' => (string) ($kilitli->para_birimi ?: 'TRY'),
                'aciklama' => 'Tekliften oluşturulan bekleyen fatura: '.(string) ($kilitli->teklif_no ?: '#'.$kilitli->getKey()),
                'notlar' => $kilitli->notlar,
                'kaynak_tipi' => 'teklif_yonetimi',
                'islem_no' => (int) $kilitli->getKey(),
            ]);

            foreach ($ozet['kalemler'] as $satir) {
                FaturaKalemi::query()->create(array_merge($satir, [
                    'firma_id' => (int) $kilitli->firma_id,
                    'fatura_id' => (int) $fatura->getKey(),
                    'para_birimi' => (string) ($kilitli->para_birimi ?: 'TRY'),
                ]));
            }

            $kilitli->forceFill([
                'faturaya_donustu_fatura_id' => (int) $fatura->getKey(),
            ])->save();

            return $fatura->refresh();
        }, 3);
    }

    /**
     * @param  iterable<int, TeklifKalemi>  $kalemler
     * @return array{kalemler: array<int, array<string, mixed>>, ara_toplam: string, toplam_indirim: string, kdv_toplam: string, genel_toplam: string}
     */
    private function faturaKalemleriniHazirla(iterable $kalemler, string $paraBirimi): array
    {
        $satirlar = [];
        $araToplam = 0.0;
        $toplamIndirim = 0.0;
        $kdvToplam = 0.0;
        $genelToplam = 0.0;
        $satirNo = 0;

        foreach ($kalemler as $kalem) {
            $satirNo++;
            $miktar = (float) $kalem->miktar;
            $birimFiyat = (float) $kalem->birim_fiyat;
            $satirToplami = round($miktar * $birimFiyat, 2);
            $netTutar = round((float) $kalem->net_tutar, 2);
            $indirimTutari = round(max(0, $satirToplami - $netTutar), 2);
            $kdvTutari = round((float) $kalem->kdv_tutari, 2);
            $toplam = round((float) $kalem->toplam, 2);

            $araToplam += $netTutar;
            $toplamIndirim += $indirimTutari;
            $kdvToplam += $kdvTutari;
            $genelToplam += $toplam;

            $satirlar[] = [
                'satir_no' => $satirNo,
                'kalem_tipi' => (string) ($kalem->kalem_tipi ?: 'stok_kalemi'),
                'stok_id' => $kalem->stok_id ? (int) $kalem->stok_id : null,
                'birim' => (string) ($kalem->birim ?: 'AD'),
                'hizmet_mi' => (bool) ($kalem->hizmet_mi || ! $kalem->stok_id),
                'aciklama' => (string) ($kalem->aciklama ?: 'Teklif kalemi'),
                'miktar' => $miktar,
                'birim_fiyat' => $birimFiyat,
                'indirim_orani' => (float) $kalem->indirim_orani,
                'kdv_orani' => (float) $kalem->kdv_orani,
                'satir_indirim_tutari' => $indirimTutari,
                'indirim_tutari' => $indirimTutari,
                'net_tutar' => $netTutar,
                'kdv_tutari' => $kdvTutari,
                'satir_toplami' => $satirToplami,
                'satir_genel_toplam' => $toplam,
                'toplam' => $toplam,
                'para_birimi' => $paraBirimi,
            ];
        }

        return [
            'kalemler' => $satirlar,
            'ara_toplam' => number_format($araToplam, 2, '.', ''),
            'toplam_indirim' => number_format($toplamIndirim, 2, '.', ''),
            'kdv_toplam' => number_format($kdvToplam, 2, '.', ''),
            'genel_toplam' => number_format($genelToplam, 2, '.', ''),
        ];
    }
}
