<?php

namespace App\Policies;

use App\Models\Muhasebe\Cari;
use App\Models\User;
use App\Support\MuhasebeYetkiSablonlari;

class CariPolicy extends BasePolicy
{
    public function viewAny(User $kullanici): bool
    {
        return $this->herhangiBirMuhasebeYetkisi($kullanici, [
            ['kod' => MuhasebeYetkiSablonlari::CARI_GORUNTULE, 'yazma' => false],
            ['kod' => MuhasebeYetkiSablonlari::CARI_OLUSTUR, 'yazma' => true],
            ['kod' => MuhasebeYetkiSablonlari::CARI_GUNCELLE, 'yazma' => true],
            ['kod' => MuhasebeYetkiSablonlari::CARI_SIL, 'yazma' => true],
        ]);
    }

    public function view(User $kullanici, Cari $cari): bool
    {
        $gorur = $this->yetkiKontrol(
            $kullanici,
            MuhasebeYetkiSablonlari::CARI_GORUNTULE,
            'muhasebe',
            false
        ) || $this->yetkiKontrol(
            $kullanici,
            MuhasebeYetkiSablonlari::CARI_GUNCELLE,
            'muhasebe',
            true
        );

        if (! $gorur) {
            return false;
        }

        return $this->kayitAktifFirmayaAitMi($kullanici, $cari);
    }

    public function create(User $kullanici): bool
    {
        return $this->yetkiKontrol(
            $kullanici,
            MuhasebeYetkiSablonlari::CARI_OLUSTUR,
            'muhasebe',
            true
        );
    }

    public function update(User $kullanici, Cari $cari): bool
    {
        if (! $this->yetkiKontrol(
            $kullanici,
            MuhasebeYetkiSablonlari::CARI_GUNCELLE,
            'muhasebe',
            true
        )) {
            return false;
        }

        return $this->kayitAktifFirmayaAitMi($kullanici, $cari);
    }

    public function delete(User $kullanici, Cari $cari): bool
    {
        if (! $this->yetkiKontrol(
            $kullanici,
            MuhasebeYetkiSablonlari::CARI_SIL,
            'muhasebe',
            true
        )) {
            return false;
        }

        return $this->kayitAktifFirmayaAitMi($kullanici, $cari);
    }
}
