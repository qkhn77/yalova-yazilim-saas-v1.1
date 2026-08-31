<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\AlacakPlanTaksiti;
use App\Models\Muhasebe\AlacakPlani;
use App\Models\Muhasebe\AlacakTahsilatEslesmesi;
use App\Models\Muhasebe\FinansHareketi;
use App\Muhasebe\Enumlar\FinansHareketDurumu;
use App\Muhasebe\Enumlar\FinansHareketTuru;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AlacakTahsilatServisi
{
    public function finansTahsilatiniPlanlaraDagit(FinansHareketi $finans): void
    {
        $tur = $finans->tur instanceof FinansHareketTuru
            ? $finans->tur
            : FinansHareketTuru::tryFrom((string) $finans->tur);
        $durum = $finans->durum instanceof FinansHareketDurumu
            ? $finans->durum
            : FinansHareketDurumu::tryFrom((string) $finans->durum);

        if ($tur !== FinansHareketTuru::Tahsilat || $durum !== FinansHareketDurumu::Aktif) {
            return;
        }
        if ((int) ($finans->cari_id ?? 0) < 1 || (float) $finans->tutar <= 0) {
            return;
        }
        if (AlacakTahsilatEslesmesi::query()->where('finans_hareketi_id', (int) $finans->getKey())->exists()) {
            return;
        }
        if ($this->pesinatTahsilatiMi($finans)) {
            return;
        }

        DB::transaction(function () use ($finans): void {
            // E-ticaret/provider callback'leri oturumsuz çalışabilir. Finans
            // nesnesi zaten firma doğrulamasından geçmiş olarak metoda geldiği
            // için burada aktif kullanıcı tenant scope'una değil, doğrudan
            // kilitli finans kaydının kimliğine bağlanmalıyız.
            $finans = FinansHareketi::query()
                ->withoutGlobalScopes()
                ->lockForUpdate()
                ->whereKey($finans->getKey())
                ->firstOrFail();
            if (AlacakTahsilatEslesmesi::query()
                ->where('finans_hareketi_id', (int) $finans->getKey())
                ->exists()) {
                return;
            }

            $kalan = number_format((float) $finans->tutar, 2, '.', '');
            $taksitSorgusu = AlacakPlanTaksiti::query()
                ->where('firma_id', (int) $finans->firma_id)
                ->where('cari_id', (int) $finans->cari_id)
                ->whereNotIn('durum', ['odendi', 'iptal'])
                ->where('kalan_tutar', '>', 0)
                ->whereHas('plan', fn ($q) => $q
                    ->where('para_birimi', strtoupper((string) ($finans->para_birimi ?: 'TRY')))
                    ->whereIn('durum', ['aktif', 'kismi_odendi', 'gecikti']))
                ->orderBy('vade_tarihi')
                ->orderBy('id')
                ->lockForUpdate();

            $referansTaksitId = (string) $finans->referans_turu === 'alacak_plan_taksiti'
                ? (int) ($finans->referans_id ?? 0)
                : 0;
            $hedefTaksit = $referansTaksitId > 0
                ? (clone $taksitSorgusu)->whereKey($referansTaksitId)->first()
                : null;
            if ($hedefTaksit) {
                $hedefPlanId = (int) $hedefTaksit->alacak_plan_id;
                $taksitler = collect([$hedefTaksit])
                    ->merge((clone $taksitSorgusu)
                        ->where('alacak_plan_id', $hedefPlanId)
                        ->where('id', '!=', $referansTaksitId)
                        ->get())
                    ->merge((clone $taksitSorgusu)
                        ->where('alacak_plan_id', '!=', $hedefPlanId)
                        ->get());
            } else {
                $taksitler = $taksitSorgusu->get();
            }

            foreach ($taksitler as $taksit) {
                if (bccomp($kalan, '0.00', 2) <= 0) {
                    break;
                }

                $taksitKalan = number_format((float) $taksit->kalan_tutar, 2, '.', '');
                if (bccomp($taksitKalan, '0.00', 2) <= 0) {
                    continue;
                }

                $uygulanacak = bccomp($kalan, $taksitKalan, 2) === 1 ? $taksitKalan : $kalan;
                $yeniOdenen = bcadd((string) $taksit->odenen_tutar, $uygulanacak, 2);
                $yeniKalan = bcsub((string) $taksit->tutar, $yeniOdenen, 2);
                if (bccomp($yeniKalan, '0.00', 2) < 0) {
                    $yeniKalan = '0.00';
                }

                $taksit->update([
                    'odenen_tutar' => $yeniOdenen,
                    'kalan_tutar' => $yeniKalan,
                    'son_tahsilat_tarihi' => $finans->tarih ?? now(),
                    'durum' => $this->taksitDurumu($yeniOdenen, $yeniKalan, $taksit->vade_tarihi),
                ]);
                $taksit->refresh();

                app(AlacakTakipNotuServisi::class)->odemeSozuDurumunuGuncelle($taksit, $uygulanacak, $finans->tarih ?? now());

                AlacakTahsilatEslesmesi::query()->create([
                    'firma_id' => (int) $finans->firma_id,
                    'alacak_plan_id' => (int) $taksit->alacak_plan_id,
                    'alacak_plan_taksiti_id' => (int) $taksit->getKey(),
                    'finans_hareketi_id' => (int) $finans->getKey(),
                    'tutar' => $uygulanacak,
                    'para_birimi' => strtoupper((string) ($finans->para_birimi ?: $taksit->para_birimi ?: 'TRY')),
                    'tarih' => $finans->tarih ?? now(),
                ]);

                $this->planOzetiniGuncelle((int) $taksit->alacak_plan_id);
                $kalan = bcsub($kalan, $uygulanacak, 2);
            }
        });
    }

    private function pesinatTahsilatiMi(FinansHareketi $finans): bool
    {
        $referansTuru = strtolower(trim((string) ($finans->referans_turu ?? '')));
        if (! in_array($referansTuru, ['barkodlu_satis', 'teknik_servis'], true)) {
            return false;
        }

        $aciklama = strtolower((string) ($finans->aciklama ?? ''));

        return str_contains($aciklama, 'pesinat') || str_contains($aciklama, 'peşinat');
    }

    public function planOzetiniGuncelle(int $planId): void
    {
        $plan = AlacakPlani::query()->whereKey($planId)->first();
        if (! $plan) {
            return;
        }

        $taksitOdenen = (string) AlacakPlanTaksiti::query()
            ->where('alacak_plan_id', $planId)
            ->sum('odenen_tutar');
        $kalan = (string) AlacakPlanTaksiti::query()
            ->where('alacak_plan_id', $planId)
            ->sum('kalan_tutar');
        $odenen = bcadd((string) ($plan->pesinat_tutari ?? '0'), number_format((float) $taksitOdenen, 2, '.', ''), 2);

        $durum = 'aktif';
        if (bccomp(number_format((float) $kalan, 2, '.', ''), '0.00', 2) <= 0) {
            $durum = 'odendi';
        } elseif (bccomp(number_format((float) $odenen, 2, '.', ''), '0.00', 2) === 1) {
            $durum = 'kismi_odendi';
        }

        $gecikenVar = AlacakPlanTaksiti::query()
            ->where('alacak_plan_id', $planId)
            ->where('kalan_tutar', '>', 0)
            ->whereDate('vade_tarihi', '<', Carbon::today())
            ->exists();
        if ($gecikenVar && $durum !== 'odendi') {
            $durum = 'gecikti';
        }

        $plan->update([
            'odenen_tutar' => number_format((float) $odenen, 2, '.', ''),
            'kalan_tutar' => number_format((float) $kalan, 2, '.', ''),
            'durum' => $durum,
        ]);
    }

    public function finansTahsilatiTersleninceDagitimiGeriAl(FinansHareketi $finans): void
    {
        $eslesmeler = AlacakTahsilatEslesmesi::query()
            ->where('finans_hareketi_id', (int) $finans->getKey())
            ->get();

        if ($eslesmeler->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($eslesmeler): void {
            $planIds = [];

            foreach ($eslesmeler as $eslesme) {
                $taksit = AlacakPlanTaksiti::query()
                    ->whereKey((int) $eslesme->alacak_plan_taksiti_id)
                    ->lockForUpdate()
                    ->first();
                if (! $taksit) {
                    continue;
                }

                $geriAlinan = number_format((float) $eslesme->tutar, 2, '.', '');
                $odenen = bcsub((string) $taksit->odenen_tutar, $geriAlinan, 2);
                if (bccomp($odenen, '0.00', 2) < 0) {
                    $odenen = '0.00';
                }

                $kalan = bcsub((string) $taksit->tutar, $odenen, 2);
                if (bccomp($kalan, '0.00', 2) < 0) {
                    $kalan = '0.00';
                }

                $taksit->update([
                    'odenen_tutar' => $odenen,
                    'kalan_tutar' => $kalan,
                    'durum' => $this->taksitDurumu($odenen, $kalan, $taksit->vade_tarihi),
                ]);

                $planIds[(int) $taksit->alacak_plan_id] = true;
            }

            AlacakTahsilatEslesmesi::query()
                ->whereIn('id', $eslesmeler->pluck('id')->all())
                ->delete();

            foreach (array_keys($planIds) as $planId) {
                $this->planOzetiniGuncelle((int) $planId);
            }
        });
    }

    /**
     * Mevcut veresiye tahsilatını iptal edip, önceden oluşturulmuş yeni aktif
     * tahsilatı aynı FIFO kurallarıyla yeniden dağıtır.
     */
    public function finansTahsilatiniIptalEtVeDuzelt(FinansHareketi $eskiFinans, FinansHareketi $yeniFinans, ?string $neden = null): void
    {
        DB::transaction(function () use ($eskiFinans, $yeniFinans, $neden): void {
            $eski = FinansHareketi::query()->lockForUpdate()->findOrFail($eskiFinans->getKey());
            $yeni = FinansHareketi::query()->lockForUpdate()->findOrFail($yeniFinans->getKey());

            if ((int) $eski->firma_id !== (int) $yeni->firma_id || (int) $eski->cari_id !== (int) $yeni->cari_id) {
                throw new \App\Muhasebe\Exceptions\IsKuraliIstisnasi('Veresiye düzeltmesi aynı firma ve cari içinde yapılmalıdır.');
            }

            if ((string) ($eski->durum->value ?? $eski->durum) !== FinansHareketDurumu::Aktif->value) {
                throw new \App\Muhasebe\Exceptions\IsKuraliIstisnasi('Eski veresiye tahsilatı aktif değil.');
            }

            app(FinansHareketServisi::class)->tersKayitOlustur($eski, $neden ?: 'Veresiye tahsilatı düzeltmesi');
            $yeni->update(['duzeltme_kaynagi_id' => (int) $eski->getKey()]);
            $this->finansTahsilatiniPlanlaraDagit($yeni->refresh());
        });
    }

    private function taksitDurumu(string $odenen, string $kalan, mixed $vadeTarihi): string
    {
        if (bccomp($kalan, '0.00', 2) <= 0) {
            return 'odendi';
        }
        if ($vadeTarihi && Carbon::parse($vadeTarihi)->lt(Carbon::today())) {
            return 'gecikti';
        }
        if (bccomp($odenen, '0.00', 2) === 1) {
            return 'kismi_odendi';
        }

        return 'bekliyor';
    }
}
