<?php

namespace App\Services;

use App\Models\Firma;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\PosHesabi;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Providers\Filament\AdminPanelProvider;
use App\Support\DenetimYardimcisi;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class EcommerceFirmaAyarServisi
{
    private static int $runtimeCacheSurumu = 1;

    public function __construct(
        private readonly FirmaAyarDeposu $depo,
    ) {}

    public function ayarVarMi(int $firmaId): bool
    {
        return $this->depo->oku($firmaId, 'ecommerce_etkin_mi', null) !== null;
    }

    public function firmaEtkinMi(int $firmaId, bool $varsayilan = false): bool
    {
        if ($firmaId <= 0) {
            return $varsayilan;
        }

        if (! $this->ayarVarMi($firmaId)) {
            return $varsayilan;
        }

        return (bool) $this->depo->oku($firmaId, 'ecommerce_etkin_mi', false);
    }

    public function runtimeCacheSurumu(): int
    {
        return self::$runtimeCacheSurumu;
    }

    public function odemeDakika(int $firmaId): int
    {
        $varsayilan = (int) config('ecommerce.odeme_dakika', 15);

        if ($firmaId <= 0 || ! $this->ayarVarMi($firmaId)) {
            return max(1, $varsayilan);
        }

        $v = $this->nullableInt($this->depo->oku($firmaId, 'ecommerce_odeme_dakika', $varsayilan));

        return max(1, $v ?? $varsayilan);
    }

    /**
     * @return array{cari_id:?int,kasa_id:?int,pos_id:?int,ayar_var_mi:bool}
     */
    public function tahsilatIds(int $firmaId): array
    {
        if ($firmaId <= 0) {
            return [
                'cari_id' => null,
                'kasa_id' => null,
                'pos_id' => null,
                'ayar_var_mi' => false,
            ];
        }

        $ayarVarMi = $this->ayarVarMi($firmaId);

        if (! $ayarVarMi) {
            $cari = $this->nullableInt(config('ecommerce.tahsilat_cari_id'));
            $kasa = $this->nullableInt(config('ecommerce.tahsilat_kasa_id'));

            return [
                'cari_id' => $cari,
                'kasa_id' => $kasa,
                'pos_id' => null,
                'ayar_var_mi' => false,
            ];
        }

        return [
            'cari_id' => $this->nullableInt($this->depo->oku($firmaId, 'ecommerce_tahsilat_cari_id', null)),
            'kasa_id' => $this->nullableInt($this->depo->oku($firmaId, 'ecommerce_tahsilat_kasa_id', null)),
            'pos_id' => $this->nullableInt($this->depo->oku($firmaId, 'ecommerce_tahsilat_pos_id', null)),
            'ayar_var_mi' => true,
        ];
    }

    public function kontrolTahsilatBaslangicVeyaHata(int $firmaId): void
    {
        if ($firmaId <= 0) {
            return;
        }

        if (! $this->ayarVarMi($firmaId)) {
            // Geçiş süreci: firma özel ayar yoksa legacy .env/config ile devam et.
            return;
        }

        $aktif = (bool) $this->depo->oku($firmaId, 'ecommerce_etkin_mi', false);
        if (! $aktif) {
            $link = url('/'.AdminPanelProvider::adminPath().'/firma-ayarlari');

            throw ValidationException::withMessages([
                'ecommerce' => 'E-ticaret tahsilatı bu firma için kapalı. Lütfen firma ayarlarından aktif edin: '.$link,
            ]);
        }

        $ids = $this->tahsilatIds($firmaId);

        if (! $ids['cari_id']) {
            $link = url('/'.AdminPanelProvider::adminPath().'/firma-ayarlari');

            throw ValidationException::withMessages([
                'ecommerce' => 'Tahsilat cari hesabı seçilmemiş. Önce firma ayarlarından tahsilat cari hesabını seçin: '.$link,
            ]);
        }

        if (! $ids['kasa_id']) {
            $link = url('/'.AdminPanelProvider::adminPath().'/firma-ayarlari');

            throw ValidationException::withMessages([
                'ecommerce' => 'Önce kasa ekleyin veya varsayılan kasa belirleyin. Lütfen firma ayarlarından tahsilat kasasını seçin: '.$link,
            ]);
        }

        // POS opsiyonel; yalnızca firma POS id sakladıysa varlığını kontrol et.
        if ($ids['pos_id']) {
            $posVarMi = PosHesabi::tenantScopeOlmadan(fn () => PosHesabi::query()
                ->where('firma_id', $firmaId)
                ->whereKey($ids['pos_id'])
                ->exists());

            if (! $posVarMi) {
                $link = url('/'.AdminPanelProvider::adminPath().'/firma-ayarlari');

                throw ValidationException::withMessages([
                    'ecommerce' => 'POS hesabı bulunmuyor, önce POS ekleyin. Lütfen firma ayarlarından güncelleyin: '.$link,
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function kaydetAyarlar(int $firmaId, array $data): void
    {
        if ($firmaId <= 0) {
            return;
        }

        self::$runtimeCacheSurumu++;

        $deger = fn (string $key, mixed $default = null): mixed => $data[$key] ?? $default;
        $oncekiEtkin = (bool) $this->depo->oku($firmaId, 'ecommerce_etkin_mi', false);
        $initializedAt = $this->depo->oku($firmaId, 'ecommerce_initialized_at', null);
        $ilkAcilisMi = ! is_string($initializedAt) || trim($initializedAt) === '';

        $etkin = (bool) $deger('ecommerce_etkin_mi', false);
        $otomatikGenelKasa = (bool) $deger('ecommerce_otomatik_genel_kasa_kullan', true);
        $cronFallbackEtkin = (bool) $deger('ecommerce_cron_fallback_etkin_mi', true);

        $cariId = $this->nullableInt($deger('ecommerce_tahsilat_cari_id', null));
        $kasaId = $this->nullableInt($deger('ecommerce_tahsilat_kasa_id', null));
        $posId = $this->nullableInt($deger('ecommerce_tahsilat_pos_id', null));

        $odemeDakika = $this->nullableInt($deger('ecommerce_odeme_dakika', config('ecommerce.odeme_dakika', 15)));
        $odemeDakika = max(1, (int) ($odemeDakika ?? 15));

        $this->depo->yaz($firmaId, 'ecommerce_etkin_mi', $etkin);
        $this->depo->yaz($firmaId, 'ecommerce_tahsilat_cari_id', $cariId);
        $this->depo->yaz($firmaId, 'ecommerce_tahsilat_kasa_id', $kasaId);
        $this->depo->yaz($firmaId, 'ecommerce_tahsilat_pos_id', $posId);
        $this->depo->yaz($firmaId, 'ecommerce_odeme_dakika', $odemeDakika);
        $this->depo->yaz($firmaId, 'ecommerce_otomatik_genel_kasa_kullan', $otomatikGenelKasa);
        $this->depo->yaz($firmaId, 'ecommerce_cron_fallback_etkin_mi', $cronFallbackEtkin);

        if ($etkin && $ilkAcilisMi) {
            $this->ilkKurulumVarsayilanlariniYukle($firmaId);
        }

        if ($oncekiEtkin !== $etkin) {
            DenetimYardimcisi::kaydet(
                olay: 'ecommerce.modul_durumu_guncellendi',
                konuTipi: Firma::class,
                konuId: $firmaId,
                firmaId: $firmaId,
                eskiVeri: ['ecommerce_etkin_mi' => $oncekiEtkin],
                yeniVeri: ['ecommerce_etkin_mi' => $etkin],
            );
        }

        if ($etkin && $otomatikGenelKasa && ! $kasaId) {
            $this->otomatikGenelKasaOlusturVeBagla($firmaId);
        }
    }

    private function ilkKurulumVarsayilanlariniYukle(int $firmaId): void
    {
        $simdi = now()->toDateTimeString();

        $this->depo->yaz($firmaId, 'ecommerce_initialized_at', $simdi);
        $this->depo->yaz($firmaId, 'ecommerce_kurulum_versiyon', 1);

        DenetimYardimcisi::kaydet(
            olay: 'ecommerce.ilk_kurulum_tamamlandi',
            konuTipi: Firma::class,
            konuId: $firmaId,
            firmaId: $firmaId,
            eskiVeri: null,
            yeniVeri: [
                'ecommerce_initialized_at' => $simdi,
                'ecommerce_kurulum_versiyon' => 1,
            ],
        );
    }

    private function otomatikGenelKasaOlusturVeBagla(int $firmaId): void
    {
        $varka = KasaHesabi::query()->where('firma_id', $firmaId)->exists();
        if ($varka) {
            return;
        }

        $lock = Cache::lock('ecommerce_otomatik_genel_kasa_'.$firmaId, 10);
        if (! $lock->get()) {
            // Aynı anda başka request oluşturuyor olabilir.
            return;
        }

        try {
            // Double-check.
            if (KasaHesabi::query()->where('firma_id', $firmaId)->exists()) {
                return;
            }

            $paraBirimi = (string) $this->depo->oku($firmaId, 'para_birimi', 'TRY');
            $kasa = KasaHesabi::query()->create([
                'firma_id' => $firmaId,
                'kod' => 'GENEL-KASA',
                'ad' => 'Genel Kasa',
                'para_birimi' => $paraBirimi,
                'sorumlu' => null,
                'aciklama' => 'E-ticaret tahsilatı için otomatik varsayılan kasa',
                'durum' => HesapDurumu::Aktif,
            ]);

            $this->depo->yaz($firmaId, 'ecommerce_tahsilat_kasa_id', (int) $kasa->id);

            Log::info('E-ticaret: varsayılan genel kasa otomatik oluşturuldu', [
                'firma_id' => $firmaId,
                'kasa_hesap_id' => $kasa->id,
            ]);
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * @return array<string, string>
     */
    public function cariSecenekleri(int $firmaId): array
    {
        if ($firmaId <= 0) {
            return [];
        }

        return Cari::tenantScopeOlmadan(function () use ($firmaId): array {
            return Cari::query()
                ->where('firma_id', $firmaId)
                ->orderBy('ad')
                ->get(['id', 'ad', 'kod'])
                ->mapWithKeys(fn (Cari $cari): array => [(string) $cari->id => $cari->ad.' ('.$cari->kod.')'])
                ->all();
        });
    }

    /**
     * @return array<int, string>
     */
    public function kasaSecenekleri(int $firmaId): array
    {
        if ($firmaId <= 0) {
            return [];
        }

        return KasaHesabi::query()
            ->where('firma_id', $firmaId)
            ->where('durum', HesapDurumu::Aktif->value)
            ->orderBy('ad')
            ->get(['id', 'ad', 'kod'])
            ->mapWithKeys(fn (KasaHesabi $kasa): array => [(int) $kasa->id => $kasa->ad.' ('.$kasa->kod.')'])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function posSecenekleri(int $firmaId): array
    {
        if ($firmaId <= 0) {
            return [];
        }

        return PosHesabi::tenantScopeOlmadan(function () use ($firmaId): array {
            return PosHesabi::query()
                ->where('firma_id', $firmaId)
                ->where('durum', HesapDurumu::Aktif->value)
                ->orderBy('ad')
                ->get(['id', 'ad', 'kod'])
                ->mapWithKeys(fn (PosHesabi $pos): array => [(int) $pos->id => $pos->ad.' ('.$pos->kod.')'])
                ->all();
        });
    }

    private function nullableInt(mixed $v): ?int
    {
        if ($v === null) {
            return null;
        }

        if (is_string($v)) {
            $v = trim($v);
        }

        if ($v === '') {
            return null;
        }

        if (! is_numeric($v)) {
            return null;
        }

        $i = (int) $v;
        if ($i <= 0) {
            return null;
        }

        return $i;
    }

    public function logEksikEcommerceAyarUyarisiThrottled(int $firmaId, string $mesaj): void
    {
        if ($firmaId <= 0) {
            return;
        }

        $throttleDakika = (int) config('ecommerce.cron_fallback_throttle_dakika', 5);
        $son = $this->depo->oku($firmaId, 'ecommerce_son_scheduler_uyari_at', null);

        if (is_string($son) && $son !== '') {
            try {
                $dt = Carbon::parse($son);
                if ($dt->diffInMinutes(now()) < $throttleDakika) {
                    return;
                }
            } catch (\Throwable) {
                // sessiz
            }
        }

        $this->depo->yaz($firmaId, 'ecommerce_son_scheduler_uyari_at', now()->toDateTimeString());

        Log::warning('E-ticaret: tahsilat ayarı eksik uyarısı (legacy mod)', [
            'firma_id' => $firmaId,
            'mesaj' => $mesaj,
        ]);
    }
}
