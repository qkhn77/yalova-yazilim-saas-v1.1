<?php

namespace App\Policies;

use App\Models\Muhasebe\PosHesabi;
use App\Models\User;
use App\Support\MuhasebeYetkiSablonlari;

class PosHesabiPolicy extends BasePolicy
{
    public function viewAny(User $kullanici): bool
    {
        return $this->herhangiBirMuhasebeYetkisi($kullanici, [
            ['kod' => MuhasebeYetkiSablonlari::POS_GORUNTULE, 'yazma' => false],
            ['kod' => MuhasebeYetkiSablonlari::POS_OLUSTUR, 'yazma' => true],
            ['kod' => MuhasebeYetkiSablonlari::POS_GUNCELLE, 'yazma' => true],
            ['kod' => MuhasebeYetkiSablonlari::POS_SIL, 'yazma' => true],
        ]);
    }

    public function view(User $kullanici, PosHesabi $posHesabi): bool
    {
        $gorur = $this->yetkiKontrol(
            $kullanici,
            MuhasebeYetkiSablonlari::POS_GORUNTULE,
            'muhasebe',
            false
        ) || $this->yetkiKontrol(
            $kullanici,
            MuhasebeYetkiSablonlari::POS_GUNCELLE,
            'muhasebe',
            true
        );

        if (! $gorur) {
            return false;
        }

        return $this->kayitAktifFirmayaAitMi($kullanici, $posHesabi);
    }

    public function create(User $kullanici): bool
    {
        return $this->yetkiKontrol(
            $kullanici,
            MuhasebeYetkiSablonlari::POS_OLUSTUR,
            'muhasebe',
            true
        );
    }

    public function update(User $kullanici, PosHesabi $posHesabi): bool
    {
        if (! $this->yetkiKontrol(
            $kullanici,
            MuhasebeYetkiSablonlari::POS_GUNCELLE,
            'muhasebe',
            true
        )) {
            return false;
        }

        return $this->kayitAktifFirmayaAitMi($kullanici, $posHesabi);
    }

    public function delete(User $kullanici, PosHesabi $posHesabi): bool
    {
        if (! $this->yetkiKontrol(
            $kullanici,
            MuhasebeYetkiSablonlari::POS_SIL,
            'muhasebe',
            true
        )) {
            return false;
        }

        return $this->kayitAktifFirmayaAitMi($kullanici, $posHesabi);
    }
}
