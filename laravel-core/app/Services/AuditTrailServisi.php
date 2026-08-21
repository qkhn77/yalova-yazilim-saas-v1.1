<?php

namespace App\Services;

use App\Support\DenetimYardimcisi;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class AuditTrailServisi
{
    /**
     * @param  array<string, mixed>|null  $eski
     * @param  array<string, mixed>|null  $yeni
     * @param  array<int, string>  $izlenenAlanlar
     * @param  array<int, string>  $maskelenecekAlanlar
     * @param  array<string, mixed>  $ekBaglam
     */
    public function modelDegisimiKaydet(
        string $olay,
        Model $model,
        ?array $eski,
        ?array $yeni,
        array $izlenenAlanlar,
        array $maskelenecekAlanlar = [],
        array $ekBaglam = []
    ): void {
        $eski = $eski ?? [];
        $yeni = $yeni ?? [];

        $degisenEski = [];
        $degisenYeni = [];
        foreach ($izlenenAlanlar as $alan) {
            $eskiDeger = Arr::get($eski, $alan);
            $yeniDeger = Arr::get($yeni, $alan);
            if ($this->esitMi($eskiDeger, $yeniDeger)) {
                continue;
            }

            $degisenEski[$alan] = $this->guvenliDeger($alan, $eskiDeger, $maskelenecekAlanlar);
            $degisenYeni[$alan] = $this->guvenliDeger($alan, $yeniDeger, $maskelenecekAlanlar);
        }

        if ($degisenEski === [] && $degisenYeni === []) {
            return;
        }

        $firmaId = (int) ($model->getAttribute('firma_id') ?? $ekBaglam['firma_id'] ?? 0);
        DenetimYardimcisi::kaydet(
            olay: $olay,
            konuTipi: $model::class,
            konuId: (int) $model->getKey(),
            firmaId: $firmaId > 0 ? $firmaId : null,
            eskiVeri: [
                'degisiklikler' => $degisenEski,
                'baglam' => $this->guvenliDizi($ekBaglam, $maskelenecekAlanlar),
            ],
            yeniVeri: [
                'degisiklikler' => $degisenYeni,
                'baglam' => $this->guvenliDizi($ekBaglam, $maskelenecekAlanlar),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $veri
     * @param  array<int, string>  $maskelenecekAlanlar
     * @return array<string, mixed>
     */
    public function guvenliDizi(array $veri, array $maskelenecekAlanlar = []): array
    {
        $sonuc = [];
        foreach ($veri as $alan => $deger) {
            $sonuc[$alan] = $this->guvenliDeger((string) $alan, $deger, $maskelenecekAlanlar);
        }

        return $sonuc;
    }

    /**
     * @param  array<int, string>  $maskelenecekAlanlar
     */
    public function guvenliDeger(string $alan, mixed $deger, array $maskelenecekAlanlar = []): mixed
    {
        if (in_array($alan, $maskelenecekAlanlar, true) || $this->hassasAlanMi($alan)) {
            if ($deger === null || $deger === '') {
                return null;
            }

            return '[MASKED]';
        }

        if (is_array($deger)) {
            return $this->guvenliDizi($deger, $maskelenecekAlanlar);
        }
        if ($deger instanceof BackedEnum) {
            return $deger->value;
        }

        return $deger;
    }

    private function hassasAlanMi(string $alan): bool
    {
        $alan = mb_strtolower($alan);

        return str_contains($alan, 'sifre')
            || str_contains($alan, 'password')
            || str_contains($alan, 'secret')
            || str_contains($alan, 'token')
            || str_contains($alan, 'api_key')
            || str_contains($alan, 'merchant_key')
            || str_contains($alan, 'merchant_salt');
    }

    private function esitMi(mixed $a, mixed $b): bool
    {
        if ($a instanceof BackedEnum) {
            $a = $a->value;
        }
        if ($b instanceof BackedEnum) {
            $b = $b->value;
        }
        if (is_bool($a) || is_bool($b)) {
            return (bool) $a === (bool) $b;
        }

        return (string) ($a ?? '') === (string) ($b ?? '');
    }
}
