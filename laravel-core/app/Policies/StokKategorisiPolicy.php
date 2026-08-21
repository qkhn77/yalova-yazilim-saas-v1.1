<?php

namespace App\Policies;

use App\Models\Muhasebe\StokKategorisi;
use App\Models\User;
use App\Support\MuhasebeYetkiSablonlari;

class StokKategorisiPolicy extends BasePolicy
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

    public function view(User $kullanici, StokKategorisi $kategori): bool
    {
        $gorur = $this->yetkiKontrol($kullanici, MuhasebeYetkiSablonlari::STOK_GORUNTULE, 'muhasebe', false)
            || $this->yetkiKontrol($kullanici, MuhasebeYetkiSablonlari::STOK_GUNCELLE, 'muhasebe', true);

        return $gorur && $this->kayitAktifFirmayaAitMi($kullanici, $kategori);
    }

    public function create(User $kullanici): bool
    {
        return $this->yetkiKontrol($kullanici, MuhasebeYetkiSablonlari::STOK_OLUSTUR, 'muhasebe', true);
    }

    public function update(User $kullanici, StokKategorisi $kategori): bool
    {
        return $this->yetkiKontrol($kullanici, MuhasebeYetkiSablonlari::STOK_GUNCELLE, 'muhasebe', true)
            && $this->kayitAktifFirmayaAitMi($kullanici, $kategori);
    }

    public function delete(User $kullanici, StokKategorisi $kategori): bool
    {
        return $this->yetkiKontrol($kullanici, MuhasebeYetkiSablonlari::STOK_SIL, 'muhasebe', true)
            && $this->kayitAktifFirmayaAitMi($kullanici, $kategori);
    }
}
