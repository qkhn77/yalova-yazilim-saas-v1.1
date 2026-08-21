<?php

namespace App\Services;

use App\Models\FirmaKullanici;
use App\Models\KullaniciYetki;
use App\Models\Rol;
use App\Models\User;
use App\Models\Yetki;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class YetkiService
{
    private const CACHE_TTL_SECONDS = 300;

    /** @var array<string, array<int, string>> */
    protected array $etkinYetkilerCache = [];

    /** @var array<string, bool> */
    protected array $yetkiVarMiCache = [];

    /** @var array<string, Yetki|null> */
    protected array $yetkiKaydiCache = [];

    /** @var array<string, Yetki>|null */
    protected ?array $yetkiKayitlariCache = null;

    /** @var array<string, FirmaKullanici|null> */
    protected array $aktifFirmaKullaniciCache = [];

    /** @var array<string, bool> */
    protected array $kullaniciOzelIzinCache = [];

    /** @var array<string, array<int, array<string, bool>>> */
    protected array $kullaniciOzelIzinleriCache = [];

    /** @var array<int, bool> */
    protected array $firmaUstYonetimRoluCache = [];

    /** @var array<string, bool> */
    protected array $rolYetkisiCache = [];

    /** @var array<int, array<int, bool>> */
    protected array $rolYetkiIdleriCache = [];

    public function __construct(
        protected ModulErisimService $modulErisimService
    ) {}

    public function etkinYetkiler(User $kullanici, int $firmaId): array
    {
        $cacheKey = $kullanici->id.'|'.$firmaId;

        if (array_key_exists($cacheKey, $this->etkinYetkilerCache)) {
            return $this->etkinYetkilerCache[$cacheKey];
        }

        if ($this->superAdminMi($kullanici)) {
            return $this->etkinYetkilerCache[$cacheKey] = Yetki::query()->pluck('kod')->filter()->values()->all();
        }

        $firmaKullanici = FirmaKullanici::query()
            ->withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->where('kullanici_id', $kullanici->id)
            ->where('durum', 'aktif')
            ->whereNull('deleted_at')
            ->first();

        if (! $firmaKullanici) {
            return $this->etkinYetkilerCache[$cacheKey] = [];
        }

        $rolYetkiKodlari = Yetki::query()
            ->whereHas('roller', function ($q) use ($firmaKullanici): void {
                $q->where('roller.id', $firmaKullanici->rol_id);
            })
            ->pluck('kod')
            ->filter()
            ->values()
            ->all();

        $kodSet = array_fill_keys($rolYetkiKodlari, true);

        $kullaniciYetkiKayitlari = KullaniciYetki::query()
            ->withoutGlobalScopes()
            ->with('yetki:id,kod')
            ->where('firma_id', $firmaId)
            ->where('kullanici_id', $kullanici->id)
            ->get();

        foreach ($kullaniciYetkiKayitlari as $kayit) {
            $kod = $kayit->yetki?->kod;
            if (! $kod) {
                continue;
            }

            if ($kayit->izin_tipi === 'ver') {
                $kodSet[$kod] = true;

                continue;
            }

            if ($kayit->izin_tipi === 'reddet') {
                unset($kodSet[$kod]);
            }
        }

        return $this->etkinYetkilerCache[$cacheKey] = array_values(array_keys($kodSet));
    }

    public function yetkiVarMi(User $kullanici, int $firmaId, string $yetkiKodu): bool
    {
        $cacheKey = $kullanici->id.'|'.$firmaId.'|'.$yetkiKodu;

        if (array_key_exists($cacheKey, $this->yetkiVarMiCache)) {
            return $this->yetkiVarMiCache[$cacheKey];
        }

        if ($this->superAdminMi($kullanici)) {
            return $this->yetkiVarMiCache[$cacheKey] = true;
        }

        $yetki = $this->yetkiKaydi($yetkiKodu);
        if (! $yetki) {
            return $this->yetkiVarMiCache[$cacheKey] = false;
        }

        $firmaKullanici = $this->aktifFirmaKullanici($kullanici, $firmaId);

        if ($this->kullaniciOzelIzinKaydiVarMi($kullanici, $firmaId, (int) $yetki->id, 'reddet')) {
            return $this->yetkiVarMiCache[$cacheKey] = false;
        }

        if ($this->kullaniciOzelIzinKaydiVarMi($kullanici, $firmaId, (int) $yetki->id, 'ver')) {
            return $this->yetkiVarMiCache[$cacheKey] = true;
        }

        $modulKodu = (string) ($yetki->modul_kodu ?? '');

        // Firma sahibi / yöneticisi: açık iş modüllerinde tüm aksiyonlar;
        // sistem alanlarında da rol matrisi güncellenmesini beklemeden tam yetki.
        if ($this->firmaUstYonetimRoluMu($firmaKullanici)) {
            if ($modulKodu === '' || $this->sistemAlaniMi($modulKodu)) {
                return $this->yetkiVarMiCache[$cacheKey] = true;
            }

            return $this->yetkiVarMiCache[$cacheKey] = $this->modulErisimService->modulErisilebilirMi($firmaId, $modulKodu);
        }

        if ($modulKodu !== '' && ! $this->sistemAlaniMi($modulKodu)) {
            if (! $this->modulErisimService->modulErisilebilirMi($firmaId, $modulKodu)) {
                return $this->yetkiVarMiCache[$cacheKey] = false;
            }

            if ($this->modulErisimService->modulSaltOkunurMu($firmaId, $modulKodu) && ! $this->saltOkunurYetkiMi($yetkiKodu)) {
                return $this->yetkiVarMiCache[$cacheKey] = false;
            }
        }

        return $this->yetkiVarMiCache[$cacheKey] = $this->rolYetkisiVarMiWithFirmaKullanici($firmaKullanici, (int) $yetki->id);
    }

    /**
     * Firma sahibi veya firma yöneticisi (kanonik veya sistem_rolu kopyası firma_yoneticisi_*) mi?
     * Policy / middleware katmanında salt_okunur bypass için kullanılır.
     */
    public function firmaUstYonetimRoluMu(?FirmaKullanici $firmaKullanici): bool
    {
        if (! $firmaKullanici?->rol_id) {
            return false;
        }

        $rolId = (int) $firmaKullanici->rol_id;

        if (array_key_exists($rolId, $this->firmaUstYonetimRoluCache)) {
            return $this->firmaUstYonetimRoluCache[$rolId];
        }

        $firmaKullaniciOznitelikleri = $firmaKullanici->getAttributes();
        if (
            array_key_exists('rol_kod', $firmaKullaniciOznitelikleri)
            || array_key_exists('rol_sistem_rolu_mu', $firmaKullaniciOznitelikleri)
        ) {
            return $this->firmaUstYonetimRoluCache[$rolId] = $this->firmaUstYonetimRoluDegeri(
                (string) ($firmaKullanici->getAttribute('rol_kod') ?? ''),
                (bool) ($firmaKullanici->getAttribute('rol_sistem_rolu_mu') ?? false)
            );
        }

        $rol = Rol::query()->find($rolId);
        if (! $rol || ! ($rol->sistem_rolu_mu ?? false)) {
            return $this->firmaUstYonetimRoluCache[$rolId] = false;
        }

        return $this->firmaUstYonetimRoluCache[$rolId] = $this->firmaUstYonetimRoluDegeri(
            (string) $rol->kod,
            (bool) ($rol->sistem_rolu_mu ?? false)
        );
    }

    protected function firmaUstYonetimRoluDegeri(string $kod, bool $sistemRoluMu): bool
    {
        if (! $sistemRoluMu) {
            return false;
        }

        if ($kod === 'firma_sahibi') {
            return true;
        }

        if ($kod === 'firma_yoneticisi') {
            return true;
        }

        return str_starts_with($kod, 'firma_yoneticisi_');
    }

    public function firmaUstYonetimRoluMuKullaniciIcin(User $kullanici, int $firmaId): bool
    {
        return $this->firmaUstYonetimRoluMu($this->aktifFirmaKullanici($kullanici, $firmaId));
    }

    public function yetkiAtayabilirMi(User $verenKullanici, int $firmaId, string $yetkiKodu): bool
    {
        if ($this->superAdminMi($verenKullanici)) {
            return true;
        }

        $yetki = Yetki::query()->where('kod', $yetkiKodu)->first();
        if (! $yetki) {
            return false;
        }

        if (! $this->yetkiVarMi($verenKullanici, $firmaId, $yetkiKodu)) {
            return false;
        }

        if (! $yetki->modul_kodu) {
            return true;
        }

        if ($this->sistemAlaniMi((string) $yetki->modul_kodu)) {
            return true;
        }

        return $this->modulErisimService->modulErisilebilirMi($firmaId, (string) $yetki->modul_kodu);
    }

    /**
     * Özel yetki (ver/reddet) ekranında listelenebilecek yetkiler.
     *
     * @return Collection<int, Yetki>
     */
    public function atanabilirYetkiKayitlari(User $verenKullanici, int $firmaId): Collection
    {
        return Yetki::query()
            ->orderBy('modul_kodu')
            ->orderBy('kod')
            ->get()
            ->filter(fn (Yetki $yetki): bool => $this->yetkiAtayabilirMi($verenKullanici, $firmaId, (string) $yetki->kod));
    }

    protected function superAdminMi(User $kullanici): bool
    {
        return (bool) ($kullanici->super_admin_mi ?? false) || (bool) ($kullanici->is_admin ?? false);
    }

    protected function sistemAlaniMi(string $modulKodu): bool
    {
        return in_array($modulKodu, ['firma', 'kullanici', 'modul'], true);
    }

    protected function saltOkunurYetkiMi(string $yetkiKodu): bool
    {
        foreach ([
            '.goruntule',
            '.listele',
            '.indir',
            '.yazdir',
            '.rapor_goruntule',
        ] as $sonek) {
            if (str_ends_with($yetkiKodu, $sonek)) {
                return true;
            }
        }

        return false;
    }

    protected function kullaniciOzelIzinKaydiVarMi(User $kullanici, int $firmaId, int $yetkiId, string $izinTipi): bool
    {
        $cacheKey = $kullanici->id.'|'.$firmaId.'|'.$yetkiId.'|'.$izinTipi;

        if (array_key_exists($cacheKey, $this->kullaniciOzelIzinCache)) {
            return $this->kullaniciOzelIzinCache[$cacheKey];
        }

        $izinler = $this->kullaniciOzelIzinleri($kullanici, $firmaId);

        return $this->kullaniciOzelIzinCache[$cacheKey] = isset($izinler[$yetkiId][$izinTipi]);
    }

    /**
     * @return array<int, array<string, bool>>
     */
    protected function kullaniciOzelIzinleri(User $kullanici, int $firmaId): array
    {
        $cacheKey = $kullanici->id.'|'.$firmaId;

        if (array_key_exists($cacheKey, $this->kullaniciOzelIzinleriCache)) {
            return $this->kullaniciOzelIzinleriCache[$cacheKey];
        }

        $izinler = [];

        Cache::remember(
            "yetki:v1:kullanici-ozel-izinleri:{$kullanici->id}:{$firmaId}",
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            fn () => KullaniciYetki::query()
                ->withoutGlobalScopes()
                ->where('firma_id', $firmaId)
                ->where('kullanici_id', (int) $kullanici->id)
                ->get(['yetki_id', 'izin_tipi'])
        )->each(function (KullaniciYetki $kayit) use (&$izinler): void {
                $yetkiId = (int) $kayit->yetki_id;
                $izinTipi = (string) $kayit->izin_tipi;

                if ($yetkiId > 0 && $izinTipi !== '') {
                    $izinler[$yetkiId][$izinTipi] = true;
                }
            });

        return $this->kullaniciOzelIzinleriCache[$cacheKey] = $izinler;
    }

    protected function rolYetkisiVarMi(User $kullanici, int $firmaId, int $yetkiId): bool
    {
        return $this->rolYetkisiVarMiWithFirmaKullanici($this->aktifFirmaKullanici($kullanici, $firmaId), $yetkiId);
    }

    protected function aktifFirmaKullanici(User $kullanici, int $firmaId): ?FirmaKullanici
    {
        $cacheKey = $kullanici->id.'|'.$firmaId;

        if (array_key_exists($cacheKey, $this->aktifFirmaKullaniciCache)) {
            return $this->aktifFirmaKullaniciCache[$cacheKey];
        }

        return $this->aktifFirmaKullaniciCache[$cacheKey] = Cache::remember(
            "yetki:v1:aktif-firma-kullanici:{$kullanici->id}:{$firmaId}",
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            fn (): ?FirmaKullanici => FirmaKullanici::query()
                ->withoutGlobalScopes()
                ->leftJoin('roller', function ($join): void {
                    $join->on('roller.id', '=', 'firma_kullanicilari.rol_id')
                        ->whereNull('roller.deleted_at');
                })
                ->where('firma_id', $firmaId)
                ->where('kullanici_id', (int) $kullanici->id)
                ->where('durum', 'aktif')
                ->whereNull('firma_kullanicilari.deleted_at')
                ->select([
                    'firma_kullanicilari.*',
                    'roller.kod as rol_kod',
                    'roller.sistem_rolu_mu as rol_sistem_rolu_mu',
                ])
                ->first()
        );
    }

    protected function rolYetkisiVarMiWithFirmaKullanici(?FirmaKullanici $firmaKullanici, int $yetkiId): bool
    {
        if (! $firmaKullanici || ! $firmaKullanici->rol_id) {
            return false;
        }

        $cacheKey = ((int) $firmaKullanici->rol_id).'|'.$yetkiId;

        if (array_key_exists($cacheKey, $this->rolYetkisiCache)) {
            return $this->rolYetkisiCache[$cacheKey];
        }

        return $this->rolYetkisiCache[$cacheKey] = isset($this->rolYetkiIdleri((int) $firmaKullanici->rol_id)[$yetkiId]);
    }

    protected function yetkiKaydi(string $yetkiKodu): ?Yetki
    {
        if (array_key_exists($yetkiKodu, $this->yetkiKaydiCache)) {
            return $this->yetkiKaydiCache[$yetkiKodu];
        }

        return $this->yetkiKaydiCache[$yetkiKodu] = $this->yetkiKayitlari()[$yetkiKodu] ?? null;
    }

    /**
     * @return array<string, Yetki>
     */
    protected function yetkiKayitlari(): array
    {
        if ($this->yetkiKayitlariCache !== null) {
            return $this->yetkiKayitlariCache;
        }

        return $this->yetkiKayitlariCache = Cache::remember(
            'yetki:v1:yetki-kayitlari',
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            fn (): array => Yetki::query()
                ->get()
                ->keyBy(fn (Yetki $yetki): string => (string) $yetki->kod)
                ->all()
        );
    }

    /**
     * @return array<int, bool>
     */
    protected function rolYetkiIdleri(int $rolId): array
    {
        if ($rolId < 1) {
            return [];
        }

        if (array_key_exists($rolId, $this->rolYetkiIdleriCache)) {
            return $this->rolYetkiIdleriCache[$rolId];
        }

        return $this->rolYetkiIdleriCache[$rolId] = Cache::remember(
            "yetki:v1:rol-yetki-idleri:{$rolId}",
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            fn (): array => DB::table('rol_yetkileri')
                ->where('rol_id', $rolId)
                ->pluck('yetki_id')
                ->mapWithKeys(fn ($yetkiId): array => [(int) $yetkiId => true])
                ->all()
        );
    }
}
