<?php

namespace App\Policies;

use App\Models\Modul;
use App\Models\User;

class ModulPolicy extends BasePolicy
{
    protected string $modulKodu = 'modul';

    public function viewAny(User $kullanici): bool
    {
        return $this->yetkiKontrol($kullanici, 'modul.goruntule', $this->modulKodu, false);
    }

    public function view(User $kullanici, Modul $modul): bool
    {
        return $this->yetkiKontrol($kullanici, 'modul.goruntule', $this->modulKodu, false);
    }

    public function create(User $kullanici): bool
    {
        return $this->yetkiKontrol($kullanici, 'modul.olustur', $this->modulKodu, true);
    }

    public function update(User $kullanici, Modul $modul): bool
    {
        return $this->yetkiKontrol($kullanici, 'modul.guncelle', $this->modulKodu, true);
    }

    public function delete(User $kullanici, Modul $modul): bool
    {
        return $this->yetkiKontrol($kullanici, 'modul.sil', $this->modulKodu, true);
    }
}

