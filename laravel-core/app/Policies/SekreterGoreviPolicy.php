<?php

namespace App\Policies;

use App\Models\SekreterGorevi;
use App\Models\User;
use App\Support\SekreterYetkiSablonlari;

class SekreterGoreviPolicy extends BasePolicy
{
    public function viewAny(User $user): bool { return $this->yetkiKontrol($user, SekreterYetkiSablonlari::GORUNTULE, 'sekreter'); }
    public function view(User $user, SekreterGorevi $record): bool { return $this->viewAny($user) && $this->kayitAktifFirmayaAitMi($user, $record); }
    public function create(User $user): bool { return $this->yetkiKontrol($user, SekreterYetkiSablonlari::OLUSTUR, 'sekreter', true); }
    public function update(User $user, SekreterGorevi $record): bool { return $this->yetkiKontrol($user, SekreterYetkiSablonlari::GUNCELLE, 'sekreter', true) && $this->kayitAktifFirmayaAitMi($user, $record); }
    public function delete(User $user, SekreterGorevi $record): bool { return $this->yetkiKontrol($user, SekreterYetkiSablonlari::SIL, 'sekreter', true) && $this->kayitAktifFirmayaAitMi($user, $record); }
}
