<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\FaturaKalemi;
use App\Models\Muhasebe\Masraf;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use Illuminate\Support\Facades\DB;

final class MasrafFaturaKayitServisi
{
    public function __construct(
        private readonly MasrafKayitServisi $masrafKayitServisi,
        private readonly MasrafFaturaBaglantiServisi $baglantiServisi,
        private readonly FaturaIslemServisi $faturaIslemServisi,
    ) {}

    /**
     * Masrafı faturasız, mevcut faturaya bağlı veya yeni gider faturası ile tek transaction'da kaydeder.
     *
     * @param array<string, mixed> $masrafAlanlari
     * @param array<string, mixed> $faturaAlanlari
     */
    public function kaydet(
        int $firmaId,
        array $masrafAlanlari,
        string $faturaModu,
        array $faturaAlanlari,
        ?int $kullaniciId,
        string $idempotencyKey,
    ): Masraf {
        if ($faturaModu === 'yok') {
            return $this->masrafKayitServisi->kaydet($firmaId, $masrafAlanlari, $kullaniciId, $idempotencyKey);
        }

        if (! in_array($faturaModu, ['mevcut', 'yeni'], true)) {
            throw new IsKuraliIstisnasi('Geçersiz fatura bağlantı seçimi.');
        }

        return DB::transaction(function () use ($firmaId, $masrafAlanlari, $faturaModu, $faturaAlanlari, $kullaniciId, $idempotencyKey): Masraf {
            $mevcutMasraf = Masraf::query()
                ->where('firma_id', $firmaId)
                ->where('idempotency_key', $idempotencyKey)
                ->with('faturaDagitimlari')
                ->lockForUpdate()
                ->first();

            if ($mevcutMasraf) {
                return $mevcutMasraf;
            }

            $masraf = $this->masrafKayitServisi->kaydet(
                $firmaId,
                $masrafAlanlari,
                $kullaniciId,
                $idempotencyKey,
            );

            $faturaId = $faturaModu === 'mevcut'
                ? (int) ($faturaAlanlari['fatura_id'] ?? 0)
                : (int) $this->yeniGiderFaturasiOlustur($firmaId, $masraf, $faturaAlanlari)->getKey();

            if ($faturaId < 1) {
                throw new IsKuraliIstisnasi('Masrafa bağlanacak gider faturası seçilmelidir.');
            }

            $this->baglantiServisi->bagla($firmaId, (int) $masraf->getKey(), $faturaId, (string) $masraf->tutar);

            return $masraf->fresh();
        }, 3);
    }

    /** @param array<string, mixed> $alanlar */
    private function yeniGiderFaturasiOlustur(int $firmaId, Masraf $masraf, array $alanlar): Fatura
    {
        $cariId = (int) ($alanlar['fatura_cari_id'] ?? 0);
        if ($cariId < 1 || ! Cari::query()->where('firma_id', $firmaId)->whereKey($cariId)->exists()) {
            throw new IsKuraliIstisnasi('Yeni gider faturası için aktif firmaya ait cari seçilmelidir.');
        }

        $tutar = bcadd((string) $masraf->tutar, '0', 2);
        $paraBirimi = strtoupper((string) ($masraf->para_birimi ?: 'TRY'));
        $aciklama = trim((string) ($alanlar['fatura_aciklama'] ?? '')) ?: (string) $masraf->aciklama;

        $kalemler = array_values(array_filter(
            (array) ($alanlar['kalemler'] ?? []),
            static fn (mixed $kalem): bool => is_array($kalem),
        ));

        if ($kalemler !== []) {
            $hesap = FaturaKaynagi::hesaplaFormKalemleriVeOzet([
                ...$alanlar,
                'tarih' => $alanlar['fatura_tarihi'] ?? $masraf->tarih,
                'para_birimi' => $paraBirimi,
                'doviz_kuru' => 1,
                'kalemler' => $kalemler,
                'aciklama' => $aciklama,
                'odendi_tutari' => 0,
            ]);
        } else {
            $hesap = [
                'tarih' => $alanlar['fatura_tarihi'] ?? $masraf->tarih,
                'ara_toplam' => $tutar,
                'baz_ara_toplam' => $tutar,
                'toplam_indirim' => 0,
                'baz_toplam_indirim' => 0,
                'kdv_toplam' => 0,
                'baz_kdv_toplam' => 0,
                'tevkifat_orani' => 0,
                'genel_toplam' => $tutar,
                'baz_genel_toplam' => $tutar,
                'odenecek_tutar' => $tutar,
                'baz_odenecek_tutar' => $tutar,
                'odendi_tutari' => 0,
                'baz_odendi_tutari' => 0,
                'acik_tutar' => $tutar,
                'baz_acik_tutar' => $tutar,
                'genel_indirim_tutari' => 0,
                'kalemler' => [[
                    'satir_no' => 1,
                    'kalem_tipi' => 'hizmet_kalemi',
                    'stok_id' => null,
                    'birim' => 'AD',
                    'hizmet_mi' => true,
                    'aciklama' => $aciklama,
                    'miktar' => 1,
                    'birim_fiyat' => $tutar,
                    'baz_birim_fiyat' => $tutar,
                    'indirim_orani' => 0,
                    'kdv_orani' => 0,
                    'satir_indirim_tutari' => 0,
                    'indirim_tutari' => 0,
                    'baz_indirim_tutari' => 0,
                    'net_tutar' => $tutar,
                    'baz_net_tutar' => $tutar,
                    'kdv_tutari' => 0,
                    'baz_kdv_tutari' => 0,
                    'satir_toplami' => $tutar,
                    'baz_satir_toplami' => $tutar,
                    'satir_genel_toplam' => $tutar,
                    'baz_satir_genel_toplam' => $tutar,
                    'para_birimi' => $paraBirimi,
                    'baz_para_birimi' => $paraBirimi,
                    'toplam' => $tutar,
                ]],
            ];
        }

        $faturaTutar = bcadd((string) ($hesap['odenecek_tutar'] ?? $hesap['genel_toplam'] ?? 0), '0', 2);
        if (bccomp($faturaTutar, '0', 2) <= 0) {
            throw new IsKuraliIstisnasi('Gider faturası toplamı sıfırdan büyük olmalıdır.');
        }

        $fatura = Fatura::query()->create([
            'firma_id' => $firmaId,
            'isletme_proje_id' => $masraf->isletme_proje_id,
            'cari_id' => $cariId,
            'tur' => FaturaTuru::Gider->value,
            'durum' => FaturaDurumu::Taslak->value,
            'tarih' => $hesap['tarih'] ?? $alanlar['fatura_tarihi'] ?? $masraf->tarih,
            'vade_tarihi' => $alanlar['fatura_vade_tarihi'] ?? null,
            'doviz_kuru' => 1,
            'ara_toplam' => $hesap['ara_toplam'] ?? 0,
            'baz_ara_toplam' => $hesap['baz_ara_toplam'] ?? ($hesap['ara_toplam'] ?? 0),
            'toplam_indirim' => $hesap['toplam_indirim'] ?? 0,
            'baz_toplam_indirim' => $hesap['baz_toplam_indirim'] ?? ($hesap['toplam_indirim'] ?? 0),
            'kdv_toplam' => $hesap['kdv_toplam'] ?? 0,
            'baz_kdv_toplam' => $hesap['baz_kdv_toplam'] ?? ($hesap['kdv_toplam'] ?? 0),
            'tevkifat_orani' => $hesap['tevkifat_orani'] ?? 0,
            'genel_toplam' => $hesap['genel_toplam'] ?? 0,
            'baz_genel_toplam' => $hesap['baz_genel_toplam'] ?? ($hesap['genel_toplam'] ?? 0),
            'odenecek_tutar' => $hesap['odenecek_tutar'] ?? 0,
            'baz_odenecek_tutar' => $hesap['baz_odenecek_tutar'] ?? ($hesap['odenecek_tutar'] ?? 0),
            'odendi_tutari' => 0,
            'baz_odendi_tutari' => 0,
            'acik_tutar' => $hesap['acik_tutar'] ?? ($hesap['odenecek_tutar'] ?? 0),
            'baz_acik_tutar' => $hesap['baz_acik_tutar'] ?? ($hesap['acik_tutar'] ?? 0),
            'genel_indirim_tutari' => $hesap['genel_indirim_tutari'] ?? 0,
            'kdv_dahil_fiyatlandirma_mi' => false,
            'para_birimi' => $paraBirimi,
            'baz_para_birimi' => $paraBirimi,
            'aciklama' => $aciklama,
            'notlar' => $alanlar['fatura_notlar'] ?? null,
            'kaynak_tipi' => 'masraf',
            'islem_tipi' => 'Masraf',
            'islem_no' => (int) $masraf->getKey(),
        ]);

        foreach ((array) ($hesap['kalemler'] ?? []) as $index => $kalem) {
            FaturaKalemi::query()->create([
                'firma_id' => $firmaId,
                'fatura_id' => (int) $fatura->getKey(),
                'satir_no' => (int) ($kalem['satir_no'] ?? ($index + 1)),
                'kalem_tipi' => (string) ($kalem['kalem_tipi'] ?? 'hizmet_kalemi'),
                'stok_id' => ! empty($kalem['stok_id']) ? (int) $kalem['stok_id'] : null,
                'birim' => (string) ($kalem['birim'] ?? 'AD'),
                'hizmet_mi' => (bool) ($kalem['hizmet_mi'] ?? (($kalem['kalem_tipi'] ?? '') === 'hizmet_kalemi')),
                'aciklama' => $kalem['aciklama'] ?? null,
                'miktar' => $kalem['miktar'] ?? 0,
                'birim_fiyat' => $kalem['birim_fiyat'] ?? 0,
                'baz_birim_fiyat' => $kalem['baz_birim_fiyat'] ?? ($kalem['birim_fiyat'] ?? 0),
                'indirim_orani' => $kalem['indirim_orani'] ?? 0,
                'kdv_orani' => $kalem['kdv_orani'] ?? 0,
                'satir_indirim_tutari' => $kalem['satir_indirim_tutari'] ?? ($kalem['indirim_tutari'] ?? 0),
                'indirim_tutari' => $kalem['indirim_tutari'] ?? 0,
                'baz_indirim_tutari' => $kalem['baz_indirim_tutari'] ?? ($kalem['indirim_tutari'] ?? 0),
                'net_tutar' => $kalem['net_tutar'] ?? 0,
                'baz_net_tutar' => $kalem['baz_net_tutar'] ?? ($kalem['net_tutar'] ?? 0),
                'kdv_tutari' => $kalem['kdv_tutari'] ?? 0,
                'baz_kdv_tutari' => $kalem['baz_kdv_tutari'] ?? ($kalem['kdv_tutari'] ?? 0),
                'satir_toplami' => $kalem['satir_toplami'] ?? 0,
                'baz_satir_toplami' => $kalem['baz_satir_toplami'] ?? ($kalem['satir_toplami'] ?? 0),
                'satir_genel_toplam' => $kalem['satir_genel_toplam'] ?? ($kalem['toplam'] ?? 0),
                'baz_satir_genel_toplam' => $kalem['baz_satir_genel_toplam'] ?? ($kalem['satir_genel_toplam'] ?? ($kalem['toplam'] ?? 0)),
                'para_birimi' => $paraBirimi,
                'baz_para_birimi' => $paraBirimi,
                'toplam' => $kalem['toplam'] ?? ($kalem['satir_genel_toplam'] ?? 0),
            ]);
        }

        $this->faturaIslemServisi->faturayiOnayla($fatura);

        return $fatura->fresh();
    }
}
