<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\AlacakPlanTaksiti;
use App\Models\Muhasebe\AlacakPlani;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use Illuminate\Support\Facades\DB;

class AlacakOperasyonServisi
{
    public function __construct(
        private readonly FinansHareketServisi $finansHareketServisi,
        private readonly AlacakPlanServisi $alacakPlanServisi,
    ) {}

    /**
     * @param iterable<int, int|AlacakPlanTaksiti> $taksitler
     * @param array<string,mixed> $veri
     * @return array<string,mixed>
     */
    public function topluTahsilatOlustur(iterable $taksitler, array $veri): array
    {
        $taksitIdleri = collect($taksitler)
            ->map(fn (int|AlacakPlanTaksiti $taksit): int => $taksit instanceof AlacakPlanTaksiti ? (int) $taksit->getKey() : (int) $taksit)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($taksitIdleri === []) {
            throw new IsKuraliIstisnasi('Tahsilat icin en az bir acik vade secilmelidir.');
        }

        return DB::transaction(function () use ($taksitIdleri, $veri): array {
            $seciliTaksitler = AlacakPlanTaksiti::query()
                ->with(['plan', 'cari'])
                ->whereIn('id', $taksitIdleri)
                ->where('kalan_tutar', '>', 0)
                ->whereNotIn('durum', ['odendi', 'iptal'])
                ->orderBy('vade_tarihi')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($seciliTaksitler->isEmpty()) {
                throw new IsKuraliIstisnasi('Secili kayitlar icinde tahsil edilebilir acik vade bulunamadi.');
            }

            $firmaId = (int) $seciliTaksitler->first()->firma_id;
            $cariId = (int) $seciliTaksitler->first()->cari_id;
            $paraBirimi = strtoupper((string) ($seciliTaksitler->first()->plan?->para_birimi ?: 'TRY'));

            foreach ($seciliTaksitler as $taksit) {
                $taksitParaBirimi = strtoupper((string) ($taksit->plan?->para_birimi ?: 'TRY'));
                if ((int) $taksit->firma_id !== $firmaId || (int) $taksit->cari_id !== $cariId || $taksitParaBirimi !== $paraBirimi) {
                    throw new IsKuraliIstisnasi('Toplu tahsilat ayni firma, cari ve para birimine ait vadeler icin yapilabilir.');
                }
            }

            $toplamKalan = $this->decimal((string) $seciliTaksitler->sum('kalan_tutar'));
            $tahsilatTipi = (string) ($veri['tahsilat_tipi'] ?? $veri['tahsilat_kapsami'] ?? 'secili_kalan');
            $tahsilEdilecek = $tahsilatTipi === 'ozel'
                ? $this->decimal((string) ($veri['tutar'] ?? '0'))
                : $toplamKalan;

            if (bccomp($tahsilEdilecek, '0.00', 2) <= 0) {
                throw new IsKuraliIstisnasi('Tahsilat tutari sifirdan buyuk olmalidir.');
            }
            if (bccomp($tahsilEdilecek, $toplamKalan, 2) === 1) {
                throw new IsKuraliIstisnasi('Tahsilat tutari secili vadelerin kalan tutarini asamaz.');
            }

            $kanal = strtolower(trim((string) ($veri['kanal'] ?? '')));
            $hesapId = $this->hesapId($kanal, $veri);
            if ($hesapId < 1) {
                throw new IsKuraliIstisnasi('Tahsilat hesabi secilmelidir.');
            }

            $kalanDagitim = $tahsilEdilecek;
            $finansHareketIdleri = [];
            $islemAdedi = 0;
            $kapatilanTaksit = 0;

            foreach ($seciliTaksitler as $taksit) {
                if (bccomp($kalanDagitim, '0.00', 2) <= 0) {
                    break;
                }

                $taksitKalan = $this->decimal((string) $taksit->kalan_tutar);
                $uygulanacak = bccomp($kalanDagitim, $taksitKalan, 2) === 1 ? $taksitKalan : $kalanDagitim;
                if (bccomp($uygulanacak, '0.00', 2) <= 0) {
                    continue;
                }

                $sonuc = $this->tahsilatKaydet(
                    $kanal,
                    $firmaId,
                    (int) $taksit->cari_id,
                    $hesapId,
                    $uygulanacak,
                    $paraBirimi,
                    $veri['tarih'] ?? now(),
                    $this->tahsilatAciklamasi($taksit, $veri),
                    (int) $taksit->getKey(),
                );

                if (isset($sonuc['finans'])) {
                    $finansHareketIdleri[] = (int) $sonuc['finans']->getKey();
                }

                $islemAdedi++;
                $kalanDagitim = bcsub($kalanDagitim, $uygulanacak, 2);

                $taksit->refresh();
                if (bccomp($this->decimal((string) $taksit->kalan_tutar), '0.00', 2) <= 0) {
                    $kapatilanTaksit++;
                }
            }

            return [
                'firma_id' => $firmaId,
                'cari_id' => $cariId,
                'para_birimi' => $paraBirimi,
                'secili_taksit_adedi' => $seciliTaksitler->count(),
                'islem_adedi' => $islemAdedi,
                'kapatilan_taksit_adedi' => $kapatilanTaksit,
                'secili_kalan_tutar' => $toplamKalan,
                'tahsil_edilen_tutar' => $tahsilEdilecek,
                'kalan_dagitilmeyen_tutar' => $kalanDagitim,
                'finans_hareket_idleri' => $finansHareketIdleri,
            ];
        });
    }

    /**
     * @param array<string,mixed> $veri
     * @return array<string,mixed>
     */
    public function planiKapat(AlacakPlani $plan, array $veri): array
    {
        return DB::transaction(function () use ($plan, $veri): array {
            $plan = AlacakPlani::query()
                ->with(['taksitler' => fn ($query) => $query
                    ->where('kalan_tutar', '>', 0)
                    ->whereNotIn('durum', ['odendi', 'iptal'])
                    ->orderBy('vade_tarihi')
                    ->orderBy('id')])
                ->whereKey($plan->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array((string) $plan->durum, ['aktif', 'kismi_odendi', 'gecikti'], true)) {
                throw new IsKuraliIstisnasi('Sadece acik alacak planlari kapatilabilir.');
            }

            $indirimTutari = $this->decimal((string) ($veri['indirim_tutari'] ?? '0'));
            $planKalan = $this->decimal((string) ($plan->kalan_tutar ?? $plan->taksitler->sum('kalan_tutar')));
            if (bccomp($indirimTutari, $planKalan, 2) === 1) {
                throw new IsKuraliIstisnasi('Indirim tutari plan kalanini asamaz.');
            }

            $indirimUygulandi = '0.00';
            if (bccomp($indirimTutari, '0.00', 2) === 1) {
                $this->alacakPlanServisi->planiRevizeEt($plan, [
                    'revizyon_turu' => 'erken_kapama_indirimi',
                    'indirim_tutari' => $indirimTutari,
                    'aciklama' => trim((string) ($veri['kapama_notu'] ?? 'Erken kapama indirimi uygulandi.')),
                    'olusturan_id' => (int) ($veri['olusturan_id'] ?? 0) ?: null,
                ]);
                $indirimUygulandi = $indirimTutari;
            }

            $plan = $plan->fresh(['taksitler' => fn ($query) => $query
                ->where('kalan_tutar', '>', 0)
                ->whereNotIn('durum', ['odendi', 'iptal'])
                ->orderBy('vade_tarihi')
                ->orderBy('id')]) ?? $plan;

            $taksitIdleri = $plan->taksitler->pluck('id')->map(fn ($id): int => (int) $id)->all();
            if ($taksitIdleri === []) {
                return [
                    'plan_id' => (int) $plan->getKey(),
                    'indirim_tutari' => $indirimUygulandi,
                    'tahsilat' => [
                        'tahsil_edilen_tutar' => '0.00',
                        'islem_adedi' => 0,
                        'kapatilan_taksit_adedi' => 0,
                        'para_birimi' => strtoupper((string) ($plan->para_birimi ?: 'TRY')),
                    ],
                ];
            }

            $tahsilat = $this->topluTahsilatOlustur($taksitIdleri, $veri + [
                'tahsilat_tipi' => 'secili_kalan',
                'aciklama' => trim((string) ($veri['kapama_notu'] ?? 'Plan erken kapama tahsilati.')),
            ]);

            return [
                'plan_id' => (int) $plan->getKey(),
                'indirim_tutari' => $indirimUygulandi,
                'tahsilat' => $tahsilat,
            ];
        });
    }

    /**
     * @param array<string,mixed> $veri
     */
    private function hesapId(string $kanal, array $veri): int
    {
        return match ($kanal) {
            'kasa' => (int) ($veri['kasa_hesap_id'] ?? 0),
            'banka' => (int) ($veri['banka_hesap_id'] ?? 0),
            'pos' => (int) ($veri['pos_hesap_id'] ?? 0),
            default => 0,
        };
    }

    /**
     * @param array<string,mixed> $veri
     * @return array<string,mixed>
     */
    private function tahsilatKaydet(
        string $kanal,
        int $firmaId,
        int $cariId,
        int $hesapId,
        string $tutar,
        string $paraBirimi,
        mixed $tarih,
        ?string $aciklama,
        int $taksitId,
    ): array {
        return match ($kanal) {
            'kasa' => $this->finansHareketServisi->tahsilatKasadanKaydet(
                $firmaId,
                $cariId,
                $hesapId,
                $tutar,
                $paraBirimi,
                $tarih,
                $aciklama,
                'alacak_plan_taksiti',
                $taksitId,
            ),
            'banka' => $this->finansHareketServisi->tahsilatBankadanKaydet(
                $firmaId,
                $cariId,
                $hesapId,
                $tutar,
                $paraBirimi,
                $tarih,
                $aciklama,
                'alacak_plan_taksiti',
                $taksitId,
            ),
            'pos' => $this->finansHareketServisi->tahsilatPosKaydet(
                $firmaId,
                $cariId,
                $hesapId,
                $tutar,
                $paraBirimi,
                $tarih,
                $aciklama,
                'alacak_plan_taksiti',
                $taksitId,
            ),
            default => throw new IsKuraliIstisnasi('Gecersiz tahsilat kanali.'),
        };
    }

    /**
     * @param array<string,mixed> $veri
     */
    private function tahsilatAciklamasi(AlacakPlanTaksiti $taksit, array $veri): string
    {
        $anaAciklama = trim((string) ($veri['aciklama'] ?? 'Toplu vade tahsilati'));

        return $anaAciklama.' / Plan #'.(int) $taksit->alacak_plan_id.' / Taksit #'.(int) $taksit->sira_no;
    }

    private function decimal(string $tutar): string
    {
        return number_format((float) str_replace(',', '.', $tutar), 2, '.', '');
    }
}
