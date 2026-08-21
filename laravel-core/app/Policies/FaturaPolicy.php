<?php

namespace App\Policies;

use App\Models\Muhasebe\Fatura;
use App\Models\User;
use App\Support\MuhasebeYetkiSablonlari;

class FaturaPolicy extends BasePolicy
{
    public function viewAny(User $kullanici): bool
    {
        return $this->herhangiBirMuhasebeYetkisi($kullanici, [
            ['kod' => MuhasebeYetkiSablonlari::FATURA_GORUNTULE, 'yazma' => false],
            ['kod' => MuhasebeYetkiSablonlari::FATURA_OLUSTUR, 'yazma' => true],
            ['kod' => MuhasebeYetkiSablonlari::FATURA_GUNCELLE, 'yazma' => true],
            ['kod' => MuhasebeYetkiSablonlari::FATURA_SIL, 'yazma' => true],
            ['kod' => MuhasebeYetkiSablonlari::FATURA_ONAY, 'yazma' => true],
        ]);
    }

    public function view(User $kullanici, Fatura $fatura): bool
    {
        $gorur = $this->yetkiKontrol($kullanici, MuhasebeYetkiSablonlari::FATURA_GORUNTULE, 'muhasebe', false)
            || $this->yetkiKontrol($kullanici, MuhasebeYetkiSablonlari::FATURA_GUNCELLE, 'muhasebe', true)
            || $this->yetkiKontrol($kullanici, MuhasebeYetkiSablonlari::FATURA_ONAY, 'muhasebe', true);

        return $gorur && $this->kayitAktifFirmayaAitMi($kullanici, $fatura);
    }

    public function create(User $kullanici): bool
    {
        return $this->yetkiKontrol($kullanici, MuhasebeYetkiSablonlari::FATURA_OLUSTUR, 'muhasebe', true);
    }

    public function update(User $kullanici, Fatura $fatura): bool
    {
        return $this->yetkiKontrol($kullanici, MuhasebeYetkiSablonlari::FATURA_GUNCELLE, 'muhasebe', true)
            && $this->kayitAktifFirmayaAitMi($kullanici, $fatura);
    }

    public function delete(User $kullanici, Fatura $fatura): bool
    {
        return $this->yetkiKontrol($kullanici, MuhasebeYetkiSablonlari::FATURA_SIL, 'muhasebe', true)
            && $this->kayitAktifFirmayaAitMi($kullanici, $fatura);
    }

    public function approve(User $kullanici, Fatura $fatura): bool
    {
        return $this->yetkiKontrol($kullanici, MuhasebeYetkiSablonlari::FATURA_ONAY, 'muhasebe', true)
            && $this->kayitAktifFirmayaAitMi($kullanici, $fatura);
    }
}
