<?php

namespace App\Services;

use App\Models\FirmaAboneligi;
use App\Models\FirmaModulu;
use App\Models\Modul;
use App\Models\PlanModulu;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class ModulErisimService
{
    private const CACHE_TTL_SECONDS = 300;

    public static function firmaModuluCacheTemizle(int $firmaId): void
    {
        Cache::forget("modul-erisim:v1:firma-modulleri:{$firmaId}");
    }

    /** @var array<string, string> */
    protected array $modulDurumuCache = [];

    /** @var array<string, Modul|null> */
    protected array $modulKaydiCache = [];

    /** @var array<string, Modul>|null */
    protected ?array $aktifModullerCache = null;

    /** @var array<int, array<int, int>> */
    protected array $aktifPlanIdleriCache = [];

    /** @var array<string, bool> */
    protected array $planModuluCache = [];

    /** @var array<string, FirmaModulu|null> */
    protected array $firmaModuluCache = [];

    /** @var array<int, array<int, FirmaModulu>> */
    protected array $firmaModulleriCache = [];

    /** @var array<string, array<int, bool>> */
    protected array $planModulIdleriCache = [];

    public function modulDurumu(int $firmaId, string $modulKodu): string
    {
        $cacheKey = $firmaId.'|'.$modulKodu;

        if (array_key_exists($cacheKey, $this->modulDurumuCache)) {
            return $this->modulDurumuCache[$cacheKey];
        }

        $modul = $this->modulKaydi($modulKodu);

        if (! $modul) {
            return $this->modulDurumuCache[$cacheKey] = 'kapali';
        }

        $planModuluMu = $this->abonelikPlanindaModulVarMi($firmaId, (int) $modul->id);
        $firmaModulu = $this->firmaModulu($firmaId, (int) $modul->id);

        if ($firmaModulu) {
            $durum = (string) $firmaModulu->durum;

            if ($durum === 'kapali') {
                return $this->modulDurumuCache[$cacheKey] = 'kapali';
            }

            if ($durum === 'salt_okunur') {
                return $this->modulDurumuCache[$cacheKey] = 'salt_okunur';
            }

            if ($durum === 'aktif') {
                return $this->modulDurumuCache[$cacheKey] = 'aktif';
            }
        }

        // Firma üzerinde modül tanımları varsa erişim yalnızca bu tanımlara göre verilir.
        // Böylece planda bulunan ancak firmaya eklenmemiş modüller açılmaz.
        if ($this->firmaModulleri($firmaId) !== []) {
            return $this->modulDurumuCache[$cacheKey] = 'kapali';
        }

        return $this->modulDurumuCache[$cacheKey] = $planModuluMu ? 'aktif' : 'kapali';
    }

    public function modulErisilebilirMi(int $firmaId, string $modulKodu): bool
    {
        return $this->modulDurumu($firmaId, $modulKodu) !== 'kapali';
    }

    public function modulSaltOkunurMu(int $firmaId, string $modulKodu): bool
    {
        return $this->modulDurumu($firmaId, $modulKodu) === 'salt_okunur';
    }

    protected function abonelikPlanindaModulVarMi(int $firmaId, int $modulId): bool
    {
        $cacheKey = $firmaId.'|'.$modulId;

        if (array_key_exists($cacheKey, $this->planModuluCache)) {
            return $this->planModuluCache[$cacheKey];
        }

        $planIdler = $this->aktifPlanIdleri($firmaId);

        if ($planIdler === []) {
            return $this->planModuluCache[$cacheKey] = false;
        }

        return $this->planModuluCache[$cacheKey] = isset($this->planModulIdleri($planIdler)[$modulId]);
    }

    protected function modulKaydi(string $modulKodu): ?Modul
    {
        if (array_key_exists($modulKodu, $this->modulKaydiCache)) {
            return $this->modulKaydiCache[$modulKodu];
        }

        return $this->modulKaydiCache[$modulKodu] = $this->aktifModuller()[$modulKodu] ?? null;
    }

    /**
     * @return array<int, int>
     */
    protected function aktifPlanIdleri(int $firmaId): array
    {
        if (array_key_exists($firmaId, $this->aktifPlanIdleriCache)) {
            return $this->aktifPlanIdleriCache[$firmaId];
        }

        $bugun = Carbon::today()->toDateString();

        return $this->aktifPlanIdleriCache[$firmaId] = Cache::remember(
            "modul-erisim:v1:aktif-plan-idleri:{$firmaId}:{$bugun}",
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            fn (): array => FirmaAboneligi::query()
                ->withoutGlobalScopes()
                ->where('firma_id', $firmaId)
                ->where('durum', 'aktif')
                ->whereDate('baslangic_tarihi', '<=', $bugun)
                ->where(function ($q) use ($bugun): void {
                    $q->whereNull('bitis_tarihi')
                        ->orWhereDate('bitis_tarihi', '>=', $bugun);
                })
                ->pluck('plan_id')
                ->filter()
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all()
        );
    }

    protected function firmaModulu(int $firmaId, int $modulId): ?FirmaModulu
    {
        $cacheKey = $firmaId.'|'.$modulId;

        if (array_key_exists($cacheKey, $this->firmaModuluCache)) {
            return $this->firmaModuluCache[$cacheKey];
        }

        return $this->firmaModuluCache[$cacheKey] = $this->firmaModulleri($firmaId)[$modulId] ?? null;
    }

    /**
     * @return array<string, Modul>
     */
    protected function aktifModuller(): array
    {
        if ($this->aktifModullerCache !== null) {
            return $this->aktifModullerCache;
        }

        return $this->aktifModullerCache = Cache::remember(
            'modul-erisim:v1:aktif-moduller',
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            fn (): array => Modul::query()
                ->where('aktif_mi', true)
                ->get()
                ->keyBy(fn (Modul $modul): string => (string) $modul->kod)
                ->all()
        );
    }

    /**
     * @return array<int, FirmaModulu>
     */
    protected function firmaModulleri(int $firmaId): array
    {
        if (array_key_exists($firmaId, $this->firmaModulleriCache)) {
            return $this->firmaModulleriCache[$firmaId];
        }

        return $this->firmaModulleriCache[$firmaId] = Cache::remember(
            "modul-erisim:v1:firma-modulleri:{$firmaId}",
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            fn (): array => FirmaModulu::query()
                ->withoutGlobalScopes()
                ->where('firma_id', $firmaId)
                ->get()
                ->keyBy(fn (FirmaModulu $firmaModulu): int => (int) $firmaModulu->modul_id)
                ->all()
        );
    }

    /**
     * @param  array<int, int>  $planIdler
     * @return array<int, bool>
     */
    protected function planModulIdleri(array $planIdler): array
    {
        sort($planIdler);
        $cacheKey = implode(',', $planIdler);

        if (array_key_exists($cacheKey, $this->planModulIdleriCache)) {
            return $this->planModulIdleriCache[$cacheKey];
        }

        return $this->planModulIdleriCache[$cacheKey] = Cache::remember(
            "modul-erisim:v1:plan-modul-idleri:{$cacheKey}",
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            fn (): array => PlanModulu::query()
                ->whereIn('plan_id', $planIdler)
                ->pluck('modul_id')
                ->mapWithKeys(fn ($modulId): array => [(int) $modulId => true])
                ->all()
        );
    }
}
