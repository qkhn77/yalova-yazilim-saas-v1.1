<?php

namespace App\Policies;

use App\Models\Muhasebe\Teklif;
use App\Models\User;
use App\Support\TeklifYetkiSablonlari;

class TeklifPolicy extends BasePolicy
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

    public function view(User $kullanici, Teklif $teklif): bool
    {
        $gorur = $this->yetkiKontrol($kullanici, TeklifYetkiSablonlari::GORUNTULE, 'teklif_yonetimi', false)
            || $this->yetkiKontrol($kullanici, TeklifYetkiSablonlari::GUNCELLE, 'teklif_yonetimi', true);

        return $gorur && $this->kayitAktifFirmayaAitMi($kullanici, $teklif);
    }

    public function create(User $kullanici): bool
    {
        return $this->yetkiKontrol($kullanici, TeklifYetkiSablonlari::OLUSTUR, 'teklif_yonetimi', true);
    }

    public function update(User $kullanici, Teklif $teklif): bool
    {
        return $this->yetkiKontrol($kullanici, TeklifYetkiSablonlari::GUNCELLE, 'teklif_yonetimi', true)
            && $this->kayitAktifFirmayaAitMi($kullanici, $teklif);
    }

    public function delete(User $kullanici, Teklif $teklif): bool
    {
        if ((int) ($teklif->faturaya_donustu_fatura_id ?? 0) > 0) {
            return false;
        }

        return $this->yetkiKontrol($kullanici, TeklifYetkiSablonlari::SIL, 'teklif_yonetimi', true)
            && $this->kayitAktifFirmayaAitMi($kullanici, $teklif);
    }

    public function deleteAny(User $kullanici): bool
    {
        return $this->yetkiKontrol($kullanici, TeklifYetkiSablonlari::SIL, 'teklif_yonetimi', true);
    }
}
