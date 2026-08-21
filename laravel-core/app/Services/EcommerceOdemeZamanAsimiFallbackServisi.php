<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EcommerceOdemeZamanAsimiFallbackServisi
{
    public function __construct(
        private readonly FirmaAyarDeposu $depo,
    ) {}

    /**
     * Hosting/cPanel ortamında scheduler yoksa bu servisle ödeme zaman aşımı işlemi tetiklenebilir.
     *
     * @return array{calisti:bool, sebep:string, firma_id:?int}
     */
    public function tetikle(?int $firmaId = null, bool $force = false): array
    {
        $firmaId = $firmaId !== null && (int) $firmaId > 0 ? (int) $firmaId : null;

        $throttleDakika = (int) config('ecommerce.cron_fallback_throttle_dakika', 5);
        $now = now();

        if ($firmaId !== null) {
            $cronFallbackEtkin = $this->depo->oku($firmaId, 'ecommerce_cron_fallback_etkin_mi', null);
            if ($cronFallbackEtkin !== null && ! (bool) $cronFallbackEtkin) {
                return [
                    'calisti' => false,
                    'sebep' => 'cron_fallback_kapali',
                    'firma_id' => $firmaId,
                ];
            }

            $sonCalisma = $this->depo->oku($firmaId, 'ecommerce_son_zaman_asimi_isleme_at', null);
            if (! $force && is_string($sonCalisma) && $sonCalisma !== '') {
                try {
                    $dt = Carbon::parse($sonCalisma);
                    if ($dt->diffInMinutes($now) < $throttleDakika) {
                        return [
                            'calisti' => false,
                            'sebep' => 'throttle',
                            'firma_id' => $firmaId,
                        ];
                    }
                } catch (\Throwable) {
                    // timestamp parse edilemiyorsa fallback'u yine de dene.
                }
            }
        } else {
            // Firma bilgisi verilmezse global throttle kullan.
            $sonCalisma = Cache::get('ecommerce_son_zaman_asimi_fallback_global_at');
            if (! $force && is_string($sonCalisma) && $sonCalisma !== '') {
                try {
                    $dt = Carbon::parse($sonCalisma);
                    if ($dt->diffInMinutes($now) < $throttleDakika) {
                        return [
                            'calisti' => false,
                            'sebep' => 'throttle_global',
                            'firma_id' => null,
                        ];
                    }
                } catch (\Throwable) {
                    // parse edilemezse yine de dene.
                }
            }
        }

        $lockKey = $firmaId !== null
            ? 'ecommerce_zaman_asimi_fallback_lock_'.$firmaId
            : 'ecommerce_zaman_asimi_fallback_global_lock';

        $lock = Cache::lock($lockKey, 30);
        if (! $lock->get()) {
            return [
                'calisti' => false,
                'sebep' => 'lock',
                'firma_id' => $firmaId,
            ];
        }

        try {
            $args = [];
            if ($firmaId !== null) {
                $args['--firma_id'] = $firmaId;
            }

            if ($firmaId !== null) {
                $this->depo->yaz($firmaId, 'ecommerce_son_zaman_asimi_isleme_at', $now->toDateTimeString());
            } else {
                Cache::put('ecommerce_son_zaman_asimi_fallback_global_at', $now->toDateTimeString(), $throttleDakika * 60 + 60);
            }

            Log::warning('E-ticaret: cron fallback tetiklendi', [
                'firma_id' => $firmaId,
                'force' => $force,
            ]);

            Artisan::call('siparis:odeme-zaman-asimi-isle', $args);

            Log::warning('E-ticaret: zaman aşımı işlemi fallback üzerinden çalıştı', [
                'firma_id' => $firmaId,
            ]);

            return [
                'calisti' => true,
                'sebep' => 'ok',
                'firma_id' => $firmaId,
            ];
        } finally {
            optional($lock)->release();
        }
    }
}
