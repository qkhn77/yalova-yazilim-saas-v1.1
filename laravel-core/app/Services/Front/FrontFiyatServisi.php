<?php

namespace App\Services\Front;

use App\Models\Setting;
use App\Models\Muhasebe\DovizKuru;
use App\Services\TenantContextService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class FrontFiyatServisi
{
    private const KUR_CACHE_VERSION_KEY = 'front:try-kur:surum';
    private const KUR_CACHE_SECONDS = 600;

    /**
     * @var array<string, float>
     */
    private static array $tryKurCache = [];

    private static ?string $kurCacheVersion = null;

    /**
     * @var array<string, array{kaynak:string, hedef:string}>
     */
    private static array $eksikKurCiftleri = [];

    public function __construct(
        private readonly FrontTercihServisi $tercihServisi,
        private readonly TenantContextService $tenantContextService,
    ) {}

    public function aktifParaBirimi(): string
    {
        return $this->tercihServisi->aktifParaBirimi();
    }

    public function kdvDahilGosterimAktifMi(): bool
    {
        return filter_var(Setting::get('front_prices_include_vat', true), FILTER_VALIDATE_BOOL);
    }

    public function cevir(float $tutar, string $kaynakParaBirimi, ?string $hedefParaBirimi = null): float
    {
        $kaynak = strtoupper(trim($kaynakParaBirimi ?: 'TRY'));
        $hedef = strtoupper(trim($hedefParaBirimi ?: $this->aktifParaBirimi()));

        if ($kaynak === $hedef) {
            return round($tutar, 2);
        }

        $kaynakTry = $this->tryKurunuBul($kaynak);
        $hedefTry = $this->tryKurunuBul($hedef);

        if ($kaynakTry <= 0 || $hedefTry <= 0) {
            $this->eksikKurCiftiniKaydet($kaynak, $hedef);

            return round($tutar, 2);
        }

        return round(($tutar * $kaynakTry) / $hedefTry, 2);
    }

    public function cevrilebilirMi(string $kaynakParaBirimi, ?string $hedefParaBirimi = null): bool
    {
        $kaynak = strtoupper(trim($kaynakParaBirimi ?: 'TRY'));
        $hedef = strtoupper(trim($hedefParaBirimi ?: $this->aktifParaBirimi()));

        if ($kaynak === $hedef) {
            return true;
        }

        $cevrilebilir = $this->tryKurunuBul($kaynak) > 0 && $this->tryKurunuBul($hedef) > 0;
        if (! $cevrilebilir) {
            $this->eksikKurCiftiniKaydet($kaynak, $hedef);
        }

        return $cevrilebilir;
    }

    public function eksikKurVarMi(): bool
    {
        return self::$eksikKurCiftleri !== [];
    }

    /**
     * @return array<int, string>
     */
    public function eksikKurMesajlari(): array
    {
        return array_values(array_map(
            fn (array $cift): string => $cift['kaynak'].'/'.$cift['hedef'].' kuru bulunamadı.',
            self::$eksikKurCiftleri
        ));
    }

    public function eksikKurKayitlariniTemizle(): void
    {
        self::$eksikKurCiftleri = [];
    }

    public static function dovizKuruCacheTemizle(): void
    {
        self::$tryKurCache = [];
        self::$kurCacheVersion = null;

        Cache::forever(self::KUR_CACHE_VERSION_KEY, str_replace([' ', '.'], '', (string) microtime()));
    }

    public function formatla(float $tutar, ?string $paraBirimi = null): string
    {
        $pb = strtoupper(trim($paraBirimi ?: $this->aktifParaBirimi()));
        $sembol = match ($pb) {
            'USD' => '$',
            'EUR' => '€',
            default => '₺',
        };

        return $sembol.number_format($tutar, 2, ',', '.');
    }

    public function cevirVeFormatla(float $tutar, string $kaynakParaBirimi, ?string $hedefParaBirimi = null): string
    {
        $hedef = strtoupper(trim($hedefParaBirimi ?: $this->aktifParaBirimi()));
        $cevrilen = $this->cevir($tutar, $kaynakParaBirimi, $hedef);

        return $this->formatla($cevrilen, $hedef);
    }

    public function kdvDahilTutari(float $tutar, float $kdvOrani): float
    {
        return round($tutar * (1 + ($kdvOrani / 100)), 2);
    }

    public function gosterimTutari(float $tutar, float $kdvOrani = 0, ?bool $kdvDahil = null): float
    {
        $kdvDahil ??= $this->kdvDahilGosterimAktifMi();

        return $kdvDahil
            ? $this->kdvDahilTutari($tutar, $kdvOrani)
            : round($tutar, 2);
    }

    public function satisFiyatiFormatla(
        float $tutar,
        float $kdvOrani,
        string $kaynakParaBirimi,
        ?string $hedefParaBirimi = null,
        ?bool $kdvDahil = null,
    ): string {
        return $this->cevirVeFormatla(
            $this->gosterimTutari($tutar, $kdvOrani, $kdvDahil),
            $kaynakParaBirimi,
            $hedefParaBirimi,
        );
    }

    private function tryKurunuBul(string $paraBirimi): float
    {
        $kod = strtoupper(trim($paraBirimi));
        if ($kod === 'TRY') {
            return 1.0;
        }

        $tarih = Carbon::today()->toDateString();
        $firmaId = (int) ($this->tenantContextService->aktifFirmaId() ?? 0);
        $runtimeKey = $firmaId.'|'.$tarih.'|'.$kod;

        if (array_key_exists($runtimeKey, self::$tryKurCache)) {
            return self::$tryKurCache[$runtimeKey];
        }

        $cacheKey = 'front:try-kur:'.self::kurCacheVersiyonu().':firma:'.$firmaId.':tarih:'.$tarih.':'.$kod;

        return self::$tryKurCache[$runtimeKey] = (float) Cache::remember(
            $cacheKey,
            self::KUR_CACHE_SECONDS,
            fn (): float => $this->tryKurunuVeritabanindanBul($kod, $tarih, $firmaId)
        );
    }

    private function tryKurunuVeritabanindanBul(string $kod, string $tarih, int $firmaId): float
    {
        $kayit = $this->kurKaydiniBul($kod, 'TRY', $tarih, $firmaId);

        if ($kayit && (float) $kayit->kur > 0) {
            return (float) $kayit->kur;
        }

        $ters = $this->kurKaydiniBul('TRY', $kod, $tarih, $firmaId);

        if ($ters && (float) $ters->kur > 0) {
            return 1 / (float) $ters->kur;
        }

        return 0.0;
    }

    private function eksikKurCiftiniKaydet(string $kaynak, string $hedef): void
    {
        self::$eksikKurCiftleri[$kaynak.'>'.$hedef] = [
            'kaynak' => $kaynak,
            'hedef' => $hedef,
        ];
    }

    private static function kurCacheVersiyonu(): string
    {
        if (self::$kurCacheVersion !== null) {
            return self::$kurCacheVersion;
        }

        return self::$kurCacheVersion = (string) Cache::rememberForever(
            self::KUR_CACHE_VERSION_KEY,
            fn (): string => '1'
        );
    }

    private function kurKaydiniBul(string $kaynak, string $hedef, string $tarih, int $firmaId): ?DovizKuru
    {
        if (! Schema::hasTable('muhasebe_doviz_kurlari')) {
            return null;
        }

        return DovizKuru::tenantScopeOlmadan(function () use ($kaynak, $hedef, $tarih, $firmaId): ?DovizKuru {
            return DovizKuru::query()
                ->where('kaynak_para_birimi', $kaynak)
                ->where('hedef_para_birimi', $hedef)
                ->whereDate('tarih', '<=', $tarih)
                ->where(function ($query) use ($firmaId): void {
                    if ($firmaId > 0) {
                        $query->where('firma_id', $firmaId)
                            ->orWhere(function ($sabit): void {
                                $sabit->whereNull('firma_id')->where('is_sabit', true);
                            });

                        return;
                    }

                    $query->whereNull('firma_id')->where('is_sabit', true);
                })
                ->orderByRaw('CASE WHEN firma_id IS NULL THEN 0 ELSE 1 END DESC')
                ->orderByDesc('tarih')
                ->orderByDesc('id')
                ->first();
        });
    }
}
