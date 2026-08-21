<?php

namespace App\Policies;

use App\Models\TeklifYonetimi\TeklifBaskiSablonu;
use App\Models\User;
use App\Support\TeklifYetkiSablonlari;

class TeklifBaskiSablonuPolicy extends BasePolicy
{
    public function viewAny(User $kullanici): bool
    {
        return $this->herhangiBirMuhasebeYetkisi($kullanici, [
            ['kod' => TeklifYetkiSablonlari::GORUNTULE, 'yazma' => false],
            ['kod' => TeklifYetkiSablonlari::OLUSTUR, 'yazma' => true],
            ['kod' => TeklifYetkiSablonlari::GUNCELLE, 'yazma' => true],
            ['kod' => TeklifYetkiSablonlari::SIL, 'yazma' => true],
        ], 'teklif_yonetimi');
    }

    public function view(User $kullanici, TeklifBaskiSablonu $sablon): bool
    {
        $gorur = $this->yetkiKontrol($kullanici, TeklifYetkiSablonlari::GORUNTULE, 'teklif_yonetimi', false)
            || $this->yetkiKontrol($kullanici, TeklifYetkiSablonlari::GUNCELLE, 'teklif_yonetimi', true);

        return $gorur && $this->kayitAktifFirmayaAitMi($kullanici, $sablon);
    }

    public function create(User $kullanici): bool
    {
        return $this->yetkiKontrol($kullanici, TeklifYetkiSablonlari::OLUSTUR, 'teklif_yonetimi', true);
    }

    public function update(User $kullanici, TeklifBaskiSablonu $sablon): bool
    {
        return $this->yetkiKontrol($kullanici, TeklifYetkiSablonlari::GUNCELLE, 'teklif_yonetimi', true)
            && $this->kayitAktifFirmayaAitMi($kullanici, $sablon);
    }

    public function delete(User $kullanici, TeklifBaskiSablonu $sablon): bool
    {
        if ((bool) ($sablon->varsayilan_mi ?? false)) {
            return false;
        }

        return $this->yetkiKontrol($kullanici, TeklifYetkiSablonlari::SIL, 'teklif_yonetimi', true)
            && $this->kayitAktifFirmayaAitMi($kullanici, $sablon);
    }

    public function deleteAny(User $kullanici): bool
    {
        return $this->yetkiKontrol($kullanici, TeklifYetkiSablonlari::SIL, 'teklif_yonetimi', true);
    }
}
