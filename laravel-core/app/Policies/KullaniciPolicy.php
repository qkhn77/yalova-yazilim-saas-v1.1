<?php

namespace App\Policies;

use App\Models\FirmaKullanici;
use App\Models\User;

class KullaniciPolicy extends BasePolicy
{
    protected string $modulKodu = 'kullanici';

    public function viewAny(User $kullanici): bool
    {
        return $this->yetkiKontrol($kullanici, 'kullanici.goruntule', $this->modulKodu, false);
    }

    public function view(User $kullanici, User $hedefKullanici): bool
    {
        if (! $this->yetkiKontrol($kullanici, 'kullanici.goruntule', $this->modulKodu, false)) {
            return false;
        }

        if ($this->superAdminMi($kullanici)) {
            return true;
        }

        $firmaId = $this->aktifFirmaId();
        if (! $firmaId) {
            return false;
        }

        return FirmaKullanici::query()
            ->withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->where('kullanici_id', $hedefKullanici->id)
            ->where('durum', 'aktif')
            ->whereNull('deleted_at')
            ->exists();
    }

    public function create(User $kullanici): bool
    {
        return $this->yetkiKontrol($kullanici, 'kullanici.olustur', $this->modulKodu, true);
    }

    public function update(User $kullanici, User $hedefKullanici): bool
    {
        if (! $this->yetkiKontrol($kullanici, 'kullanici.guncelle', $this->modulKodu, true)) {
            return false;
        }

        if ($this->superAdminMi($kullanici)) {
            return true;
        }

        $firmaId = $this->aktifFirmaId();
        if (! $firmaId) {
            return false;
        }

        return FirmaKullanici::query()
            ->withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->where('kullanici_id', $hedefKullanici->id)
            ->whereNull('deleted_at')
            ->exists();
    }

    public function delete(User $kullanici, User $hedefKullanici): bool
    {
        if ((int) $kullanici->id === (int) $hedefKullanici->id) {
            return false;
        }

        return $this->update($kullanici, $hedefKullanici)
            && $this->yetkiKontrol($kullanici, 'kullanici.sil', $this->modulKodu, true);
    }
}

