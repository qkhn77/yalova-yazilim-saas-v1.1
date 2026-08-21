<?php

namespace App\TeknikServis\Servisler;

use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\FaturaKalemi;
use App\Models\TeknikServis\TeknikServisTahsilati;
use BackedEnum;
use Carbon\CarbonInterface;

final class TeknikServisBekleyenFaturaSenkronKontrolu
{
    /**
     * @param  array<string, mixed>  $beklenenFaturaAlanlari
     * @param  array<int, array<string, mixed>>  $beklenenKalemler
     */
    public function faturaVeKalemlerAyniMi(
        Fatura $fatura,
        array $beklenenFaturaAlanlari,
        array $beklenenKalemler,
        int $firmaId,
        string $paraBirimi
    ): bool {
        if (! $this->faturaAlanlariAyniMi($fatura, $beklenenFaturaAlanlari)) {
            return false;
        }

        return $this->faturaKalemleriAyniMi($fatura, $beklenenKalemler, $firmaId, $paraBirimi);
    }

    public function tahsilatFaturaBaglantisiEksikMi(int $firmaId, int $servisId, int $faturaId): bool
    {
        return TeknikServisTahsilati::query()
            ->withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->where('teknik_servis_kaydi_id', $servisId)
            ->where(function ($query) use ($faturaId): void {
                $query->whereNull('satis_faturasi_id')
                    ->orWhere('satis_faturasi_id', '!=', $faturaId);
            })
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $beklenen
     */
    private function faturaAlanlariAyniMi(Fatura $fatura, array $beklenen): bool
    {
        foreach ($beklenen as $alan => $deger) {
            $mevcut = $fatura->getAttribute($alan);

            if ($this->normalizeEt($mevcut, $alan) !== $this->normalizeEt($deger, $alan)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, array<string, mixed>>  $beklenenKalemler
     */
    private function faturaKalemleriAyniMi(Fatura $fatura, array $beklenenKalemler, int $firmaId, string $paraBirimi): bool
    {
        $alanlar = [
            'satir_no',
            'kalem_tipi',
            'stok_id',
            'birim',
            'hizmet_mi',
            'aciklama',
            'miktar',
            'birim_fiyat',
            'baz_birim_fiyat',
            'indirim_orani',
            'kdv_orani',
            'satir_indirim_tutari',
            'indirim_tutari',
            'baz_indirim_tutari',
            'net_tutar',
            'baz_net_tutar',
            'kdv_tutari',
            'baz_kdv_tutari',
            'satir_toplami',
            'baz_satir_toplami',
            'satir_genel_toplam',
            'baz_satir_genel_toplam',
            'toplam',
            'firma_id',
            'para_birimi',
            'baz_para_birimi',
        ];

        $beklenen = array_map(function (array $kalem) use ($alanlar, $firmaId, $paraBirimi): array {
            $kalem['firma_id'] = $firmaId;
            $kalem['para_birimi'] = $paraBirimi;
            $kalem['baz_para_birimi'] = 'TRY';

            return $this->kalemSatiriniNormalizeEt($kalem, $alanlar);
        }, $beklenenKalemler);

        $mevcut = FaturaKalemi::query()
            ->withoutGlobalScopes()
            ->where('fatura_id', (int) $fatura->getKey())
            ->orderBy('satir_no')
            ->orderBy('id')
            ->get($alanlar)
            ->map(fn (FaturaKalemi $kalem): array => $this->kalemSatiriniNormalizeEt($kalem->getAttributes(), $alanlar))
            ->all();

        return $mevcut === $beklenen;
    }

    /**
     * @param  array<string, mixed>  $satir
     * @param  array<int, string>  $alanlar
     * @return array<string, mixed>
     */
    private function kalemSatiriniNormalizeEt(array $satir, array $alanlar): array
    {
        $sonuc = [];

        foreach ($alanlar as $alan) {
            $sonuc[$alan] = $this->normalizeEt($satir[$alan] ?? null, $alan);
        }

        return $sonuc;
    }

    private function normalizeEt(mixed $deger, string $alan): mixed
    {
        if ($deger instanceof BackedEnum) {
            $deger = $deger->value;
        }

        if ($deger instanceof CarbonInterface) {
            return $deger->format('Y-m-d H:i:s');
        }

        if (in_array($alan, ['firma_id', 'cari_id', 'stok_id', 'satir_no'], true)) {
            return $deger === null ? null : (int) $deger;
        }

        if ($alan === 'hizmet_mi') {
            return (bool) $deger;
        }

        if (in_array($alan, [
            'ara_toplam',
            'toplam_indirim',
            'kdv_toplam',
            'genel_toplam',
            'odenecek_tutar',
            'odendi_tutari',
            'acik_tutar',
            'birim_fiyat',
            'baz_birim_fiyat',
            'indirim_orani',
            'kdv_orani',
            'satir_indirim_tutari',
            'indirim_tutari',
            'baz_indirim_tutari',
            'net_tutar',
            'baz_net_tutar',
            'kdv_tutari',
            'baz_kdv_tutari',
            'satir_toplami',
            'baz_satir_toplami',
            'satir_genel_toplam',
            'baz_satir_genel_toplam',
            'toplam',
        ], true)) {
            return number_format((float) $deger, $alan === 'indirim_orani' || $alan === 'miktar' ? 4 : 2, '.', '');
        }

        if ($alan === 'miktar') {
            return number_format((float) $deger, 4, '.', '');
        }

        return $deger === null ? null : (string) $deger;
    }
}
