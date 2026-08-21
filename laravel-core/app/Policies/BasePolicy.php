<?php

namespace App\Policies;

use App\Models\User;
use App\Services\ModulErisimService;
use App\Services\TenantContextService;
use App\Services\YetkiService;
use App\Support\KullaniciRolYardimcisi;
use Illuminate\Database\Eloquent\Model;

class BasePolicy
{
    public function __construct(
        protected TenantContextService $tenantContextService,
        protected YetkiService $yetkiService,
        protected ModulErisimService $modulErisimService
    ) {}

    protected function superAdminMi(User $kullanici): bool
    {
        return KullaniciRolYardimcisi::superAdminVeyaIsAdmin($kullanici);
    }

    /**
     * Kiracı: kayıt aktif firmaya ait olmalı. Süper yönetici: serbest.
     */
    protected function kayitAktifFirmayaAitMi(User $kullanici, Model $kayit): bool
    {
        if ($this->superAdminMi($kullanici)) {
            return true;
        }

        $aktif = $this->aktifFirmaId();
        if ($aktif === null || ! isset($kayit->firma_id)) {
            return false;
        }

        return (int) $kayit->firma_id === (int) $aktif;
    }

    /**
     * @param  array<int, array{kod: string|null, yazma: bool}>  $yetkiPaketleri
     */
    protected function herhangiBirMuhasebeYetkisi(User $kullanici, array $yetkiPaketleri, string $modulKodu = 'muhasebe'): bool
    {
        foreach ($yetkiPaketleri as $paket) {
            if ($this->yetkiKontrol($kullanici, $paket['kod'], $modulKodu, $paket['yazma'])) {
                return true;
            }
        }

        return false;
    }

    protected function aktifFirmaId(): ?int
    {
        return $this->tenantContextService->aktifFirmaId();
    }

    protected function tenantGecerliMi(User $kullanici): bool
    {
        if ($this->superAdminMi($kullanici)) {
            return true;
        }

        $firmaId = $this->aktifFirmaId();

        return $firmaId !== null;
    }

    protected function modulYazmaIzinliMi(int $firmaId, string $modulKodu, ?User $kullanici = null): bool
    {
        if (! $this->modulErisimService->modulErisilebilirMi($firmaId, $modulKodu)) {
            return false;
        }

        if ($kullanici instanceof User && $this->yetkiService->firmaUstYonetimRoluMuKullaniciIcin($kullanici, $firmaId)) {
            return true;
        }

        return ! $this->modulErisimService->modulSaltOkunurMu($firmaId, $modulKodu);
    }

    protected function yetkiKontrol(User $kullanici, ?string $yetkiKodu, ?string $modulKodu = null, bool $yazma = false): bool
    {
        if ($this->superAdminMi($kullanici)) {
            return true;
        }

        $firmaId = $this->aktifFirmaId();
        if (! $firmaId) {
            return false;
        }

        if ($modulKodu !== null) {
            if (! $this->sistemAlaniMi($modulKodu)) {
                if (! $this->modulErisimService->modulErisilebilirMi($firmaId, $modulKodu)) {
                    return false;
                }

                if ($yazma && ! $this->modulYazmaIzinliMi($firmaId, $modulKodu, $kullanici)) {
                    return false;
                }
            }
        }

        if ($yetkiKodu === null) {
            return true;
        }

        return $this->yetkiService->yetkiVarMi($kullanici, $firmaId, $yetkiKodu);
    }

    protected function sistemAlaniMi(string $modulKodu): bool
    {
        return in_array($modulKodu, ['firma', 'kullanici', 'modul'], true);
    }
}
