<?php

namespace App\Policies;

use App\Models\Firma;
use App\Models\User;

class FirmaPolicy extends BasePolicy
{
    protected string $modulKodu = 'firma';

    /**
     * Tüm firmaların listesi yalnızca süper admin (SaaS yönetim ekranı).
     */
    public function viewAny(User $kullanici): bool
    {
        return $this->superAdminMi($kullanici);
    }

    public function view(User $kullanici, Firma $firma): bool
    {
        if (! $this->tenantGecerliMi($kullanici)) {
            return false;
        }

        if (! $this->superAdminMi($kullanici) && $this->aktifFirmaId() !== (int) $firma->id) {
            return false;
        }

        return $this->yetkiKontrol($kullanici, 'firma.goruntule', $this->modulKodu, false);
    }

    public function create(User $kullanici): bool
    {
        return $this->superAdminMi($kullanici);
    }

    public function update(User $kullanici, Firma $firma): bool
    {
        if (! $this->superAdminMi($kullanici) && $this->aktifFirmaId() !== (int) $firma->id) {
            return false;
        }

        return $this->yetkiKontrol($kullanici, 'firma.guncelle', $this->modulKodu, true);
    }

    public function delete(User $kullanici, Firma $firma): bool
    {
        if (! $this->superAdminMi($kullanici) && $this->aktifFirmaId() !== (int) $firma->id) {
            return false;
        }

        return $this->yetkiKontrol($kullanici, 'firma.sil', $this->modulKodu, true);
    }
}

