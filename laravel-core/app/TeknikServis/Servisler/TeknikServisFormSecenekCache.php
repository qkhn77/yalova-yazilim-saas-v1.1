<?php

namespace App\TeknikServis\Servisler;

use App\Models\Muhasebe\Birim;
use App\Models\Muhasebe\VergiOrani;
use App\Models\TeknikServis\TeknikServisAksesuarTanimi;
use App\Models\TeknikServis\TeknikServisArizaTanimi;
use App\Models\TeknikServis\TeknikServisCihazTanimi;
use App\Models\TeknikServis\TeknikServisDurumTanimi;
use App\Models\TeknikServis\TeknikServisMesajSablonu;
use App\Models\TeknikServis\TeknikServisMarkaTanimi;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

final class TeknikServisFormSecenekCache
{
    public const GROUP_SERVIS_DURUMU = 'servis_durumu';
    public const GROUP_CIHAZ = 'cihaz';
    public const GROUP_MARKA = 'marka';
    public const GROUP_AKSESUAR = 'aksesuar';
    public const GROUP_ARIZA = 'ariza';
    public const GROUP_BIRIM = 'birim';
    public const GROUP_VERGI_ORANI = 'vergi_orani';
    public const GROUP_MESAJ_SABLONU = 'mesaj_sablonu';
    public const GROUP_TANIM_SECENEKLERI = 'tanim_secenekleri';

    private const CACHE_PREFIX = 'teknik_servis:form_secenekleri';
    private const CACHE_TTL_SECONDS = 43200;

    /** @var array<string, mixed> */
    private static array $istekCache = [];

    /**
     * @template T
     *
     * @param  Closure(): T  $yukle
     * @return T
     */
    public function remember(string $grup, string $anahtar, Closure $yukle): mixed
    {
        $versiyon = $this->versiyon($grup);
        $istekAnahtari = $grup.'|'.$versiyon.'|'.$anahtar;

        if (array_key_exists($istekAnahtari, self::$istekCache)) {
            return self::$istekCache[$istekAnahtari];
        }

        return self::$istekCache[$istekAnahtari] = Cache::remember(
            $this->cacheAnahtari($grup, $anahtar, $versiyon),
            self::CACHE_TTL_SECONDS,
            $yukle
        );
    }

    public function modelIcinTemizle(Model|string $model): void
    {
        $sinif = is_string($model) ? $model : $model::class;

        foreach ($this->modelGruplari($sinif) as $grup) {
            $this->grubuTemizle($grup);
        }
    }

    public function grubuTemizle(string $grup): void
    {
        Cache::forever($this->versiyonAnahtari($grup), $this->versiyon($grup) + 1);

        self::$istekCache = [];
    }

    public function istekCacheTemizle(): void
    {
        self::$istekCache = [];
    }

    /**
     * @return array<int, string>
     */
    private function modelGruplari(string $sinif): array
    {
        return match ($sinif) {
            TeknikServisDurumTanimi::class => [self::GROUP_SERVIS_DURUMU, self::GROUP_TANIM_SECENEKLERI],
            TeknikServisCihazTanimi::class => [self::GROUP_CIHAZ, self::GROUP_TANIM_SECENEKLERI],
            TeknikServisMarkaTanimi::class => [self::GROUP_MARKA, self::GROUP_TANIM_SECENEKLERI],
            TeknikServisAksesuarTanimi::class => [self::GROUP_AKSESUAR, self::GROUP_TANIM_SECENEKLERI],
            TeknikServisArizaTanimi::class => [self::GROUP_ARIZA, self::GROUP_TANIM_SECENEKLERI],
            TeknikServisMesajSablonu::class => [self::GROUP_MESAJ_SABLONU],
            Birim::class => [self::GROUP_BIRIM],
            VergiOrani::class => [self::GROUP_VERGI_ORANI],
            default => [],
        };
    }

    private function versiyon(string $grup): int
    {
        $versiyon = Cache::get($this->versiyonAnahtari($grup), 1);

        return is_numeric($versiyon) ? max(1, (int) $versiyon) : 1;
    }

    private function cacheAnahtari(string $grup, string $anahtar, int $versiyon): string
    {
        return self::CACHE_PREFIX.':'.$grup.':v'.$versiyon.':'.$anahtar;
    }

    private function versiyonAnahtari(string $grup): string
    {
        return self::CACHE_PREFIX.':'.$grup.':versiyon';
    }
}
