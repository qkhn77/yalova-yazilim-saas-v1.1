<?php

namespace App\Policies;

use App\Models\Muhasebe\StokHareketi;
use App\Models\User;
use App\Support\MuhasebeYetkiSablonlari;

class StokHareketiPolicy extends BasePolicy
{
    public function viewAny(User $kullanici): bool
    {
        return $this->herhangiBirMuhasebeYetkisi($kullanici, [
            ['kod' => MuhasebeYetkiSablonlari::STOK_GORUNTULE, 'yazma' => false],
            ['kod' => MuhasebeYetkiSablonlari::STOK_GUNCELLE, 'yazma' => true],
            ['kod' => MuhasebeYetkiSablonlari::STOK_SIL, 'yazma' => true],
        ]);
    }

    public function view(User $kullanici, StokHareketi $hareket): bool
    {
        return $this->viewAny($kullanici)
            && $this->kayitAktifFirmayaAitMi($kullanici, $hareket);
    }
}
