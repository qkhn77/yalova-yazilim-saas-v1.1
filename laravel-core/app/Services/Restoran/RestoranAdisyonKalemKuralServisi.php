<?php

namespace App\Services\Restoran;

use App\Models\Muhasebe\StokKarti;
use App\Models\Personel\Personel;
use App\Models\Restoran\RestoranAdisyonKalemi;
use App\Models\Restoran\RestoranAdisyonu;
use App\Models\Scopes\FirmaIdTenantScope;
use Illuminate\Validation\ValidationException;

final class RestoranAdisyonKalemKuralServisi
{
    public function hazirlaVeDogrula(RestoranAdisyonKalemi $kalem): void
    {
        if (! $kalem->firma_id && $kalem->adisyon_id) {
            $adisyonFirmaId = RestoranAdisyonu::query()
                ->withoutGlobalScope(FirmaIdTenantScope::class)
                ->whereKey($kalem->adisyon_id)
                ->value('firma_id');

            if ($adisyonFirmaId) {
                $kalem->firma_id = (int) $adisyonFirmaId;
            }
        }

        if (! $kalem->firma_id || ! $kalem->adisyon_id) {
            return;
        }

        $kalem->durum = $kalem->durum ?: RestoranAdisyonKalemi::DURUM_YENI;

        $hatalar = [];
        $adisyon = $this->adisyon($kalem);
        $stok = $this->stok($kalem);

        if (! $adisyon) {
            $hatalar['adisyon_id'][] = 'Seçilen adisyon bu firmaya ait değil.';
        } elseif (in_array((string) $adisyon->durum, [RestoranAdisyonu::DURUM_KAPANDI, RestoranAdisyonu::DURUM_IPTAL], true)) {
            $hatalar['adisyon_id'][] = 'Kapalı veya iptal adisyona kalem eklenemez.';
        }

        if ($kalem->stok_karti_id && ! $stok) {
            $hatalar['stok_karti_id'][] = 'Seçilen ürün bu firmaya ait değil.';
        }

        if ($kalem->hazirlayan_personel_id && ! $this->aktifPersonelVarMi((int) $kalem->firma_id, (int) $kalem->hazirlayan_personel_id)) {
            $hatalar['hazirlayan_personel_id'][] = 'Hazırlayan personel bu firmaya ait aktif personel değil.';
        }

        if ($stok) {
            $kalem->urun_adi = $kalem->urun_adi ?: $stok->ad;
            if ((float) ($kalem->birim_fiyat ?? 0) <= 0) {
                $kalem->birim_fiyat = (float) ($stok->satis_fiyati ?? 0);
            }
            if ((float) ($kalem->kdv_orani ?? 0) <= 0) {
                $kalem->kdv_orani = (float) ($stok->kdv_orani ?? 0);
            }
        }

        if (trim((string) $kalem->urun_adi) === '') {
            $hatalar['urun_adi'][] = 'Ürün adı boş olamaz.';
        }

        $this->tutarHazirla($kalem, $hatalar);

        if (! in_array((string) $kalem->durum, [
            RestoranAdisyonKalemi::DURUM_YENI,
            RestoranAdisyonKalemi::DURUM_HAZIRLANIYOR,
            RestoranAdisyonKalemi::DURUM_HAZIR,
            RestoranAdisyonKalemi::DURUM_SERVIS_EDILDI,
            RestoranAdisyonKalemi::DURUM_IPTAL,
        ], true)) {
            $hatalar['durum'][] = 'Adisyon kalemi durumu geçerli değil.';
        }

        if ($hatalar !== []) {
            throw ValidationException::withMessages($hatalar);
        }
    }

    public function adisyonToplamlariniGuncelle(RestoranAdisyonKalemi $kalem): void
    {
        if (! $kalem->adisyon_id || ! $kalem->firma_id) {
            return;
        }

        $toplamlar = RestoranAdisyonKalemi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $kalem->firma_id)
            ->where('adisyon_id', $kalem->adisyon_id)
            ->where('durum', '!=', RestoranAdisyonKalemi::DURUM_IPTAL)
            ->selectRaw('COALESCE(SUM(ara_tutar), 0) as ara_toplam')
            ->selectRaw('COALESCE(SUM(iskonto_tutari), 0) as indirim_toplam')
            ->selectRaw('COALESCE(SUM(ikram_tutari), 0) as ikram_toplam')
            ->selectRaw('COALESCE(SUM(kdv_tutari), 0) as kdv_toplam')
            ->selectRaw('COALESCE(SUM(toplam_tutar), 0) as genel_toplam')
            ->first();

        $adisyon = RestoranAdisyonu::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $kalem->firma_id)
            ->whereKey($kalem->adisyon_id)
            ->first();

        if (! $adisyon || ! $toplamlar) {
            return;
        }

        $adisyon->forceFill([
            'ara_toplam' => round((float) $toplamlar->ara_toplam, 2),
            'indirim_toplam' => round((float) $toplamlar->indirim_toplam, 2),
            'ikram_toplam' => round((float) $toplamlar->ikram_toplam, 2),
            'kdv_toplam' => round((float) $toplamlar->kdv_toplam, 2),
            'genel_toplam' => round((float) $toplamlar->genel_toplam + (float) ($adisyon->servis_ucreti ?? 0), 2),
        ])->saveQuietly();
    }

    /**
     * @param  array<string, list<string>>  $hatalar
     */
    private function tutarHazirla(RestoranAdisyonKalemi $kalem, array &$hatalar): void
    {
        $miktar = (float) ($kalem->miktar ?? 0);
        $birimFiyat = (float) ($kalem->birim_fiyat ?? 0);
        $kdvOrani = (float) ($kalem->kdv_orani ?? 0);
        $iskonto = max(0.0, (float) ($kalem->iskonto_tutari ?? 0));
        $brut = round($miktar * $birimFiyat, 2);

        if ($miktar <= 0) {
            $hatalar['miktar'][] = 'Miktar sıfırdan büyük olmalıdır.';
        }

        if ($birimFiyat < 0) {
            $hatalar['birim_fiyat'][] = 'Birim fiyat negatif olamaz.';
        }

        if ($kdvOrani < 0) {
            $hatalar['kdv_orani'][] = 'KDV oranı negatif olamaz.';
        }

        if ($iskonto > $brut) {
            $hatalar['iskonto_tutari'][] = 'İskonto tutarı satır ara tutarını aşamaz.';
        }

        $net = max(0.0, $brut - $iskonto);
        $kdv = round($net * $kdvOrani / 100, 2);
        $satirToplam = round($net + $kdv, 2);
        $ikramMi = (bool) ($kalem->ikram_mi ?? false);

        $kalem->ara_tutar = $brut;
        $kalem->iskonto_tutari = $iskonto;
        $kalem->ikram_tutari = $ikramMi ? $satirToplam : 0;
        $kalem->kdv_tutari = $ikramMi ? 0 : $kdv;
        $kalem->toplam_tutar = $ikramMi ? 0 : $satirToplam;
    }

    private function adisyon(RestoranAdisyonKalemi $kalem): ?RestoranAdisyonu
    {
        return RestoranAdisyonu::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $kalem->firma_id)
            ->whereKey($kalem->adisyon_id)
            ->first();
    }

    private function stok(RestoranAdisyonKalemi $kalem): ?StokKarti
    {
        if (! $kalem->stok_karti_id) {
            return null;
        }

        return StokKarti::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $kalem->firma_id)
            ->whereKey($kalem->stok_karti_id)
            ->first();
    }

    private function aktifPersonelVarMi(int $firmaId, int $personelId): bool
    {
        return Personel::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->whereKey($personelId)
            ->where('durum', Personel::DURUM_AKTIF)
            ->exists();
    }
}
