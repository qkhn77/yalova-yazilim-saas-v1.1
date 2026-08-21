<?php

namespace App\Services;

use App\Models\FirmaAyari;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * firma_ayarlari anahtar/değer (deger JSON) okuma-yazma.
 */
class FirmaAyarDeposu
{
    /** @var array<int, string> */
    private const HASSAS_ANAHTARLAR = [
        'paytr_merchant_key',
        'paytr_merchant_salt',
        'iyzico_api_key',
        'iyzico_secret_key',
        'telegram_bot_token',
        'teknik_servis_telegram_bot_token',
        'barkodlu_satis_telegram_bot_token',
        'nette_fatura_kullanici_adi',
        'nette_fatura_sifre',
    ];

    private const SIFRELI_ON_EK = 'enc:v1:';
    private const CACHE_TTL_SECONDS = 300;

    /**
     * @var array<int, array<string, array<string, mixed>|null>>
     */
    private array $firmaAyarlari = [];

    public function oku(int $firmaId, string $anahtar, mixed $varsayilan = null): mixed
    {
        $kayit = $this->firmaAyari($firmaId, $anahtar);

        if (! is_array($kayit)) {
            return $varsayilan;
        }

        $deger = $kayit['deger'] ?? $varsayilan;
        if (! in_array($anahtar, self::HASSAS_ANAHTARLAR, true)) {
            return $deger;
        }

        return $this->hassasDegerCoz($deger, $varsayilan);
    }

    public function yaz(int $firmaId, string $anahtar, mixed $deger): void
    {
        if (! Schema::hasTable('firma_ayarlari')) {
            return;
        }

        $yazilacakDeger = $deger;
        $meta = null;
        if (in_array($anahtar, self::HASSAS_ANAHTARLAR, true)) {
            $yazilacakDeger = $this->hassasDegerSifrele($deger);
            $meta = [
                'secret_rotated_at' => now()->toIso8601String(),
                'secret_schema' => 'v1',
            ];
        }

        $kayit = array_filter([
            'deger' => $yazilacakDeger,
            'meta' => $meta,
        ], static fn (mixed $v): bool => $v !== null);

        FirmaAyari::query()->updateOrCreate(
            [
                'firma_id' => $firmaId,
                'anahtar' => $anahtar,
            ],
            [
                'deger' => $kayit,
            ]
        );

        Cache::forget($this->cacheAnahtari($firmaId));

        if (array_key_exists($firmaId, $this->firmaAyarlari)) {
            $this->firmaAyarlari[$firmaId][$anahtar] = $kayit;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function firmaAyari(int $firmaId, string $anahtar): ?array
    {
        if (! Schema::hasTable('firma_ayarlari')) {
            return null;
        }

        if (! array_key_exists($firmaId, $this->firmaAyarlari)) {
            $this->firmaAyarlari[$firmaId] = Cache::remember(
                $this->cacheAnahtari($firmaId),
                self::CACHE_TTL_SECONDS,
                function () use ($firmaId): array {
                    $ayarlar = [];

                    FirmaAyari::query()
                        ->withoutGlobalScopes()
                        ->where('firma_id', $firmaId)
                        ->get(['anahtar', 'deger'])
                        ->each(function (FirmaAyari $ayar) use (&$ayarlar): void {
                            $anahtar = (string) ($ayar->anahtar ?? '');
                            if ($anahtar === '' || array_key_exists($anahtar, $ayarlar)) {
                                return;
                            }

                            $ayarlar[$anahtar] = $this->ayarDegeriniDiziyeCevir($ayar->deger);
                        });

                    return $ayarlar;
                },
            );
        }

        return $this->firmaAyarlari[$firmaId][$anahtar] ?? null;
    }

    private function cacheAnahtari(int $firmaId): string
    {
        return 'firma-ayarlari:v1:'.$firmaId;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function ayarDegeriniDiziyeCevir(mixed $deger): ?array
    {
        if (is_array($deger)) {
            return $deger;
        }

        if (! is_string($deger) || $deger === '') {
            return null;
        }

        $cozulmus = json_decode($deger, true);

        return is_array($cozulmus) ? $cozulmus : null;
    }

    private function hassasDegerSifrele(mixed $deger): ?string
    {
        if ($deger === null || $deger === '') {
            return null;
        }

        return self::SIFRELI_ON_EK.Crypt::encryptString((string) $deger);
    }

    private function hassasDegerCoz(mixed $deger, mixed $varsayilan = null): mixed
    {
        if (! is_string($deger) || $deger === '') {
            return $varsayilan;
        }

        if (! str_starts_with($deger, self::SIFRELI_ON_EK)) {
            // Legacy plaintext değerlerle geriye dönük uyumluluk.
            return $deger;
        }

        try {
            return Crypt::decryptString(substr($deger, strlen(self::SIFRELI_ON_EK)));
        } catch (DecryptException) {
            return $varsayilan;
        }
    }
}
