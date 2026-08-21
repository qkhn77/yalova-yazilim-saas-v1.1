<?php

namespace App\Services;

use App\Models\Firma;
use Closure;

class TenantContextService
{
    public const SESSION_AKTIF_FIRMA_ID = 'aktif_firma_id';
    public const SESSION_AKTIF_FIRMA_KODU = 'aktif_firma_kodu';
    public const SESSION_AKTIF_ROL_ID = 'aktif_rol_id';
    public const SESSION_AKTIF_KULLANICI_FIRMA_ID = 'aktif_kullanici_firma_id';

    private bool $aktifFirmaYuklendi = false;

    private ?int $aktifFirmaCacheId = null;

    private ?Firma $aktifFirmaCache = null;

    private int $sistemFirmaKapsamiDerinligi = 0;

    /**
     * Oturumsuz firma işlemlerinde tenant scope'larının yalnızca kontrollü
     * işlem süresince devre dışı kalmasını sağlar.
     */
    public function sistemFirmaKapsaminda(Closure $islem): mixed
    {
        $this->sistemFirmaKapsamiDerinligi++;
        try {
            return $islem();
        } finally {
            $this->sistemFirmaKapsamiDerinligi--;
        }
    }

    public function sistemFirmaKapsamiAktifMi(): bool
    {
        return $this->sistemFirmaKapsamiDerinligi > 0;
    }

    public function aktifFirmaId(): ?int
    {
        $id = session(self::SESSION_AKTIF_FIRMA_ID);
        return $id ? (int) $id : null;
    }

    public function aktifFirmaKodu(): ?string
    {
        $kod = session(self::SESSION_AKTIF_FIRMA_KODU);
        return is_string($kod) && $kod !== '' ? $kod : null;
    }

    public function aktifRolId(): ?int
    {
        $id = session(self::SESSION_AKTIF_ROL_ID);
        return $id ? (int) $id : null;
    }

    public function aktifKullaniciFirmaId(): ?int
    {
        $id = session(self::SESSION_AKTIF_KULLANICI_FIRMA_ID);
        return $id ? (int) $id : null;
    }

    public function aktifFirma(): ?Firma
    {
        $firmaId = $this->aktifFirmaId();
        if (! $firmaId) {
            $this->aktifFirmaYuklendi = false;
            $this->aktifFirmaCacheId = null;
            $this->aktifFirmaCache = null;

            return null;
        }

        if ($this->aktifFirmaYuklendi && $this->aktifFirmaCacheId === $firmaId) {
            return $this->aktifFirmaCache;
        }

        $this->aktifFirmaYuklendi = true;
        $this->aktifFirmaCacheId = $firmaId;
        $this->aktifFirmaCache = Firma::query()->find($firmaId);

        return $this->aktifFirmaCache;
    }

    public function firmaAyarla(Firma $firma, ?int $rolId = null, ?int $kullaniciFirmaId = null): void
    {
        session([
            self::SESSION_AKTIF_FIRMA_ID => $firma->id,
            self::SESSION_AKTIF_FIRMA_KODU => $firma->firma_kodu,
            self::SESSION_AKTIF_ROL_ID => $rolId,
            self::SESSION_AKTIF_KULLANICI_FIRMA_ID => $kullaniciFirmaId,
        ]);

        $this->aktifFirmaYuklendi = true;
        $this->aktifFirmaCacheId = (int) $firma->id;
        $this->aktifFirmaCache = $firma;
    }

    public function hasAktifFirma(): bool
    {
        return $this->aktifFirmaId() !== null;
    }

    public function check(): bool
    {
        return $this->hasAktifFirma();
    }

    public function temizle(): void
    {
        session()->forget([
            self::SESSION_AKTIF_FIRMA_ID,
            self::SESSION_AKTIF_FIRMA_KODU,
            self::SESSION_AKTIF_ROL_ID,
            self::SESSION_AKTIF_KULLANICI_FIRMA_ID,
        ]);

        $this->aktifFirmaYuklendi = false;
        $this->aktifFirmaCacheId = null;
        $this->aktifFirmaCache = null;
    }
}

