<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\AlacakPlanOnayTalebi;
use App\Models\Muhasebe\AlacakPlani;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use Illuminate\Support\Facades\DB;

class AlacakPlanOnayServisi
{
    public const DURUM_BEKLIYOR = 'bekliyor';

    public const DURUM_ONAYLANDI = 'onaylandi';

    public const DURUM_REDDEDILDI = 'reddedildi';

    public const TUR_IPTAL = 'iptal';

    public const TUR_REVIZYON = 'revizyon';

    public function __construct(
        private readonly AlacakPlanServisi $alacakPlanServisi,
    ) {}

    public function onayGerektirir(AlacakPlani $plan, bool $kullaniciOnaylayabilir): bool
    {
        if ($kullaniciOnaylayabilir) {
            return false;
        }

        return bccomp($this->riskTutari($plan), $this->onayLimiti(), 2) >= 0;
    }

    public function onayLimiti(): string
    {
        $limit = (float) config('muhasebe.alacak_plan_onay_limiti', 1000);

        return number_format(max(0, $limit), 2, '.', '');
    }

    /**
     * @param array<string,mixed> $istenenVeri
     */
    public function talepOlustur(
        AlacakPlani $plan,
        string $talepTuru,
        array $istenenVeri,
        string $gerekce,
        ?int $talepEdenId = null
    ): AlacakPlanOnayTalebi {
        $talepTuru = $this->talepTuru($talepTuru);
        $gerekce = trim($gerekce);
        if (mb_strlen($gerekce) < 10) {
            throw new IsKuraliIstisnasi('Onay talebi icin en az 10 karakterlik gerekce zorunludur.');
        }

        return DB::transaction(function () use ($plan, $talepTuru, $istenenVeri, $gerekce, $talepEdenId): AlacakPlanOnayTalebi {
            $plan = AlacakPlani::query()
                ->whereKey($plan->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $mevcut = $this->bekleyenTalep($plan, $talepTuru);
            if ($mevcut) {
                return $mevcut;
            }

            return AlacakPlanOnayTalebi::query()->create([
                'firma_id' => (int) $plan->firma_id,
                'alacak_plan_id' => (int) $plan->getKey(),
                'talep_turu' => $talepTuru,
                'durum' => self::DURUM_BEKLIYOR,
                'risk_tutari' => $this->riskTutari($plan),
                'para_birimi' => strtoupper((string) ($plan->para_birimi ?: 'TRY')),
                'onceki_veri' => $this->planOzeti($plan),
                'istenen_veri' => $istenenVeri,
                'gerekce' => $gerekce,
                'talep_eden_id' => $talepEdenId,
            ]);
        });
    }

    public function bekleyenTalep(AlacakPlani $plan, string $talepTuru): ?AlacakPlanOnayTalebi
    {
        return AlacakPlanOnayTalebi::query()
            ->where('firma_id', (int) $plan->firma_id)
            ->where('alacak_plan_id', (int) $plan->getKey())
            ->where('talep_turu', $this->talepTuru($talepTuru))
            ->where('durum', self::DURUM_BEKLIYOR)
            ->latest('id')
            ->first();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function bekleyenTalepler(int $firmaId, int $limit = 10): array
    {
        if ($firmaId < 1) {
            return [];
        }

        return AlacakPlanOnayTalebi::query()
            ->with(['plan.cari', 'talepEden'])
            ->where('firma_id', $firmaId)
            ->where('durum', self::DURUM_BEKLIYOR)
            ->latest('created_at')
            ->latest('id')
            ->limit(max(1, $limit))
            ->get()
            ->map(fn (AlacakPlanOnayTalebi $talep): array => [
                'id' => (int) $talep->getKey(),
                'plan_id' => (int) $talep->alacak_plan_id,
                'talep_turu' => (string) $talep->talep_turu,
                'talep_turu_etiketi' => $this->talepTuruEtiketi((string) $talep->talep_turu),
                'cari_ad' => (string) ($talep->plan?->cari?->ad ?? '-'),
                'cari_kod' => (string) ($talep->plan?->cari?->kod ?? ''),
                'risk_tutari' => (string) $talep->risk_tutari,
                'para_birimi' => strtoupper((string) ($talep->para_birimi ?: 'TRY')),
                'gerekce' => (string) ($talep->gerekce ?? ''),
                'talep_eden' => (string) ($talep->talepEden?->name ?? '-'),
                'created_at' => $talep->created_at?->format('d.m.Y H:i') ?? '',
            ])
            ->all();
    }

    public function onayla(AlacakPlanOnayTalebi $talep, ?int $kararVerenId = null, ?string $kararNotu = null): AlacakPlanOnayTalebi
    {
        return DB::transaction(function () use ($talep, $kararVerenId, $kararNotu): AlacakPlanOnayTalebi {
            $talep = AlacakPlanOnayTalebi::query()
                ->whereKey($talep->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ((string) $talep->durum !== self::DURUM_BEKLIYOR) {
                throw new IsKuraliIstisnasi('Sadece bekleyen onay talepleri onaylanabilir.');
            }

            $plan = AlacakPlani::query()
                ->where('firma_id', (int) $talep->firma_id)
                ->whereKey((int) $talep->alacak_plan_id)
                ->firstOrFail();

            $istenenVeri = (array) ($talep->istenen_veri ?? []);
            if ((string) $talep->talep_turu === self::TUR_IPTAL) {
                $this->alacakPlanServisi->planiIptalEt($plan, 'Onayli vade plan iptali: '.(string) ($talep->gerekce ?? ''));
            } elseif ((string) $talep->talep_turu === self::TUR_REVIZYON) {
                $this->alacakPlanServisi->planiRevizeEt($plan, $istenenVeri + [
                    'olusturan_id' => (int) ($talep->talep_eden_id ?? 0) ?: $kararVerenId,
                ]);
            } else {
                throw new IsKuraliIstisnasi('Gecersiz onay talebi turu.');
            }

            $talep->update([
                'durum' => self::DURUM_ONAYLANDI,
                'karar_veren_id' => $kararVerenId,
                'karar_notu' => trim((string) ($kararNotu ?? '')),
                'karar_tarihi' => now(),
            ]);

            return $talep->fresh(['plan', 'kararVeren']) ?? $talep;
        });
    }

    public function reddet(AlacakPlanOnayTalebi $talep, ?int $kararVerenId = null, ?string $kararNotu = null): AlacakPlanOnayTalebi
    {
        return DB::transaction(function () use ($talep, $kararVerenId, $kararNotu): AlacakPlanOnayTalebi {
            $talep = AlacakPlanOnayTalebi::query()
                ->whereKey($talep->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ((string) $talep->durum !== self::DURUM_BEKLIYOR) {
                throw new IsKuraliIstisnasi('Sadece bekleyen onay talepleri reddedilebilir.');
            }

            $talep->update([
                'durum' => self::DURUM_REDDEDILDI,
                'karar_veren_id' => $kararVerenId,
                'karar_notu' => trim((string) ($kararNotu ?? '')),
                'karar_tarihi' => now(),
            ]);

            return $talep->fresh(['plan', 'kararVeren']) ?? $talep;
        });
    }

    public function talepTuruEtiketi(string $talepTuru): string
    {
        return match ($talepTuru) {
            self::TUR_IPTAL => 'Plan iptal',
            self::TUR_REVIZYON => 'Plan revizyon',
            default => ucfirst($talepTuru),
        };
    }

    private function talepTuru(string $talepTuru): string
    {
        $talepTuru = strtolower(trim($talepTuru));
        if (! in_array($talepTuru, [self::TUR_IPTAL, self::TUR_REVIZYON], true)) {
            throw new IsKuraliIstisnasi('Gecersiz onay talebi turu.');
        }

        return $talepTuru;
    }

    private function riskTutari(AlacakPlani $plan): string
    {
        return number_format(max(0, (float) ($plan->kalan_tutar ?? $plan->planlanan_tutar ?? 0)), 2, '.', '');
    }

    /**
     * @return array<string,mixed>
     */
    private function planOzeti(AlacakPlani $plan): array
    {
        return [
            'plan_id' => (int) $plan->getKey(),
            'cari_id' => (int) $plan->cari_id,
            'kaynak_turu' => (string) $plan->kaynak_turu,
            'kaynak_id' => $plan->kaynak_id ? (int) $plan->kaynak_id : null,
            'plan_turu' => (string) $plan->plan_turu,
            'toplam_tutar' => (string) $plan->toplam_tutar,
            'planlanan_tutar' => (string) $plan->planlanan_tutar,
            'odenen_tutar' => (string) $plan->odenen_tutar,
            'kalan_tutar' => (string) $plan->kalan_tutar,
            'para_birimi' => strtoupper((string) ($plan->para_birimi ?: 'TRY')),
            'durum' => (string) $plan->durum,
            'son_vade_tarihi' => $plan->son_vade_tarihi?->toDateString(),
        ];
    }
}
