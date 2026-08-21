<?php

namespace App\Services\Restoran;

use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\FaturaKalemi;
use App\Models\Restoran\RestoranAdisyonKalemi;
use App\Models\Restoran\RestoranAdisyonu;
use App\Models\Scopes\FirmaIdTenantScope;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Services\TenantContextService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RestoranFaturaServisi
{
    private const PARA_BASAMAK = 8;

    public const KAYNAK_TIPI = 'restoran_adisyon';

    public const ISLEM_TIPI = 'restoran_satis';

    /**
     * Kapali restoran adisyonundan muhasebe tarafinda bekleyen fatura olusturur.
     *
     * Stok etkisi tahsilat/adisyon kapanisi tarafinda olustugu icin fatura kalemleri
     * bilincli olarak hizmet kalemi yazilir. Boylece bekleyen fatura daha sonra
     * isleme alinsa bile restoran satisi stoktan ikinci kez dusmez.
     */
    public function bekleyenFaturaOlustur(RestoranAdisyonu $adisyon, ?string $eBelgeTipi = null): Fatura
    {
        return DB::transaction(function () use ($adisyon, $eBelgeTipi): Fatura {
            $kilitli = $this->adisyonuKilitle($adisyon);
            $this->faturayaUygunMu($kilitli, $eBelgeTipi);

            $mevcut = $this->mevcutFatura($kilitli);
            if ($mevcut) {
                return $mevcut->load('kalemler');
            }

            $ozet = $this->adisyonOzetiniHazirla($kilitli);
            if ($ozet['kalemler'] === []) {
                throw ValidationException::withMessages([
                    'kalemler' => ['Fatura olusturmak icin adisyonda faturalandirilabilir kalem bulunmalidir.'],
                ]);
            }

            $paraBirimi = strtoupper((string) ($kilitli->para_birimi ?: 'TRY'));
            $fatura = Fatura::query()->create([
                'firma_id' => (int) $kilitli->firma_id,
                'cari_id' => $kilitli->cari_id,
                'tur' => FaturaTuru::BekleyenFatura->value,
                'durum' => FaturaDurumu::Beklemede->value,
                'tarih' => $kilitli->kapanis_at ?: $kilitli->tahsilat_at ?: now(),
                'ara_toplam' => $ozet['ara_toplam'],
                'baz_ara_toplam' => $ozet['ara_toplam'],
                'toplam_indirim' => $ozet['toplam_indirim'],
                'baz_toplam_indirim' => $ozet['toplam_indirim'],
                'kdv_toplam' => $ozet['kdv_toplam'],
                'baz_kdv_toplam' => $ozet['kdv_toplam'],
                'genel_toplam' => $ozet['genel_toplam'],
                'baz_genel_toplam' => $ozet['genel_toplam'],
                'odenecek_tutar' => $ozet['genel_toplam'],
                'baz_odenecek_tutar' => $ozet['genel_toplam'],
                'odendi_tutari' => '0.00000000',
                'baz_odendi_tutari' => '0.00000000',
                'acik_tutar' => $ozet['genel_toplam'],
                'baz_acik_tutar' => $ozet['genel_toplam'],
                'odeme_durumu' => 'beklemede',
                'para_birimi' => $paraBirimi,
                'baz_para_birimi' => $paraBirimi,
                'doviz_kuru' => '1.00000000',
                'kaynak_tipi' => self::KAYNAK_TIPI,
                'islem_tipi' => self::ISLEM_TIPI,
                'islem_no' => (int) $kilitli->getKey(),
                'e_belge_tipi' => $eBelgeTipi,
                'aciklama' => 'Restoran adisyonu icin otomatik bekleyen fatura: '.$kilitli->adisyon_no,
            ]);

            foreach ($ozet['kalemler'] as $satir) {
                FaturaKalemi::query()->create(array_merge($satir, [
                    'firma_id' => (int) $kilitli->firma_id,
                    'fatura_id' => (int) $fatura->getKey(),
                    'para_birimi' => $paraBirimi,
                    'baz_para_birimi' => $paraBirimi,
                ]));
            }

            return $fatura->load('kalemler');
        });
    }

    public function bekleyenFaturayiIptalEt(RestoranAdisyonu $adisyon, string $neden): ?Fatura
    {
        return DB::transaction(function () use ($adisyon, $neden): ?Fatura {
            $kilitli = $this->adisyonuKilitle($adisyon);
            $fatura = $this->mevcutFatura($kilitli);

            if (! $fatura) {
                return null;
            }

            if ($fatura->tur !== FaturaTuru::BekleyenFatura || $fatura->durum === FaturaDurumu::Iptal) {
                return $fatura;
            }

            $fatura->forceFill([
                'durum' => FaturaDurumu::Iptal->value,
                'odeme_durumu' => 'iptal',
                'acik_tutar' => '0.00000000',
                'baz_acik_tutar' => '0.00000000',
                'iptal_nedeni' => $neden,
                'iptal_edildi_at' => now(),
            ])->save();

            return $fatura->refresh();
        });
    }

    private function adisyonuKilitle(RestoranAdisyonu $adisyon): RestoranAdisyonu
    {
        return RestoranAdisyonu::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->lockForUpdate()
            ->findOrFail($adisyon->getKey());
    }

    private function faturayaUygunMu(RestoranAdisyonu $adisyon, ?string $eBelgeTipi): void
    {
        $aktifFirmaId = app(TenantContextService::class)->aktifFirmaId();
        if ($aktifFirmaId !== null && (int) $adisyon->firma_id !== $aktifFirmaId) {
            throw ValidationException::withMessages([
                'firma_id' => ['Restoran adisyon faturasi sadece aktif firma icin olusturulabilir.'],
            ]);
        }

        if ($adisyon->durum !== RestoranAdisyonu::DURUM_KAPANDI) {
            throw ValidationException::withMessages([
                'durum' => ['Sadece kapali restoran adisyonundan bekleyen fatura olusturulabilir.'],
            ]);
        }

        if ((float) $adisyon->genel_toplam <= 0) {
            throw ValidationException::withMessages([
                'genel_toplam' => ['Fatura olusturmak icin adisyon genel toplami sifirdan buyuk olmalidir.'],
            ]);
        }

        if ($eBelgeTipi !== null && ! in_array($eBelgeTipi, ['e_fatura', 'e_arsiv', 'fatura'], true)) {
            throw ValidationException::withMessages([
                'e_belge_tipi' => ['E-belge tipi e_fatura, e_arsiv veya fatura olmalidir.'],
            ]);
        }
    }

    private function mevcutFatura(RestoranAdisyonu $adisyon): ?Fatura
    {
        return Fatura::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', (int) $adisyon->firma_id)
            ->where('kaynak_tipi', self::KAYNAK_TIPI)
            ->where('islem_tipi', self::ISLEM_TIPI)
            ->where('islem_no', (int) $adisyon->getKey())
            ->where('durum', '!=', FaturaDurumu::Iptal->value)
            ->lockForUpdate()
            ->first();
    }

    /**
     * @return array{ara_toplam:string,toplam_indirim:string,kdv_toplam:string,genel_toplam:string,kalemler:array<int,array<string,mixed>>}
     */
    private function adisyonOzetiniHazirla(RestoranAdisyonu $adisyon): array
    {
        $kalemler = RestoranAdisyonKalemi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', (int) $adisyon->firma_id)
            ->where('adisyon_id', (int) $adisyon->getKey())
            ->where('durum', '!=', RestoranAdisyonKalemi::DURUM_IPTAL)
            ->orderBy('id')
            ->get();

        $satirlar = [];
        $araToplam = 0.0;
        $toplamIndirim = 0.0;
        $kdvToplam = 0.0;
        $genelToplam = 0.0;
        $satirNo = 1;

        foreach ($kalemler as $kalem) {
            $miktar = round((float) $kalem->miktar, 4);
            $birimFiyat = $this->paraFloat((float) $kalem->birim_fiyat);
            $indirim = $kalem->ikram_mi ? $this->paraFloat((float) $kalem->ikram_tutari) : $this->paraFloat((float) $kalem->iskonto_tutari);
            $netTutar = $kalem->ikram_mi ? 0.0 : max(0.0, $this->paraFloat((float) $kalem->ara_tutar - (float) $kalem->iskonto_tutari));
            $kdvTutari = $kalem->ikram_mi ? 0.0 : $this->paraFloat((float) $kalem->kdv_tutari);
            $satirGenelToplam = $kalem->ikram_mi ? 0.0 : $this->paraFloat((float) $kalem->toplam_tutar);

            $araToplam += $netTutar;
            $toplamIndirim += $indirim;
            $kdvToplam += $kdvTutari;
            $genelToplam += $satirGenelToplam;

            $satirlar[] = [
                'satir_no' => $satirNo++,
                'kalem_tipi' => 'restoran_adisyon_kalemi',
                'stok_id' => null,
                'birim' => 'AD',
                'hizmet_mi' => true,
                'aciklama' => (string) $kalem->urun_adi,
                'miktar' => $miktar,
                'birim_fiyat' => $birimFiyat,
                'baz_birim_fiyat' => $birimFiyat,
                'indirim_orani' => 0,
                'satir_indirim_tutari' => $indirim,
                'indirim_tutari' => $indirim,
                'baz_indirim_tutari' => $indirim,
                'net_tutar' => $netTutar,
                'baz_net_tutar' => $netTutar,
                'kdv_orani' => round((float) $kalem->kdv_orani, 2),
                'kdv_tutari' => $kdvTutari,
                'baz_kdv_tutari' => $kdvTutari,
                'satir_toplami' => $netTutar,
                'baz_satir_toplami' => $netTutar,
                'satir_genel_toplam' => $satirGenelToplam,
                'baz_satir_genel_toplam' => $satirGenelToplam,
                'toplam' => $satirGenelToplam,
            ];
        }

        $servisUcreti = $this->paraFloat((float) $adisyon->servis_ucreti);
        if ($servisUcreti > 0) {
            $araToplam += $servisUcreti;
            $genelToplam += $servisUcreti;
            $satirlar[] = [
                'satir_no' => $satirNo,
                'kalem_tipi' => 'restoran_servis_ucreti',
                'stok_id' => null,
                'birim' => 'AD',
                'hizmet_mi' => true,
                'aciklama' => 'Servis ucreti',
                'miktar' => 1,
                'birim_fiyat' => $servisUcreti,
                'baz_birim_fiyat' => $servisUcreti,
                'indirim_orani' => 0,
                'satir_indirim_tutari' => 0,
                'indirim_tutari' => 0,
                'baz_indirim_tutari' => 0,
                'net_tutar' => $servisUcreti,
                'baz_net_tutar' => $servisUcreti,
                'kdv_orani' => 0,
                'kdv_tutari' => 0,
                'baz_kdv_tutari' => 0,
                'satir_toplami' => $servisUcreti,
                'baz_satir_toplami' => $servisUcreti,
                'satir_genel_toplam' => $servisUcreti,
                'baz_satir_genel_toplam' => $servisUcreti,
                'toplam' => $servisUcreti,
            ];
        }

        return [
            'ara_toplam' => $this->para($araToplam),
            'toplam_indirim' => $this->para($toplamIndirim),
            'kdv_toplam' => $this->para($kdvToplam),
            'genel_toplam' => $this->para($genelToplam),
            'kalemler' => $satirlar,
        ];
    }

    private function para(float $tutar): string
    {
        return number_format($this->paraFloat($tutar), self::PARA_BASAMAK, '.', '');
    }

    private function paraFloat(float $tutar): float
    {
        return round($tutar, self::PARA_BASAMAK);
    }
}
