<?php

namespace App\Policies;

use App\Models\FirmaKullanici;
use App\Models\User;

/**
 * Kiracı firma içi kullanıcı (firma_kullanicilari) kayıtları.
 * Süper admin tüm firmaları görür; kiracı yalnızca aktif firmayı.
 */
class FirmaIciKullaniciPolitikasi extends BasePolicy
{
    protected string $modulKodu = 'kullanici';

    protected string $yonetimYetkiKodu = 'firma_yetki_yonetimi.yonet';

    public function viewAny(User $kullanici): bool
    {
        return $this->yetkiKontrol($kullanici, $this->yonetimYetkiKodu, $this->modulKodu, true);
    }

    public function view(User $kullanici, FirmaKullanici $kayit): bool
    {
        if (! $this->yetkiKontrol($kullanici, $this->yonetimYetkiKodu, $this->modulKodu, true)) {
            return false;
        }

        return $this->kayitFirmasiErisilebilirMi($kullanici, $kayit);
    }

    public function create(User $kullanici): bool
    {
        return $this->yetkiKontrol($kullanici, $this->yonetimYetkiKodu, $this->modulKodu, true);
    }

    public function update(User $kullanici, FirmaKullanici $kayit): bool
    {
        if (! $this->yetkiKontrol($kullanici, $this->yonetimYetkiKodu, $this->modulKodu, true)) {
            return false;
        }

        return $this->kayitFirmasiErisilebilirMi($kullanici, $kayit);
    }

    public function delete(User $kullanici, FirmaKullanici $kayit): bool
    {
        if ((int) $kullanici->id === (int) $kayit->kullanici_id) {
            return false;
        }

        if (! $this->yetkiKontrol($kullanici, $this->yonetimYetkiKodu, $this->modulKodu, true)) {
            return false;
        }

        return $this->kayitFirmasiErisilebilirMi($kullanici, $kayit);
    }

    public function restore(User $kullanici, FirmaKullanici $kayit): bool
    {
        return $this->update($kullanici, $kayit);
    }

    protected function kayitFirmasiErisilebilirMi(User $kullanici, FirmaKullanici $kayit): bool
    {
        if ($this->superAdminMi($kullanici)) {
            return true;
        }

        $fid = $this->aktifFirmaId();

        return $fid !== null && $fid === (int) $kayit->firma_id;
    }
}
