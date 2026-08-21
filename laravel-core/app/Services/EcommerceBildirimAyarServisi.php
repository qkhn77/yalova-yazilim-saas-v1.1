<?php

namespace App\Services;

use App\Support\EcommerceBildirimTanimlari;

class EcommerceBildirimAyarServisi
{
    public function __construct(
        private readonly FirmaAyarDeposu $depo,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function ayarlariGetir(int $firmaId): array
    {
        $data = [
            'ecommerce_bildirim_admin_eposta' => (string) $this->depo->oku($firmaId, 'ecommerce_bildirim_admin_eposta', ''),
        ];

        foreach (EcommerceBildirimTanimlari::olaylar() as $olay => $_label) {
            foreach (EcommerceBildirimTanimlari::kanallar() as $kanal => $_kanalLabel) {
                $key = $this->anahtar($olay, $kanal);
                $varsayilan = $this->varsayilanKanalAktifMi($olay, $kanal);
                $data[$key] = (bool) $this->depo->oku($firmaId, $key, $varsayilan);
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function kaydetAyarlar(int $firmaId, array $data): void
    {
        if ($firmaId <= 0) {
            return;
        }

        if (array_key_exists('ecommerce_bildirim_admin_eposta', $data)) {
            $adminEposta = trim((string) $data['ecommerce_bildirim_admin_eposta']);
            $this->depo->yaz($firmaId, 'ecommerce_bildirim_admin_eposta', $adminEposta);
        }

        foreach (EcommerceBildirimTanimlari::olaylar() as $olay => $_label) {
            foreach (EcommerceBildirimTanimlari::kanallar() as $kanal => $_kanalLabel) {
                $key = $this->anahtar($olay, $kanal);
                if (array_key_exists($key, $data)) {
                    $this->depo->yaz($firmaId, $key, (bool) $data[$key]);
                }
            }
        }
    }

    public function kanalAktifMi(int $firmaId, string $olay, string $kanal): bool
    {
        $key = $this->anahtar($olay, $kanal);
        $varsayilan = $this->varsayilanKanalAktifMi($olay, $kanal);

        return (bool) $this->depo->oku($firmaId, $key, $varsayilan);
    }

    private function varsayilanKanalAktifMi(string $olay, string $kanal): bool
    {
        $harita = EcommerceBildirimTanimlari::varsayilanKanalHaritasi();
        $kanallar = $harita[$olay] ?? [];

        return in_array($kanal, $kanallar, true);
    }

    private function anahtar(string $olay, string $kanal): string
    {
        return 'ecommerce_bildirim_'.$olay.'_'.$kanal;
    }
}
