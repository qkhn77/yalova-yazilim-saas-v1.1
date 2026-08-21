<?php

namespace App\Services\PersonelTakip;

use App\Models\Personel\Personel;
use App\Models\Personel\PersonelBelgesi;
use App\Models\Scopes\FirmaIdTenantScope;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class PersonelBelgesiKuralServisi
{
    public function dogrula(PersonelBelgesi $belge): void
    {
        if (! $belge->firma_id && $belge->personel_id) {
            $belge->firma_id = Personel::query()
                ->withoutGlobalScope(FirmaIdTenantScope::class)
                ->whereKey($belge->personel_id)
                ->value('firma_id');
        }

        $hatalar = [];

        if (! $belge->firma_id) {
            $hatalar['firma_id'][] = 'Firma bilgisi zorunludur.';
        }

        if (! $belge->personel_id) {
            $hatalar['personel_id'][] = 'Personel bilgisi zorunludur.';
        }

        if (blank($belge->ad)) {
            $hatalar['ad'][] = 'Belge adı zorunludur.';
        }

        if (blank($belge->dosya_yolu)) {
            $hatalar['dosya_yolu'][] = 'Belge dosyası zorunludur.';
        }

        if ($belge->duzenleme_tarihi && $belge->gecerlilik_tarihi
            && Carbon::parse($belge->gecerlilik_tarihi)->lt(Carbon::parse($belge->duzenleme_tarihi))) {
            $hatalar['gecerlilik_tarihi'][] = 'Geçerlilik tarihi düzenleme tarihinden önce olamaz.';
        }

        if ($belge->uyari_tarihi && $belge->gecerlilik_tarihi
            && Carbon::parse($belge->uyari_tarihi)->gt(Carbon::parse($belge->gecerlilik_tarihi))) {
            $hatalar['uyari_tarihi'][] = 'Uyarı tarihi geçerlilik tarihinden sonra olamaz.';
        }

        if ($belge->firma_id && $belge->personel_id) {
            $personelVarMi = Personel::query()
                ->withoutGlobalScope(FirmaIdTenantScope::class)
                ->where('firma_id', $belge->firma_id)
                ->whereKey($belge->personel_id)
                ->exists();

            if (! $personelVarMi) {
                $hatalar['personel_id'][] = 'Seçilen personel bu firmaya ait değil.';
            }
        }

        if ($hatalar !== []) {
            throw ValidationException::withMessages($hatalar);
        }

        $belge->belge_turu = trim((string) ($belge->belge_turu ?: 'diger'));
        $belge->ad = trim((string) $belge->ad);
        $belge->dosya_yolu = trim((string) $belge->dosya_yolu);
        $belge->aciklama = filled($belge->aciklama) ? trim((string) $belge->aciklama) : null;
        $belge->durum = $this->durumBelirle($belge);
    }

    private function durumBelirle(PersonelBelgesi $belge): string
    {
        if (in_array((string) $belge->durum, ['iptal', 'arsiv'], true)) {
            return (string) $belge->durum;
        }

        if ($belge->gecerlilik_tarihi && Carbon::parse($belge->gecerlilik_tarihi)->lt(today())) {
            return 'suresi_doldu';
        }

        if ($belge->uyari_tarihi && Carbon::parse($belge->uyari_tarihi)->lte(today())) {
            return 'yenilenecek';
        }

        return 'gecerli';
    }
}
