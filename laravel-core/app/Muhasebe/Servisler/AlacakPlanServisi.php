<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\AlacakPlanTaksiti;
use App\Models\Muhasebe\AlacakPlani;
use App\Models\Muhasebe\AlacakPlanRevizyonu;
use App\Models\Muhasebe\BarkodluSatis;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\CariHareketi;
use App\Models\TeknikServis\TeknikServisKaydi;
use App\Muhasebe\Enumlar\CariHareketBelgeTuru;
use App\Muhasebe\Enumlar\CariHareketDurumu;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AlacakPlanServisi
{
    public function __construct(
        private readonly CariHareketServisi $cariHareketServisi,
    ) {}

    /**
     * @param array<string,mixed> $veri
     */
    public function barkodluSatisIcinOlustur(BarkodluSatis $satis, array $veri): ?AlacakPlani
    {
        $odemeTipi = strtolower(trim((string) ($satis->odeme_tipi ?? '')));
        if (! in_array($odemeTipi, ['veresiye', 'taksitli'], true)) {
            return null;
        }

        $taksitSayisi = $odemeTipi === 'taksitli'
            ? max(1, (int) ($veri['taksit_sayisi'] ?? 1))
            : 1;

        $ilkVade = $veri['vade_tarihi'] ?? $veri['ilk_vade_tarihi'] ?? null;
        if (! $ilkVade) {
            $ilkVade = $this->varsayilanVadeTarihi((int) $satis->cari_id, (string) $satis->satis_tarihi);
        }

        return $this->olustur((int) $satis->firma_id, [
            'cari_id' => (int) $satis->cari_id,
            'kaynak_turu' => 'barkodlu_satis',
            'kaynak_id' => (int) $satis->getKey(),
            'plan_turu' => $odemeTipi === 'taksitli' ? 'taksit' : 'veresiye',
            'toplam_tutar' => (string) $satis->genel_toplam,
            'pesinat_tutari' => (string) ($veri['pesinat_tutari'] ?? '0'),
            'vade_farki_uygula' => (bool) ($veri['vade_farki_uygula'] ?? $veri['faiz_uygula'] ?? false),
            'vade_farki_tipi' => (string) (($veri['vade_farki_tipi'] ?? null) ?: ($taksitSayisi > 1 ? 'aylik' : 'tek_seferlik')),
            'vade_farki_orani' => (string) ($veri['vade_farki_orani'] ?? $veri['faiz_orani'] ?? '0'),
            'vade_farki_tutari' => (string) ($veri['vade_farki_tutari'] ?? $veri['faiz_tutari'] ?? '0'),
            'para_birimi' => strtoupper((string) ($satis->para_birimi ?: 'TRY')),
            'baslangic_tarihi' => Carbon::parse((string) $satis->satis_tarihi)->toDateString(),
            'ilk_vade_tarihi' => $ilkVade,
            'taksit_sayisi' => $taksitSayisi,
            'taksit_araligi_gun' => (int) ($veri['taksit_araligi_gun'] ?? 30),
            'aciklama' => 'Barkodlu satis #'.$satis->satis_no,
            'olusturan_id' => $satis->olusturan_id,
        ]);
    }

    /**
     * @param array<string,mixed> $veri
     */
    public function teknikServisIcinOlustur(TeknikServisKaydi $servis, array $veri): AlacakPlani
    {
        $taksitSayisi = strtolower((string) ($veri['plan_turu'] ?? 'veresiye')) === 'taksit'
            ? max(1, (int) ($veri['taksit_sayisi'] ?? 1))
            : 1;

        return $this->olustur((int) $servis->firma_id, [
            'cari_id' => (int) $servis->cari_id,
            'kaynak_turu' => 'teknik_servis',
            'kaynak_id' => (int) $servis->getKey(),
            'plan_turu' => $taksitSayisi > 1 ? 'taksit' : 'veresiye',
            'toplam_tutar' => (string) ($veri['toplam_tutar'] ?? $servis->toplam_tutar ?? '0'),
            'pesinat_tutari' => (string) ($veri['pesinat_tutari'] ?? $servis->odenen_tutar ?? '0'),
            'vade_farki_uygula' => (bool) ($veri['vade_farki_uygula'] ?? $veri['faiz_uygula'] ?? false),
            'vade_farki_tipi' => (string) (($veri['vade_farki_tipi'] ?? null) ?: ($taksitSayisi > 1 ? 'aylik' : 'tek_seferlik')),
            'vade_farki_orani' => (string) ($veri['vade_farki_orani'] ?? $veri['faiz_orani'] ?? '0'),
            'vade_farki_tutari' => (string) ($veri['vade_farki_tutari'] ?? $veri['faiz_tutari'] ?? '0'),
            'para_birimi' => strtoupper((string) ($veri['para_birimi'] ?? $servis->cari?->para_birimi ?? 'TRY')),
            'baslangic_tarihi' => now()->toDateString(),
            'ilk_vade_tarihi' => $veri['ilk_vade_tarihi'] ?? $veri['vade_tarihi'] ?? now()->addDays(30)->toDateString(),
            'taksit_sayisi' => $taksitSayisi,
            'taksit_araligi_gun' => (int) ($veri['taksit_araligi_gun'] ?? 30),
            'aciklama' => 'Teknik servis #'.(int) $servis->getKey(),
            'olusturan_id' => $servis->olusturan_id,
            'cari_hareket_uret' => false,
        ]);
    }

    /**
     * @param array<string,mixed> $veri
     */
    public function olustur(int $firmaId, array $veri): AlacakPlani
    {
        $cariId = (int) ($veri['cari_id'] ?? 0);
        if ($cariId < 1) {
            throw new IsKuraliIstisnasi('Alacak plani icin cari zorunludur.');
        }

        $cari = Cari::query()
            ->where('firma_id', $firmaId)
            ->whereKey($cariId)
            ->first();
        if (! $cari) {
            throw new IsKuraliIstisnasi('Alacak plani carisi firmaya ait degil.');
        }

        $kaynakTuru = strtolower(trim((string) ($veri['kaynak_turu'] ?? 'manuel')));
        $kaynakId = (int) ($veri['kaynak_id'] ?? 0);
        if ($kaynakId > 0) {
            $mevcut = AlacakPlani::query()
                ->where('firma_id', $firmaId)
                ->where('kaynak_turu', $kaynakTuru)
                ->where('kaynak_id', $kaynakId)
                ->whereIn('durum', ['aktif', 'kismi_odendi', 'gecikti'])
                ->first();
            if ($mevcut) {
                return $mevcut;
            }
        }

        $anaTutar = $this->decimal((string) ($veri['toplam_tutar'] ?? '0'));
        $pesinat = $this->decimal((string) ($veri['pesinat_tutari'] ?? '0'));
        if (bccomp($anaTutar, '0.00', 2) <= 0) {
            throw new IsKuraliIstisnasi('Alacak plani tutari sifirdan buyuk olmalidir.');
        }
        if (bccomp($pesinat, $anaTutar, 2) === 1) {
            throw new IsKuraliIstisnasi('Pesinat toplam tutardan buyuk olamaz.');
        }

        $taksitSayisi = max(1, (int) ($veri['taksit_sayisi'] ?? 1));
        $aralikGun = max(1, (int) ($veri['taksit_araligi_gun'] ?? 30));
        $ilkVade = Carbon::parse((string) ($veri['ilk_vade_tarihi'] ?? $veri['vade_tarihi'] ?? now()->toDateString()))->startOfDay();
        $baslangic = Carbon::parse((string) ($veri['baslangic_tarihi'] ?? now()->toDateString()))->startOfDay();
        $vadeFarkiTipi = $this->vadeFarkiTipi((string) (($veri['vade_farki_tipi'] ?? null) ?: ($taksitSayisi > 1 ? 'aylik' : 'tek_seferlik')));
        $vadeFarkiOrani = max(0, (float) ($veri['vade_farki_orani'] ?? $veri['faiz_orani'] ?? 0));
        $vadeFarkiTutari = '0.00';
        if ((bool) ($veri['vade_farki_uygula'] ?? $veri['faiz_uygula'] ?? false)) {
            $vadeFarkiTutari = $this->decimal((string) ($veri['vade_farki_tutari'] ?? $veri['faiz_tutari'] ?? '0'));
            if (bccomp($vadeFarkiTutari, '0.00', 2) <= 0 && $vadeFarkiOrani > 0) {
                $vadeFarkiTutari = $this->vadeFarkiTutariHesapla(
                    bcsub($anaTutar, $pesinat, 2),
                    $vadeFarkiOrani,
                    $vadeFarkiTipi,
                    $baslangic,
                    $ilkVade,
                    $taksitSayisi,
                    $aralikGun,
                );
            }
        } else {
            $vadeFarkiOrani = 0.0;
        }

        $toplamTutar = bcadd($anaTutar, $vadeFarkiTutari, 2);
        $planlanan = bcsub($toplamTutar, $pesinat, 2);
        if (bccomp($planlanan, '0.00', 2) <= 0) {
            throw new IsKuraliIstisnasi('Planlanacak kalan tutar sifirdan buyuk olmalidir.');
        }

        $paraBirimi = strtoupper((string) ($veri['para_birimi'] ?? $cari->para_birimi ?? 'TRY'));
        $cariParaBirimi = strtoupper((string) ($cari->para_birimi ?: 'TRY'));
        if ($paraBirimi !== $cariParaBirimi) {
            throw new IsKuraliIstisnasi('Alacak plani para birimi cari para birimi ile ayni olmalidir.');
        }
        $planTuru = strtolower(trim((string) ($veri['plan_turu'] ?? ($taksitSayisi > 1 ? 'taksit' : 'veresiye'))));

        return DB::transaction(function () use ($firmaId, $veri, $cariId, $kaynakTuru, $kaynakId, $toplamTutar, $pesinat, $vadeFarkiTipi, $vadeFarkiOrani, $vadeFarkiTutari, $planlanan, $paraBirimi, $planTuru, $taksitSayisi, $aralikGun, $ilkVade): AlacakPlani {
            $sonVade = $ilkVade->copy()->addDays($aralikGun * ($taksitSayisi - 1));
            $baslangic = Carbon::parse((string) ($veri['baslangic_tarihi'] ?? now()->toDateString()))->startOfDay();
            $anaPlanlanan = bcsub(bcsub($toplamTutar, $vadeFarkiTutari, 2), $pesinat, 2);
            if (bccomp($anaPlanlanan, '0.00', 2) < 0) {
                $anaPlanlanan = '0.00';
            }
            $taksitTutarlari = $this->planTaksitTutarlari(
                $planlanan,
                $anaPlanlanan,
                $vadeFarkiTipi,
                $vadeFarkiOrani,
                $baslangic,
                $ilkVade,
                $taksitSayisi,
                $aralikGun,
            );

            $plan = AlacakPlani::query()->create([
                'firma_id' => $firmaId,
                'cari_id' => $cariId,
                'kaynak_turu' => $kaynakTuru,
                'kaynak_id' => $kaynakId > 0 ? $kaynakId : null,
                'plan_turu' => $planTuru,
                'toplam_tutar' => $toplamTutar,
                'pesinat_tutari' => $pesinat,
                'vade_farki_tipi' => $vadeFarkiTipi,
                'vade_farki_orani' => number_format($vadeFarkiOrani, 4, '.', ''),
                'vade_farki_tutari' => $vadeFarkiTutari,
                'faiz_orani' => number_format($vadeFarkiOrani, 4, '.', ''),
                'faiz_tutari' => $vadeFarkiTutari,
                'planlanan_tutar' => $planlanan,
                'odenen_tutar' => $pesinat,
                'kalan_tutar' => $planlanan,
                'para_birimi' => $paraBirimi,
                'baslangic_tarihi' => $veri['baslangic_tarihi'] ?? now()->toDateString(),
                'son_vade_tarihi' => $sonVade->toDateString(),
                'durum' => 'aktif',
                'aciklama' => $veri['aciklama'] ?? null,
                'olusturan_id' => $veri['olusturan_id'] ?? null,
            ]);
            $plan->update(['islem_no' => $this->islemNoUret($plan)]);

            foreach ($taksitTutarlari as $index => $tutar) {
                $vade = $ilkVade->copy()->addDays($aralikGun * $index);
                $taksit = AlacakPlanTaksiti::query()->create([
                    'firma_id' => $firmaId,
                    'alacak_plan_id' => (int) $plan->getKey(),
                    'cari_id' => $cariId,
                    'sira_no' => $index + 1,
                    'vade_tarihi' => $vade->toDateString(),
                    'tutar' => $tutar,
                    'odenen_tutar' => '0.00',
                    'kalan_tutar' => $tutar,
                    'durum' => $vade->isPast() && ! $vade->isToday() ? 'gecikti' : 'bekliyor',
                ]);

                if ((bool) ($veri['cari_hareket_uret'] ?? true)) {
                    $cariHareket = $this->cariHareketServisi->kayitOlustur($firmaId, [
                        'cari_id' => $cariId,
                        'belge_turu' => CariHareketBelgeTuru::Satis,
                        'belge_id' => (int) $taksit->getKey(),
                        'islem_tarihi' => $veri['baslangic_tarihi'] ?? now(),
                        'vade_tarihi' => $vade->toDateString(),
                        'borc' => '0.00',
                        'alacak' => $tutar,
                        'para_birimi' => $paraBirimi,
                        'aciklama' => ($veri['aciklama'] ?? 'Alacak plani').' / Taksit '.($index + 1),
                    ]);

                    $taksit->update(['cari_hareket_id' => (int) $cariHareket->getKey()]);
                }
            }

            return $plan->fresh(['cari', 'taksitler']) ?? $plan;
        });
    }

    public function kaynakPlaniniIptalEt(int $firmaId, string $kaynakTuru, int $kaynakId, ?string $aciklama = null): ?AlacakPlani
    {
        $kaynakTuru = strtolower(trim($kaynakTuru));
        if ($kaynakTuru === '' || $kaynakId < 1) {
            return null;
        }

        return DB::transaction(function () use ($firmaId, $kaynakTuru, $kaynakId, $aciklama): ?AlacakPlani {
            $plan = AlacakPlani::query()
                ->where('firma_id', $firmaId)
                ->where('kaynak_turu', $kaynakTuru)
                ->where('kaynak_id', $kaynakId)
                ->whereIn('durum', ['aktif', 'kismi_odendi', 'gecikti'])
                ->first();

            if (! $plan) {
                return null;
            }

            return $this->planiIptalEt($plan, $aciklama);
        });
    }

    private function islemNoUret(AlacakPlani $plan): string
    {
        $tarih = $plan->created_at
            ? $plan->created_at->format('Ymd')
            : now()->format('Ymd');

        return sprintf('VP-%s-%06d', $tarih, (int) $plan->getKey());
    }

    public function planiIptalEt(AlacakPlani $plan, ?string $aciklama = null): AlacakPlani
    {
        return DB::transaction(function () use ($plan, $aciklama): AlacakPlani {
            $plan = AlacakPlani::query()
                ->whereKey($plan->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array((string) $plan->durum, ['aktif', 'kismi_odendi', 'gecikti'], true)) {
                return $plan;
            }

            $tahsilatVar = $plan->tahsilatEslesmeleri()->exists()
                || $plan->taksitler()->where('odenen_tutar', '>', 0)->exists()
                || bccomp((string) ($plan->pesinat_tutari ?? '0'), '0.00', 2) === 1
                || $plan->revizyonlar()->where('revizyon_turu', 'erken_kapama_indirimi')->exists();

            if ($tahsilatVar) {
                throw new IsKuraliIstisnasi('Bu vade planinda tahsilat var. Iptalden once vade tahsilatlarini tersleyin.');
            }

            $taksitler = $plan->taksitler()
                ->lockForUpdate()
                ->orderBy('sira_no')
                ->get();

            foreach ($taksitler as $taksit) {
                $cariHareketId = (int) ($taksit->cari_hareket_id ?? 0);
                if ($cariHareketId > 0) {
                    $hareket = CariHareketi::query()
                        ->where('firma_id', (int) $plan->firma_id)
                        ->whereKey($cariHareketId)
                        ->where('durum', CariHareketDurumu::Aktif)
                        ->first();

                    if ($hareket) {
                        $this->cariHareketServisi->kaydiIptalEt($hareket, $aciklama ?? 'Alacak plani iptal');
                    }
                }

                $taksit->update([
                    'kalan_tutar' => '0.00',
                    'durum' => 'iptal',
                ]);
            }

            $plan->update([
                'kalan_tutar' => '0.00',
                'durum' => 'iptal',
                'aciklama' => trim((string) (($plan->aciklama ?? '').($aciklama ? ' | '.$aciklama : ''))) ?: $plan->aciklama,
            ]);

            return $plan->fresh(['taksitler']) ?? $plan;
        });
    }

    /**
     * @param array<string,mixed> $veri
     */
    public function planiRevizeEt(AlacakPlani $plan, array $veri): AlacakPlani
    {
        return DB::transaction(function () use ($plan, $veri): AlacakPlani {
            $plan = AlacakPlani::query()
                ->with(['taksitler' => fn ($query) => $query->orderBy('sira_no')])
                ->whereKey($plan->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array((string) $plan->durum, ['aktif', 'kismi_odendi', 'gecikti'], true)) {
                throw new IsKuraliIstisnasi('Sadece aktif alacak planlari revize edilebilir.');
            }

            $onceki = $this->planRevizyonOzeti($plan);
            $revizyonTuru = (string) ($veri['revizyon_turu'] ?? 'vade_ertele');

            match ($revizyonTuru) {
                'vade_ertele' => $this->acikTaksitleriErtele($plan, max(1, (int) ($veri['erteleme_gun'] ?? 1))),
                'taksit_vade_degistir' => $this->tekTaksitVadesiniDegistir($plan, (int) ($veri['taksit_id'] ?? 0), (string) ($veri['yeni_vade_tarihi'] ?? '')),
                'taksit_duzenle' => $this->taksitDuzenle($plan, $veri),
                'kalan_yeniden_taksitlendir', 'kismi_yapilandir' => $this->kalanTutariYenidenTaksitlendir($plan, $veri),
                'vade_farki_ekle' => $this->vadeFarkiTaksitiEkle($plan, $veri),
                'erken_kapama_indirimi' => $this->erkenKapamaIndirimiUygula($plan, $veri),
                default => throw new IsKuraliIstisnasi('Gecersiz plan revizyon turu.'),
            };

            $plan = $plan->fresh(['taksitler' => fn ($query) => $query->orderBy('sira_no')]) ?? $plan;
            $this->planSonVadesiniGuncelle($plan);
            $plan = $plan->fresh(['taksitler' => fn ($query) => $query->orderBy('sira_no')]) ?? $plan;

            AlacakPlanRevizyonu::query()->create([
                'firma_id' => (int) $plan->firma_id,
                'alacak_plan_id' => (int) $plan->getKey(),
                'revizyon_turu' => $revizyonTuru,
                'onceki_veri' => $onceki,
                'sonraki_veri' => $this->planRevizyonOzeti($plan),
                'aciklama' => trim((string) ($veri['aciklama'] ?? '')),
                'olusturan_id' => (int) ($veri['olusturan_id'] ?? auth()->id() ?? 0) ?: null,
            ]);

            return $plan->fresh(['taksitler', 'revizyonlar']) ?? $plan;
        });
    }

    private function varsayilanVadeTarihi(int $cariId, string $satisTarihi): string
    {
        $vadeGunu = (int) (Cari::query()->whereKey($cariId)->value('vade_gunu') ?? 0);

        return Carbon::parse($satisTarihi)->addDays(max(0, $vadeGunu))->toDateString();
    }

    /**
     * @return array<int, string>
     */
    private function taksitTutarlari(string $toplam, int $adet): array
    {
        $adet = max(1, $adet);
        $baz = bcdiv($toplam, (string) $adet, 2);
        $tutarlar = array_fill(0, $adet, $baz);
        $dagitilan = bcmul($baz, (string) $adet, 2);
        $fark = bcsub($toplam, $dagitilan, 2);
        $tutarlar[$adet - 1] = bcadd($tutarlar[$adet - 1], $fark, 2);

        return $tutarlar;
    }

    /**
     * @return array<int, string>
     */
    private function planTaksitTutarlari(
        string $planlanan,
        string $anaPlanlanan,
        string $vadeFarkiTipi,
        float $vadeFarkiOrani,
        Carbon $baslangic,
        Carbon $ilkVade,
        int $taksitSayisi,
        int $aralikGun,
    ): array {
        $taksitSayisi = max(1, $taksitSayisi);

        if (! in_array($vadeFarkiTipi, ['aylik', 'yillik'], true) || $vadeFarkiOrani <= 0) {
            return $this->taksitTutarlari($planlanan, $taksitSayisi);
        }

        $anaTaksitler = $this->taksitTutarlari($anaPlanlanan, $taksitSayisi);
        $tutarlar = [];

        foreach ($anaTaksitler as $index => $anaTaksit) {
            $vade = $ilkVade->copy()->addDays($aralikGun * $index);
            $gun = max(0, $baslangic->diffInDays($vade, false));
            $donem = $vadeFarkiTipi === 'aylik' ? ($gun / 30) : ($gun / 365);
            $faiz = round(((float) $anaTaksit) * ($vadeFarkiOrani / 100) * $donem, 2);
            $tutarlar[] = number_format(((float) $anaTaksit) + $faiz, 2, '.', '');
        }

        $dagitilan = number_format(array_sum(array_map('floatval', $tutarlar)), 2, '.', '');
        $fark = bcsub($planlanan, $dagitilan, 2);
        $sonIndex = count($tutarlar) - 1;
        $tutarlar[$sonIndex] = bcadd($tutarlar[$sonIndex], $fark, 2);

        return $tutarlar;
    }

    private function vadeFarkiTipi(string $tip): string
    {
        return in_array($tip, ['tek_seferlik', 'aylik', 'yillik'], true) ? $tip : 'tek_seferlik';
    }

    private function vadeFarkiTutariHesapla(
        string $anapara,
        float $oran,
        string $tip,
        Carbon $baslangic,
        Carbon $ilkVade,
        int $taksitSayisi,
        int $aralikGun,
    ): string {
        $anaparaFloat = max(0, (float) $anapara);
        if ($anaparaFloat <= 0 || $oran <= 0) {
            return '0.00';
        }

        if ($tip === 'tek_seferlik') {
            return number_format(round($anaparaFloat * ($oran / 100), 2), 2, '.', '');
        }

        $taksitler = $this->taksitTutarlari(number_format($anaparaFloat, 2, '.', ''), max(1, $taksitSayisi));
        $toplam = 0.0;
        foreach ($taksitler as $index => $tutar) {
            $vade = $ilkVade->copy()->addDays($aralikGun * $index);
            $gun = max(0, $baslangic->diffInDays($vade, false));
            $donem = $tip === 'aylik' ? ($gun / 30) : ($gun / 365);
            $toplam += ((float) $tutar) * ($oran / 100) * $donem;
        }

        return number_format(round($toplam, 2), 2, '.', '');
    }

    private function decimal(string $tutar): string
    {
        return number_format((float) str_replace(',', '.', $tutar), 2, '.', '');
    }

    private function acikTaksitleriErtele(AlacakPlani $plan, int $gun): void
    {
        $taksitler = $this->acikTaksitler($plan);
        if ($taksitler->isEmpty()) {
            throw new IsKuraliIstisnasi('Revize edilecek acik taksit bulunamadi.');
        }

        foreach ($taksitler as $taksit) {
            $yeniVade = Carbon::parse((string) $taksit->vade_tarihi)->addDays($gun)->toDateString();
            $taksit->update(['vade_tarihi' => $yeniVade]);
            $this->taksitCariHareketVadesiniGuncelle($taksit, $yeniVade);
        }
    }

    private function tekTaksitVadesiniDegistir(AlacakPlani $plan, int $taksitId, string $yeniVadeTarihi): void
    {
        if ($taksitId < 1 || trim($yeniVadeTarihi) === '') {
            throw new IsKuraliIstisnasi('Taksit ve yeni vade tarihi zorunludur.');
        }

        $taksit = AlacakPlanTaksiti::query()
            ->where('alacak_plan_id', (int) $plan->getKey())
            ->whereKey($taksitId)
            ->where('kalan_tutar', '>', 0)
            ->whereNotIn('durum', ['odendi', 'iptal'])
            ->lockForUpdate()
            ->first();
        if (! $taksit) {
            throw new IsKuraliIstisnasi('Revize edilecek acik taksit bulunamadi.');
        }

        $yeniVade = Carbon::parse($yeniVadeTarihi)->toDateString();
        $taksit->update(['vade_tarihi' => $yeniVade]);
        $this->taksitCariHareketVadesiniGuncelle($taksit, $yeniVade);
    }

    /**
     * Açık bir veresiye/taksit kaydının tutarını ve vadesini düzenler.
     * Kısmi tahsilat varsa yeni tutar, tahsil edilmiş tutarın altında bırakılamaz.
     *
     * @param array<string,mixed> $veri
     */
    private function taksitDuzenle(AlacakPlani $plan, array $veri): void
    {
        $taksitId = (int) ($veri['taksit_id'] ?? 0);
        $taksit = AlacakPlanTaksiti::query()
            ->where('alacak_plan_id', (int) $plan->getKey())
            ->whereKey($taksitId)
            ->whereNotIn('durum', ['odendi', 'iptal'])
            ->lockForUpdate()
            ->first();

        if (! $taksit) {
            throw new IsKuraliIstisnasi('Düzenlenecek açık veresiye kaydı bulunamadı.');
        }

        $yeniTutar = $this->decimal((string) ($veri['yeni_tutar'] ?? '0'));
        $odenen = $this->decimal((string) $taksit->odenen_tutar);
        if (bccomp($yeniTutar, '0.00', 2) <= 0 || bccomp($yeniTutar, $odenen, 2) < 0) {
            throw new IsKuraliIstisnasi('Yeni tutar sıfırdan büyük ve tahsil edilmiş tutardan küçük olamaz.');
        }

        $yeniVade = trim((string) ($veri['yeni_vade_tarihi'] ?? ''));
        if ($yeniVade === '') {
            throw new IsKuraliIstisnasi('Vade tarihi zorunludur.');
        }
        $yeniVade = Carbon::parse($yeniVade)->toDateString();
        $eskiTutar = $this->decimal((string) $taksit->tutar);
        $fark = bcsub($yeniTutar, $eskiTutar, 2);
        $yeniKalan = $this->sifirinAltinaDusme(bcsub($yeniTutar, $odenen, 2));

        $taksit->update([
            'tutar' => $yeniTutar,
            'kalan_tutar' => $yeniKalan,
            'vade_tarihi' => $yeniVade,
            'durum' => $this->taksitDurumu($odenen, $yeniKalan, $yeniVade),
        ]);
        $this->taksitCariHareketVadesiniGuncelle($taksit, $yeniVade);
        $this->taksitCariHareketTutariGuncelle($taksit, $fark);

        if (bccomp($fark, '0.00', 2) !== 0) {
            $plan->update([
                'toplam_tutar' => bcadd((string) $plan->toplam_tutar, $fark, 2),
                'planlanan_tutar' => bcadd((string) $plan->planlanan_tutar, $fark, 2),
            ]);
        }

        if (array_key_exists('plan_aciklama', $veri)) {
            $plan->update(['aciklama' => trim((string) $veri['plan_aciklama']) ?: null]);
        }

        $this->planOzetiniTaksitlerdenGuncelle($plan->fresh() ?? $plan);
    }

    /**
     * @param array<string,mixed> $veri
     */
    private function kalanTutariYenidenTaksitlendir(AlacakPlani $plan, array $veri): void
    {
        $acikTaksitler = $this->acikTaksitler($plan);
        if ($acikTaksitler->isEmpty()) {
            throw new IsKuraliIstisnasi('Yeniden taksitlendirilecek acik tutar bulunamadi.');
        }
        if ($acikTaksitler->contains(fn (AlacakPlanTaksiti $taksit): bool => (float) $taksit->odenen_tutar > 0)) {
            throw new IsKuraliIstisnasi('Kismi tahsilatli acik taksitler yeniden taksitlendirilemez. Once ilgili tahsilati tersleyin veya sadece vade revizyonu yapin.');
        }

        $kalan = number_format((float) $acikTaksitler->sum('kalan_tutar'), 2, '.', '');
        $taksitSayisi = max(1, (int) ($veri['taksit_sayisi'] ?? 1));
        $aralikGun = max(1, (int) ($veri['taksit_araligi_gun'] ?? 30));
        $ilkVade = Carbon::parse((string) ($veri['ilk_vade_tarihi'] ?? now()->addDays(30)->toDateString()))->startOfDay();
        $siraNo = (int) $plan->taksitler()->max('sira_no');
        $cariHareketUret = $acikTaksitler->contains(fn (AlacakPlanTaksiti $taksit): bool => (int) ($taksit->cari_hareket_id ?? 0) > 0);

        foreach ($acikTaksitler as $taksit) {
            $cariHareketId = (int) ($taksit->cari_hareket_id ?? 0);
            if ($cariHareketId > 0) {
                $hareket = CariHareketi::query()
                    ->where('firma_id', (int) $plan->firma_id)
                    ->whereKey($cariHareketId)
                    ->where('durum', CariHareketDurumu::Aktif)
                    ->first();
                if ($hareket) {
                    $this->cariHareketServisi->kaydiIptalEt($hareket, 'Alacak plani revizyonu');
                }
            }

            $taksit->update([
                'kalan_tutar' => '0.00',
                'durum' => 'iptal',
            ]);
        }

        foreach ($this->taksitTutarlari($kalan, $taksitSayisi) as $index => $tutar) {
            $vade = $ilkVade->copy()->addDays($aralikGun * $index);
            $yeniTaksit = AlacakPlanTaksiti::query()->create([
                'firma_id' => (int) $plan->firma_id,
                'alacak_plan_id' => (int) $plan->getKey(),
                'cari_id' => (int) $plan->cari_id,
                'sira_no' => ++$siraNo,
                'vade_tarihi' => $vade->toDateString(),
                'tutar' => $tutar,
                'odenen_tutar' => '0.00',
                'kalan_tutar' => $tutar,
                'durum' => $vade->isPast() && ! $vade->isToday() ? 'gecikti' : 'bekliyor',
            ]);

            if ($cariHareketUret) {
                $cariHareket = $this->cariHareketServisi->kayitOlustur((int) $plan->firma_id, [
                    'cari_id' => (int) $plan->cari_id,
                    'belge_turu' => CariHareketBelgeTuru::Satis,
                    'belge_id' => (int) $yeniTaksit->getKey(),
                    'islem_tarihi' => now(),
                    'vade_tarihi' => $vade->toDateString(),
                    'borc' => '0.00',
                    'alacak' => $tutar,
                    'para_birimi' => (string) $plan->para_birimi,
                    'aciklama' => 'Alacak plani revizyonu / Taksit '.$siraNo,
                ]);

                $yeniTaksit->update(['cari_hareket_id' => (int) $cariHareket->getKey()]);
            }
        }

        $plan->update([
            'plan_turu' => $taksitSayisi > 1 ? 'taksit' : 'veresiye',
            'kalan_tutar' => $kalan,
            'durum' => Carbon::parse($ilkVade)->isPast() && ! Carbon::parse($ilkVade)->isToday() ? 'gecikti' : (bccomp((string) $plan->odenen_tutar, '0.00', 2) === 1 ? 'kismi_odendi' : 'aktif'),
        ]);
    }

    /**
     * @param array<string,mixed> $veri
     */
    private function vadeFarkiTaksitiEkle(AlacakPlani $plan, array $veri): void
    {
        $tutar = $this->decimal((string) ($veri['vade_farki_tutari'] ?? $veri['tutar'] ?? '0'));
        if (bccomp($tutar, '0.00', 2) <= 0) {
            throw new IsKuraliIstisnasi('Eklenecek vade farki tutari sifirdan buyuk olmalidir.');
        }

        $vadeTarihi = Carbon::parse((string) ($veri['vade_tarihi'] ?? $veri['vade_farki_vade_tarihi'] ?? now()->toDateString()))->startOfDay();
        $siraNo = ((int) $plan->taksitler()->max('sira_no')) + 1;

        $taksit = AlacakPlanTaksiti::query()->create([
            'firma_id' => (int) $plan->firma_id,
            'alacak_plan_id' => (int) $plan->getKey(),
            'cari_id' => (int) $plan->cari_id,
            'sira_no' => $siraNo,
            'vade_tarihi' => $vadeTarihi->toDateString(),
            'tutar' => $tutar,
            'odenen_tutar' => '0.00',
            'kalan_tutar' => $tutar,
            'durum' => $vadeTarihi->isPast() && ! $vadeTarihi->isToday() ? 'gecikti' : 'bekliyor',
        ]);

        $cariHareket = $this->cariHareketServisi->kayitOlustur((int) $plan->firma_id, [
            'cari_id' => (int) $plan->cari_id,
            'belge_turu' => CariHareketBelgeTuru::Satis,
            'belge_id' => (int) $taksit->getKey(),
            'islem_tarihi' => now(),
            'vade_tarihi' => $vadeTarihi->toDateString(),
            'borc' => '0.00',
            'alacak' => $tutar,
            'para_birimi' => (string) $plan->para_birimi,
            'aciklama' => trim((string) ($veri['aciklama'] ?? 'Alacak plani vade farki')).' / Taksit '.$siraNo,
        ]);

        $taksit->update(['cari_hareket_id' => (int) $cariHareket->getKey()]);

        $plan->update([
            'toplam_tutar' => bcadd((string) $plan->toplam_tutar, $tutar, 2),
            'planlanan_tutar' => bcadd((string) $plan->planlanan_tutar, $tutar, 2),
            'kalan_tutar' => bcadd((string) $plan->kalan_tutar, $tutar, 2),
            'vade_farki_tutari' => bcadd((string) ($plan->vade_farki_tutari ?? '0'), $tutar, 2),
            'faiz_tutari' => bcadd((string) ($plan->faiz_tutari ?? '0'), $tutar, 2),
            'son_vade_tarihi' => max((string) ($plan->son_vade_tarihi?->toDateString() ?? $vadeTarihi->toDateString()), $vadeTarihi->toDateString()),
            'durum' => $vadeTarihi->isPast() && ! $vadeTarihi->isToday() ? 'gecikti' : (bccomp((string) $plan->odenen_tutar, '0.00', 2) === 1 ? 'kismi_odendi' : 'aktif'),
        ]);
    }

    /**
     * @param array<string,mixed> $veri
     */
    private function erkenKapamaIndirimiUygula(AlacakPlani $plan, array $veri): void
    {
        $indirimTutari = $this->decimal((string) ($veri['indirim_tutari'] ?? $veri['tutar'] ?? '0'));
        if (bccomp($indirimTutari, '0.00', 2) <= 0) {
            throw new IsKuraliIstisnasi('Erken kapama indirimi sifirdan buyuk olmalidir.');
        }

        $acikTaksitler = AlacakPlanTaksiti::query()
            ->where('alacak_plan_id', (int) $plan->getKey())
            ->where('kalan_tutar', '>', 0)
            ->whereNotIn('durum', ['odendi', 'iptal'])
            ->orderByDesc('vade_tarihi')
            ->orderByDesc('sira_no')
            ->lockForUpdate()
            ->get();
        if ($acikTaksitler->isEmpty()) {
            throw new IsKuraliIstisnasi('Indirim uygulanacak acik vade bulunamadi.');
        }

        $acikKalan = $this->decimal((string) $acikTaksitler->sum('kalan_tutar'));
        if (bccomp($indirimTutari, $acikKalan, 2) === 1) {
            throw new IsKuraliIstisnasi('Indirim tutari acik kalan tutari asamaz.');
        }

        $this->cariHareketServisi->kayitOlustur((int) $plan->firma_id, [
            'cari_id' => (int) $plan->cari_id,
            'belge_turu' => CariHareketBelgeTuru::Mahsup,
            'belge_id' => (int) $plan->getKey(),
            'islem_tarihi' => now(),
            'vade_tarihi' => null,
            'borc' => $indirimTutari,
            'alacak' => '0.00',
            'para_birimi' => (string) $plan->para_birimi,
            'aciklama' => trim((string) ($veri['aciklama'] ?? 'Erken kapama indirimi')).' / Plan #'.(int) $plan->getKey(),
        ]);

        $kalanIndirim = $indirimTutari;
        foreach ($acikTaksitler as $taksit) {
            if (bccomp($kalanIndirim, '0.00', 2) <= 0) {
                break;
            }

            $taksitKalan = $this->decimal((string) $taksit->kalan_tutar);
            $uygulanacak = bccomp($kalanIndirim, $taksitKalan, 2) === 1 ? $taksitKalan : $kalanIndirim;
            $yeniTutar = bcsub((string) $taksit->tutar, $uygulanacak, 2);
            $yeniKalan = bcsub((string) $taksit->kalan_tutar, $uygulanacak, 2);
            if (bccomp($yeniTutar, (string) $taksit->odenen_tutar, 2) < 0) {
                $yeniTutar = (string) $taksit->odenen_tutar;
            }
            if (bccomp($yeniKalan, '0.00', 2) < 0) {
                $yeniKalan = '0.00';
            }

            $taksit->update([
                'tutar' => $yeniTutar,
                'kalan_tutar' => $yeniKalan,
                'durum' => $this->taksitDurumu((string) $taksit->odenen_tutar, $yeniKalan, $taksit->vade_tarihi),
            ]);

            $kalanIndirim = bcsub($kalanIndirim, $uygulanacak, 2);
        }

        $vadeFarkiDusulecek = bccomp((string) ($plan->vade_farki_tutari ?? '0'), $indirimTutari, 2) === 1
            ? $indirimTutari
            : (string) ($plan->vade_farki_tutari ?? '0');
        $faizDusulecek = bccomp((string) ($plan->faiz_tutari ?? '0'), $indirimTutari, 2) === 1
            ? $indirimTutari
            : (string) ($plan->faiz_tutari ?? '0');

        $plan->update([
            'toplam_tutar' => $this->sifirinAltinaDusme(bcsub((string) $plan->toplam_tutar, $indirimTutari, 2)),
            'planlanan_tutar' => $this->sifirinAltinaDusme(bcsub((string) $plan->planlanan_tutar, $indirimTutari, 2)),
            'vade_farki_tutari' => $this->sifirinAltinaDusme(bcsub((string) ($plan->vade_farki_tutari ?? '0'), $vadeFarkiDusulecek, 2)),
            'faiz_tutari' => $this->sifirinAltinaDusme(bcsub((string) ($plan->faiz_tutari ?? '0'), $faizDusulecek, 2)),
        ]);

        $this->planOzetiniTaksitlerdenGuncelle($plan->fresh() ?? $plan);
    }

    private function taksitCariHareketVadesiniGuncelle(AlacakPlanTaksiti $taksit, string $vadeTarihi): void
    {
        $cariHareketId = (int) ($taksit->cari_hareket_id ?? 0);
        if ($cariHareketId < 1) {
            return;
        }

        CariHareketi::query()
            ->where('firma_id', (int) $taksit->firma_id)
            ->whereKey($cariHareketId)
            ->where('durum', CariHareketDurumu::Aktif)
            ->update(['vade_tarihi' => $vadeTarihi]);
    }

    private function taksitCariHareketTutariGuncelle(AlacakPlanTaksiti $taksit, string $fark): void
    {
        $cariHareketId = (int) ($taksit->cari_hareket_id ?? 0);
        if ($cariHareketId < 1 || bccomp($fark, '0.00', 2) === 0) {
            return;
        }

        $hareket = CariHareketi::query()
            ->where('firma_id', (int) $taksit->firma_id)
            ->whereKey($cariHareketId)
            ->where('durum', CariHareketDurumu::Aktif)
            ->first();
        if (! $hareket) {
            return;
        }

        $hareket->update([
            'alacak' => bcadd((string) $hareket->alacak, $fark, 2),
        ]);
    }

    private function planSonVadesiniGuncelle(AlacakPlani $plan): void
    {
        $sonVade = AlacakPlanTaksiti::query()
            ->where('alacak_plan_id', (int) $plan->getKey())
            ->whereNotIn('durum', ['iptal'])
            ->max('vade_tarihi');

        $plan->update([
            'son_vade_tarihi' => $sonVade,
        ]);
    }

    private function planOzetiniTaksitlerdenGuncelle(AlacakPlani $plan): void
    {
        $taksitOdenen = number_format((float) AlacakPlanTaksiti::query()
            ->where('alacak_plan_id', (int) $plan->getKey())
            ->sum('odenen_tutar'), 2, '.', '');
        $kalan = number_format((float) AlacakPlanTaksiti::query()
            ->where('alacak_plan_id', (int) $plan->getKey())
            ->sum('kalan_tutar'), 2, '.', '');
        $odenen = bcadd((string) ($plan->pesinat_tutari ?? '0'), $taksitOdenen, 2);

        $durum = 'aktif';
        if (bccomp($kalan, '0.00', 2) <= 0) {
            $durum = 'odendi';
        } elseif (bccomp($odenen, '0.00', 2) === 1) {
            $durum = 'kismi_odendi';
        }

        $gecikenVar = AlacakPlanTaksiti::query()
            ->where('alacak_plan_id', (int) $plan->getKey())
            ->where('kalan_tutar', '>', 0)
            ->whereDate('vade_tarihi', '<', Carbon::today())
            ->exists();
        if ($gecikenVar && $durum !== 'odendi') {
            $durum = 'gecikti';
        }

        $plan->update([
            'odenen_tutar' => $odenen,
            'kalan_tutar' => $kalan,
            'durum' => $durum,
        ]);
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

    private function sifirinAltinaDusme(string $tutar): string
    {
        return bccomp($tutar, '0.00', 2) < 0 ? '0.00' : $tutar;
    }

    private function acikTaksitler(AlacakPlani $plan): \Illuminate\Database\Eloquent\Collection
    {
        return AlacakPlanTaksiti::query()
            ->where('alacak_plan_id', (int) $plan->getKey())
            ->where('kalan_tutar', '>', 0)
            ->whereNotIn('durum', ['odendi', 'iptal'])
            ->orderBy('sira_no')
            ->lockForUpdate()
            ->get();
    }

    /**
     * @return array<string,mixed>
     */
    private function planRevizyonOzeti(AlacakPlani $plan): array
    {
        $plan->loadMissing(['taksitler' => fn ($query) => $query->orderBy('sira_no')]);

        return [
            'plan' => [
                'durum' => (string) $plan->durum,
                'plan_turu' => (string) $plan->plan_turu,
                'toplam_tutar' => (string) $plan->toplam_tutar,
                'planlanan_tutar' => (string) $plan->planlanan_tutar,
                'kalan_tutar' => (string) $plan->kalan_tutar,
                'vade_farki_tutari' => (string) ($plan->vade_farki_tutari ?? '0'),
                'son_vade_tarihi' => $plan->son_vade_tarihi?->toDateString(),
            ],
            'taksitler' => $plan->taksitler
                ->map(fn (AlacakPlanTaksiti $taksit): array => [
                    'id' => (int) $taksit->getKey(),
                    'sira_no' => (int) $taksit->sira_no,
                    'vade_tarihi' => $taksit->vade_tarihi?->toDateString(),
                    'tutar' => (string) $taksit->tutar,
                    'odenen_tutar' => (string) $taksit->odenen_tutar,
                    'kalan_tutar' => (string) $taksit->kalan_tutar,
                    'durum' => (string) $taksit->durum,
                ])
                ->all(),
        ];
    }
}
