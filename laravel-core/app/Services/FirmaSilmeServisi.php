<?php

namespace App\Services;

use App\Models\Firma;
use App\Models\User;
use App\Support\DenetimYardimcisi;
use App\Support\FirmaMuhasebeGuvenlikYardimcisi;
use App\Support\SaaSemaYardimcisi;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FirmaSilmeServisi
{
    /**
     * @return array<string, int>
     */
    public function sil(Firma $firma, User $islemYapan): array
    {
        if (! ((bool) ($islemYapan->super_admin_mi ?? false) || (bool) ($islemYapan->is_admin ?? false))) {
            throw ValidationException::withMessages([
                'firma' => 'Firma silme işlemi yalnızca süper admin tarafından yapılabilir.',
            ]);
        }

        if (! SaaSemaYardimcisi::firmalarTablosuVarMi()) {
            throw ValidationException::withMessages([
                'firma' => 'Firmalar tablosu bulunamadığı için işlem yapılamıyor.',
            ]);
        }

        if ($firma->trashed()) {
            throw ValidationException::withMessages([
                'firma' => 'Bu firma zaten silinmiş.',
            ]);
        }

        if (FirmaMuhasebeGuvenlikYardimcisi::muhasebeKaydiVarMi((int) $firma->getKey())) {
            throw ValidationException::withMessages([
                'firma' => 'Bu firmaya ait muhasebe kayıtları (fatura, cari, stok veya finans hareketi vb.) bulunduğu için firma silinemez. Firmayı durumunu “askıda” yaparak pasifleştirmeyi veya arşiv akışını kullanın; tam silme için önce ilgili yasal saklama kurallarını değerlendirin.',
            ]);
        }

        /** @var array<string, int> $sayaclar */
        $sayaclar = DB::transaction(function () use ($firma, $islemYapan): array {
            $sayaclar = [];
            $firma->tumIliskileriSil($sayaclar, (int) $islemYapan->getKey());
            $firma->delete(); // Soft delete

            return $sayaclar;
        });

        DenetimYardimcisi::kaydet(
            'firma_silindi',
            Firma::class,
            (int) $firma->getKey(),
            (int) $firma->getKey(),
            null,
            $sayaclar
        );

        return $sayaclar;
    }
}
