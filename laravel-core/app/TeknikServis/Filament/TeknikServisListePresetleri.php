<?php

namespace App\TeknikServis\Filament;

use App\Models\TeknikServis\TeknikServisDurumTanimi;
use App\Services\TenantContextService;
use App\TeknikServis\Enumlar\ServisTipi;
use App\TeknikServis\Servisler\TeknikServisFormSecenekCache;
use Illuminate\Database\Eloquent\Builder;

/**
 * Teknik servis kayıt listeleri için merkezi sorgu ön ayarları.
 */
final class TeknikServisListePresetleri
{
    /**
     * @var array<string, array<int, int>>
     */
    private static array $durumIdCache = [];

    public static function uygula(Builder $sorgu, TeknikServisListePreset $preset): Builder
    {
        $servisTipleri = self::servisTipleriPresetIcin($preset);
        if ($servisTipleri !== null) {
            $sorgu = count($servisTipleri) === 1
                ? $sorgu->where('servis_tipi', $servisTipleri[0])
                : $sorgu->whereIn('servis_tipi', $servisTipleri);
        }

        $durumIdleri = self::durumIdleriPresetIcin($preset);
        if ($durumIdleri === null) {
            return $sorgu;
        }

        return self::durumIdleriniUygula($sorgu, $durumIdleri);
    }

    /**
     * @return array<int, int>|null
     */
    public static function durumIdleriPresetIcin(TeknikServisListePreset $preset): ?array
    {
        return match ($preset) {
            TeknikServisListePreset::Tum => null,

            TeknikServisListePreset::Yeni => self::durumKodIdleri([
                TeknikServisDurumKodlari::YENI,
                TeknikServisDurumKodlari::YENI_ESKI,
            ], ['Yeni Kayıt', 'Yeni']),

            TeknikServisListePreset::Acik => self::durumKodIdleri([
                TeknikServisDurumKodlari::YENI,
                TeknikServisDurumKodlari::YENI_ESKI,
                TeknikServisDurumKodlari::TEZGAHTA,
                TeknikServisDurumKodlari::FIYAT_VERILDI,
                TeknikServisDurumKodlari::PARCA_BEKLEYEN,
                TeknikServisDurumKodlari::PARCA_BEKLIYOR,
                TeknikServisDurumKodlari::GARANTIYE_GONDERILDI,
                TeknikServisDurumKodlari::TESLIM_BEKLEYEN,
                TeknikServisDurumKodlari::TESLIM_BEKLIYOR,
            ], [
                'Yeni Kayıt',
                'Yeni',
                'Tezgahta',
                'Fiyat Verilen',
                'Parça Bekleyen',
                'Parça Bekliyor',
                'Garantiye Gönderilen',
                'Teslim Bekleyen',
                'Teslim Bekliyor',
            ]),

            TeknikServisListePreset::Tezgahta => self::durumKodIdleri([TeknikServisDurumKodlari::TEZGAHTA], ['Tezgahta']),

            TeknikServisListePreset::ParcaBekleyen => self::durumKodIdleri([
                TeknikServisDurumKodlari::PARCA_BEKLEYEN,
                TeknikServisDurumKodlari::PARCA_BEKLIYOR,
            ], ['Parça Bekleyen', 'Parça Bekliyor']),

            TeknikServisListePreset::GarantiyeGonderilen => self::durumKodIdleri([TeknikServisDurumKodlari::GARANTIYE_GONDERILDI], ['Garantiye Gönderilen']),

            TeknikServisListePreset::FiyatVerilen => self::durumBayrakVeveyaKodIdleri(
                'fiyat-verildi',
                'is_fiyat_verildi',
                [TeknikServisDurumKodlari::FIYAT_VERILDI]
            ),

            TeknikServisListePreset::TeslimBekleyen => self::durumKodIdleri([
                TeknikServisDurumKodlari::TESLIM_BEKLEYEN,
                TeknikServisDurumKodlari::TESLIM_BEKLIYOR,
            ], ['Teslim Bekleyen', 'Teslim Bekliyor']),

            TeknikServisListePreset::TamamlananDisServis => self::tamamlananDisServisDurumIdleri(),

            TeknikServisListePreset::TeslimEdilen => self::durumBayrakVeveyaKodIdleri(
                'teslim-edildi',
                'is_teslim_edildi',
                [TeknikServisDurumKodlari::TESLIM_EDILDI]
            ),

            TeknikServisListePreset::Iptal => self::durumBayrakVeveyaKodIdleri(
                'iptal',
                'is_iptal',
                [TeknikServisDurumKodlari::IPTAL]
            ),

            TeknikServisListePreset::Iade => self::durumBayrakVeveyaKodIdleri(
                'iade',
                'is_iade',
                [TeknikServisDurumKodlari::IADE]
            ),
        };
    }

    /**
     * @return array<int, string>|null
     */
    public static function servisTipleriPresetIcin(TeknikServisListePreset $preset): ?array
    {
        return match ($preset) {
            TeknikServisListePreset::TamamlananDisServis => [ServisTipi::DisServis->value],
            TeknikServisListePreset::TeslimEdilen => [ServisTipi::ArizaliCihaz->value, ServisTipi::Bakim->value],
            default => null,
        };
    }

    /**
     * @param  array<int, int>  $durumIdleri
     */
    private static function durumIdleriniUygula(Builder $sorgu, array $durumIdleri): Builder
    {
        if ($durumIdleri === []) {
            return $sorgu->whereRaw('1 = 0');
        }

        return $sorgu->whereIn('servis_durumu_id', $durumIdleri);
    }

    /**
     * @param  array<int, string>  $kodlar
     * @param  array<int, string>  $adlar
     * @return array<int, int>
     */
    private static function durumKodIdleri(array $kodlar, array $adlar = []): array
    {
        return self::durumIdleri(self::diziAnahtari('kod-ad', $kodlar, $adlar), static function (array $durum) use ($kodlar, $adlar): bool {
            $kod = (string) $durum['kod'];
            if (in_array($kod, $kodlar, true)) {
                return true;
            }

            if ($adlar === []) {
                return false;
            }

            return in_array((string) $durum['ad'], $adlar, true)
                || in_array($kod, $adlar, true); // gecmisten "kod=Yeni Kayıt" gibi kirik veriler icin
        });
    }

    /**
     * @param  array<int, string>  $kodlar
     * @return array<int, int>
     */
    private static function durumBayrakVeveyaKodIdleri(string $bayrakAnahtari, string $bayrakKolonu, array $kodlar): array
    {
        return self::durumIdleri(self::diziAnahtari('bayrak-'.$bayrakAnahtari, $kodlar), static function (array $durum) use ($bayrakKolonu, $kodlar): bool {
            return (bool) ($durum[$bayrakKolonu] ?? false)
                || in_array((string) $durum['kod'], $kodlar, true);
        });
    }

    /**
     * @return array<int, int>
     */
    private static function tamamlananDisServisDurumIdleri(): array
    {
        $kodlar = [
            TeknikServisDurumKodlari::DIS_SERVIS_TAMAMLANDI,
            TeknikServisDurumKodlari::TESLIM_EDILDI,
        ];
        $adlar = ['Tamamlanan Dış Servis', 'Teslim Edilen'];

        return self::durumIdleri(self::diziAnahtari('tamamlanan-dis-servis', $kodlar, $adlar), static function (array $durum) use ($kodlar, $adlar): bool {
            return (bool) $durum['is_teslim_edildi']
                || in_array((string) $durum['kod'], $kodlar, true)
                || in_array((string) $durum['ad'], $adlar, true);
        });
    }

    /**
     * @param  callable(array{id:int,ad:string,kod:string,is_fiyat_verildi:bool,is_teslim_edildi:bool,is_iptal:bool,is_iade:bool}): bool  $kosul
     * @return array<int, int>
     */
    private static function durumIdleri(string $kosulAnahtari, callable $kosul): array
    {
        $firmaId = app(TenantContextService::class)->aktifFirmaId();
        $kapsamAnahtari = implode('|', [
            app()->bound('request') ? (string) spl_object_id(request()) : 'no-request',
            app()->runningInConsole() ? 'console' : 'web',
            (string) (auth()->id() ?? 'guest'),
            $firmaId !== null ? (string) $firmaId : 'global',
            $kosulAnahtari,
        ]);

        if (array_key_exists($kapsamAnahtari, self::$durumIdCache)) {
            return self::$durumIdCache[$kapsamAnahtari];
        }

        $idler = [];
        foreach (self::durumSatirlari() as $durum) {
            if ($kosul($durum)) {
                $idler[] = (int) $durum['id'];
            }
        }

        return self::$durumIdCache[$kapsamAnahtari] = $idler;
    }

    /**
     * @return array<int, array{id:int,ad:string,kod:string,is_fiyat_verildi:bool,is_teslim_edildi:bool,is_iptal:bool,is_iade:bool}>
     */
    private static function durumSatirlari(): array
    {
        $cacheAnahtari = self::durumSatirlariCacheAnahtari();

        return app(TeknikServisFormSecenekCache::class)->remember(
            TeknikServisFormSecenekCache::GROUP_SERVIS_DURUMU,
            'liste-presetleri|'.$cacheAnahtari,
            static fn (): array => TeknikServisDurumTanimi::query()
                ->select(['id', 'ad', 'kod', 'is_fiyat_verildi', 'is_teslim_edildi', 'is_iptal', 'is_iade'])
                ->toBase()
                ->get()
                ->map(static fn (object $durum): array => [
                    'id' => (int) ($durum->id ?? 0),
                    'ad' => (string) ($durum->ad ?? ''),
                    'kod' => (string) ($durum->kod ?? ''),
                    'is_fiyat_verildi' => (bool) ($durum->is_fiyat_verildi ?? false),
                    'is_teslim_edildi' => (bool) ($durum->is_teslim_edildi ?? false),
                    'is_iptal' => (bool) ($durum->is_iptal ?? false),
                    'is_iade' => (bool) ($durum->is_iade ?? false),
                ])
                ->all()
        );
    }

    private static function durumSatirlariCacheAnahtari(): string
    {
        $firmaId = app(TenantContextService::class)->aktifFirmaId();

        return implode('|', [
            app()->runningInConsole() ? 'console' : 'web',
            app()->runningUnitTests() ? 'unit' : 'app',
            (string) (auth()->id() ?? 'guest'),
            $firmaId !== null ? (string) $firmaId : 'global',
        ]);
    }

    /**
     * @param  array<int, string>  ...$parcalar
     */
    private static function diziAnahtari(string $tur, array ...$parcalar): string
    {
        return $tur.':'.md5(serialize($parcalar));
    }
}
