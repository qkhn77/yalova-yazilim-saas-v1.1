<?php

namespace App\Policies;

use App\Models\Muhasebe\StokKarti;
use App\Models\User;
use App\Support\MuhasebeYetkiSablonlari;

class StokKartiPolicy extends BasePolicy
{
    public function viewAny(User $kullanici): bool
    {
        return $this->herhangiBirMuhasebeYetkisi($kullanici, [
            ['kod' => MuhasebeYetkiSablonlari::STOK_GORUNTULE, 'yazma' => false],
            ['kod' => MuhasebeYetkiSablonlari::STOK_OLUSTUR, 'yazma' => true],
            ['kod' => MuhasebeYetkiSablonlari::STOK_GUNCELLE, 'yazma' => true],
            ['kod' => MuhasebeYetkiSablonlari::STOK_SIL, 'yazma' => true],
        ]);
    }

    public function view(User $kullanici, StokKarti $stokKarti): bool
    {
        $gorur = $this->yetkiKontrol(
            $kullanici,
            MuhasebeYetkiSablonlari::STOK_GORUNTULE,
            'muhasebe',
            false
        ) || $this->yetkiKontrol(
            $kullanici,
            MuhasebeYetkiSablonlari::STOK_GUNCELLE,
            'muhasebe',
            true
        );

        if (! $gorur) {
            return false;
        }

        return $this->kayitAktifFirmayaAitMi($kullanici, $stokKarti);
    }

    public function create(User $kullanici): bool
    {
        return $this->yetkiKontrol(
            $kullanici,
            MuhasebeYetkiSablonlari::STOK_OLUSTUR,
            'muhasebe',
            true
        );
    }

    public function update(User $kullanici, StokKarti $stokKarti): bool
    {
        if (! $this->yetkiKontrol(
            $kullanici,
            MuhasebeYetkiSablonlari::STOK_GUNCELLE,
            'muhasebe',
            true
        )) {
            return false;
        }

        return $this->kayitAktifFirmayaAitMi($kullanici, $stokKarti);
    }

    public function delete(User $kullanici, StokKarti $stokKarti): bool
    {
        if (! $this->yetkiKontrol(
            $kullanici,
            MuhasebeYetkiSablonlari::STOK_SIL,
            'muhasebe',
            true
        )) {
            return false;
        }

        return $this->kayitAktifFirmayaAitMi($kullanici, $stokKarti);
    }
}
