<?php

namespace App\Services\Restoran;

use App\Models\Muhasebe\StokKarti;
use App\Models\Restoran\RestoranMenuKategorisi;
use App\Models\Restoran\RestoranMenuUrunu;
use App\Models\Scopes\FirmaIdTenantScope;
use Illuminate\Validation\ValidationException;

final class RestoranMenuUrunKuralServisi
{
    public function dogrula(RestoranMenuUrunu $urun): void
    {
        if (! $urun->firma_id) {
            return;
        }

        $hatalar = [];
        $kategori = $this->kategori($urun);
        $stok = $this->stok($urun);

        if ($urun->kategori_id && ! $kategori) {
            $hatalar['kategori_id'][] = 'Seçilen menü kategorisi bu firmaya ait değil.';
        }

        if ($urun->stok_karti_id && ! $stok) {
            $hatalar['stok_karti_id'][] = 'Seçilen stok kartı bu firmaya ait değil.';
        }

        if ($stok) {
            $urun->ad = $urun->ad ?: $stok->ad;
            if ((float) ($urun->fiyat ?? 0) <= 0) {
                $urun->fiyat = (float) ($stok->satis_fiyati ?? 0);
            }
            if ((float) ($urun->kdv_orani ?? 0) <= 0) {
                $urun->kdv_orani = (float) ($stok->kdv_orani ?? 0);
            }
        }

        if (trim((string) $urun->ad) === '') {
            $hatalar['ad'][] = 'Menü ürün adı boş olamaz.';
        }

        if ((float) ($urun->fiyat ?? 0) < 0) {
            $hatalar['fiyat'][] = 'Menü ürün fiyatı negatif olamaz.';
        }

        if ((float) ($urun->kdv_orani ?? 0) < 0) {
            $hatalar['kdv_orani'][] = 'KDV oranı negatif olamaz.';
        }

        if ($hatalar !== []) {
            throw ValidationException::withMessages($hatalar);
        }
    }

    private function kategori(RestoranMenuUrunu $urun): ?RestoranMenuKategorisi
    {
        if (! $urun->kategori_id) {
            return null;
        }

        return RestoranMenuKategorisi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $urun->firma_id)
            ->whereKey($urun->kategori_id)
            ->first();
    }

    private function stok(RestoranMenuUrunu $urun): ?StokKarti
    {
        if (! $urun->stok_karti_id) {
            return null;
        }

        return StokKarti::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $urun->firma_id)
            ->whereKey($urun->stok_karti_id)
            ->first();
    }
}
