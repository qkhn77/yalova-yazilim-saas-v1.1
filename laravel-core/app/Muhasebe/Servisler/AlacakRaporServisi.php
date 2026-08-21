<?php

namespace App\Muhasebe\Servisler;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class AlacakRaporServisi
{
    /**
     * @return array<int, array<string, string>>
     */
    public function yaslandirmaOzeti(int $firmaId, array $filtreler = []): array
    {
        $bugun = Carbon::today();
        $satirlar = [];

        foreach ($this->acikTaksitSorgusu($firmaId, $filtreler)->select([
            't.vade_tarihi',
            't.kalan_tutar',
            'plan.para_birimi',
        ])->cursor() as $taksit) {
            $paraBirimi = strtoupper((string) ($taksit->para_birimi ?: 'TRY'));
            $satirlar[$paraBirimi] ??= $this->bosYaslandirmaSatiri($paraBirimi);

            $kalan = $this->decimal($taksit->kalan_tutar);
            $vade = Carbon::parse((string) $taksit->vade_tarihi)->startOfDay();
            $alan = 'vadesi_gelmemis';

            if ($vade->isSameDay($bugun)) {
                $alan = 'bugun';
            } elseif ($vade->lt($bugun)) {
                $gecikmeGunu = $vade->diffInDays($bugun);
                $alan = match (true) {
                    $gecikmeGunu <= 30 => 'geciken_1_30',
                    $gecikmeGunu <= 60 => 'geciken_31_60',
                    $gecikmeGunu <= 90 => 'geciken_61_90',
                    default => 'geciken_90_plus',
                };
            }

            $satirlar[$paraBirimi][$alan] = $this->decimalTopla($satirlar[$paraBirimi][$alan], $kalan);
            $satirlar[$paraBirimi]['toplam'] = $this->decimalTopla($satirlar[$paraBirimi]['toplam'], $kalan);
        }

        ksort($satirlar);

        return array_values($satirlar);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function cariOzetleri(int $firmaId, int $limit = 10, array $filtreler = []): array
    {
        $bugun = Carbon::today()->toDateString();
        $query = $this->acikTaksitSorgusu($firmaId, $filtreler)
            ->selectRaw('c.id as cari_id, c.kod as cari_kod, c.ad as cari_ad, plan.para_birimi as para_birimi')
            ->selectRaw('COUNT(DISTINCT plan.id) as plan_adedi')
            ->selectRaw('COUNT(t.id) as acik_vade_adedi')
            ->selectRaw('COALESCE(SUM(t.kalan_tutar), 0) as acik_toplam')
            ->selectRaw('COALESCE(SUM(CASE WHEN t.vade_tarihi < ? THEN t.kalan_tutar ELSE 0 END), 0) as geciken_toplam', [$bugun])
            ->selectRaw('COALESCE(SUM(CASE WHEN t.vade_tarihi = ? THEN t.kalan_tutar ELSE 0 END), 0) as bugun_toplam', [$bugun])
            ->selectRaw('COALESCE(SUM(CASE WHEN t.vade_tarihi > ? THEN t.kalan_tutar ELSE 0 END), 0) as gelecek_toplam', [$bugun])
            ->selectRaw('MIN(t.vade_tarihi) as ilk_vade_tarihi')
            ->selectRaw('MAX(t.vade_tarihi) as son_vade_tarihi')
            ->groupBy('c.id', 'c.kod', 'c.ad', 'plan.para_birimi')
            ->orderByDesc('acik_toplam')
            ->orderBy('c.ad');

        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query->get()
            ->map(fn (object $row): array => $this->raporSatiri($row, [
                'acik_toplam',
                'geciken_toplam',
                'bugun_toplam',
                'gelecek_toplam',
            ]))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function tahsilatOncelikSatirlari(int $firmaId, int $limit = 10, array $filtreler = []): array
    {
        $bugun = Carbon::today();
        $bugunMetni = $bugun->toDateString();
        $query = $this->acikTaksitSorgusu($firmaId, $filtreler)
            ->selectRaw('c.id as cari_id, c.kod as cari_kod, c.ad as cari_ad, plan.para_birimi as para_birimi')
            ->selectRaw('COUNT(DISTINCT plan.id) as plan_adedi')
            ->selectRaw('COUNT(t.id) as acik_vade_adedi')
            ->selectRaw('COALESCE(SUM(t.kalan_tutar), 0) as acik_toplam')
            ->selectRaw('COALESCE(SUM(CASE WHEN t.vade_tarihi < ? THEN t.kalan_tutar ELSE 0 END), 0) as geciken_toplam', [$bugunMetni])
            ->selectRaw('COALESCE(SUM(CASE WHEN t.vade_tarihi = ? THEN t.kalan_tutar ELSE 0 END), 0) as bugun_toplam', [$bugunMetni])
            ->selectRaw('MIN(t.vade_tarihi) as ilk_vade_tarihi')
            ->selectRaw('MAX(t.son_tahsilat_tarihi) as son_tahsilat_tarihi')
            ->groupBy('c.id', 'c.kod', 'c.ad', 'plan.para_birimi')
            ->orderByRaw('CASE WHEN COALESCE(SUM(CASE WHEN t.vade_tarihi < ? THEN t.kalan_tutar ELSE 0 END), 0) > 0 THEN 0 WHEN COALESCE(SUM(CASE WHEN t.vade_tarihi = ? THEN t.kalan_tutar ELSE 0 END), 0) > 0 THEN 1 ELSE 2 END', [$bugunMetni, $bugunMetni])
            ->orderBy('ilk_vade_tarihi')
            ->orderByDesc('acik_toplam')
            ->orderBy('c.ad');

        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query->get()
            ->map(function (object $row) use ($bugun): array {
                $satir = $this->raporSatiri($row, ['acik_toplam', 'geciken_toplam', 'bugun_toplam']);
                $ilkVade = filled($satir['ilk_vade_tarihi'] ?? null)
                    ? Carbon::parse((string) $satir['ilk_vade_tarihi'])->startOfDay()
                    : null;
                $gecikmeGunu = $ilkVade && $ilkVade->lt($bugun)
                    ? (int) $ilkVade->diffInDays($bugun)
                    : 0;

                $satir['gecikme_gunu'] = $gecikmeGunu;
                $satir['oncelik'] = $this->tahsilatOnceligi(
                    $gecikmeGunu,
                    (float) ($satir['geciken_toplam'] ?? 0),
                    (float) ($satir['bugun_toplam'] ?? 0),
                    (int) ($satir['acik_vade_adedi'] ?? 0),
                );

                return $satir;
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function takipAjandasi(int $firmaId, int $limit = 10, array $filtreler = [], int $yaklasanGun = 7): array
    {
        $simdi = now();
        $bitis = $simdi->copy()->addDays(max(1, $yaklasanGun))->endOfDay();
        $query = $this->takipNotuSorgusu($firmaId, $filtreler)
            ->whereIn('n.durum', ['planlandi', 'ulasilamadi', 'odeme_sozu', 'takip_gerekli'])
            ->whereNotNull('n.sonraki_takip_tarihi')
            ->where('n.sonraki_takip_tarihi', '<=', $bitis)
            ->orderByRaw('CASE WHEN n.sonraki_takip_tarihi < ? THEN 0 WHEN DATE(n.sonraki_takip_tarihi) = ? THEN 1 ELSE 2 END', [
                $simdi->toDateTimeString(),
                $simdi->toDateString(),
            ])
            ->orderBy('n.sonraki_takip_tarihi')
            ->orderBy('c.ad');

        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query->get()
            ->map(fn (object $row): array => $this->takipNotuSatiri($row, true))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function takipNotlari(int $firmaId, int $limit = 0, array $filtreler = []): array
    {
        $query = $this->takipNotuSorgusu($firmaId, $filtreler)
            ->orderByDesc('n.takip_tarihi')
            ->orderByDesc('n.id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query->get()
            ->map(fn (object $row): array => $this->takipNotuSatiri($row, false))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function kaynakOzetleri(int $firmaId, array $filtreler = []): array
    {
        return $this->acikTaksitSorgusu($firmaId, $filtreler)
            ->selectRaw('plan.kaynak_turu as kaynak_turu, plan.plan_turu as plan_turu, plan.para_birimi as para_birimi')
            ->selectRaw('COUNT(DISTINCT plan.id) as plan_adedi')
            ->selectRaw('COUNT(t.id) as acik_vade_adedi')
            ->selectRaw('COALESCE(SUM(t.kalan_tutar), 0) as acik_toplam')
            ->selectRaw('MIN(t.vade_tarihi) as ilk_vade_tarihi')
            ->groupBy('plan.kaynak_turu', 'plan.plan_turu', 'plan.para_birimi')
            ->orderBy('plan.kaynak_turu')
            ->orderBy('plan.plan_turu')
            ->get()
            ->map(fn (object $row): array => $this->raporSatiri($row, ['acik_toplam']))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function planOzetleri(int $firmaId, int $limit = 0, array $filtreler = []): array
    {
        $bugun = Carbon::today()->toDateString();
        $query = $this->acikTaksitSorgusu($firmaId, $filtreler)
            ->selectRaw('plan.id as plan_id, plan.kaynak_turu, plan.kaynak_id, plan.plan_turu, plan.durum, plan.toplam_tutar, plan.pesinat_tutari, plan.odenen_tutar, plan.kalan_tutar, plan.para_birimi, plan.baslangic_tarihi, plan.son_vade_tarihi, plan.aciklama')
            ->selectRaw('c.id as cari_id, c.kod as cari_kod, c.ad as cari_ad')
            ->selectRaw('COUNT(t.id) as acik_vade_adedi')
            ->selectRaw('COALESCE(SUM(t.kalan_tutar), 0) as acik_toplam')
            ->selectRaw('COALESCE(SUM(CASE WHEN t.vade_tarihi < ? THEN t.kalan_tutar ELSE 0 END), 0) as geciken_toplam', [$bugun])
            ->selectRaw('MIN(t.vade_tarihi) as ilk_acik_vade_tarihi')
            ->selectRaw('MAX(t.vade_tarihi) as son_acik_vade_tarihi')
            ->groupBy(
                'plan.id',
                'plan.kaynak_turu',
                'plan.kaynak_id',
                'plan.plan_turu',
                'plan.durum',
                'plan.toplam_tutar',
                'plan.pesinat_tutari',
                'plan.odenen_tutar',
                'plan.kalan_tutar',
                'plan.para_birimi',
                'plan.baslangic_tarihi',
                'plan.son_vade_tarihi',
                'plan.aciklama',
                'c.id',
                'c.kod',
                'c.ad'
            )
            ->orderBy('ilk_acik_vade_tarihi')
            ->orderBy('plan.id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query->get()
            ->map(fn (object $row): array => $this->raporSatiri($row, [
                'toplam_tutar',
                'pesinat_tutari',
                'odenen_tutar',
                'kalan_tutar',
                'acik_toplam',
                'geciken_toplam',
            ]))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function tahsilatPerformansi(int $firmaId, int $gun = 30, array $filtreler = []): array
    {
        $baslangic = Carbon::today()->subDays(max(1, $gun) - 1)->startOfDay();
        $query = DB::table('muhasebe_alacak_tahsilat_eslesmeleri as e')
            ->join('muhasebe_alacak_planlari as plan', 'plan.id', '=', 'e.alacak_plan_id')
            ->join('cariler as c', 'c.id', '=', 'plan.cari_id')
            ->where('e.firma_id', $firmaId)
            ->whereNull('plan.deleted_at')
            ->where('e.tarih', '>=', $baslangic->toDateTimeString())
            ->selectRaw('plan.para_birimi as para_birimi')
            ->selectRaw('COUNT(DISTINCT e.finans_hareketi_id) as finans_hareket_adedi')
            ->selectRaw('COUNT(DISTINCT e.alacak_plan_id) as plan_adedi')
            ->selectRaw('COUNT(DISTINCT plan.cari_id) as cari_adedi')
            ->selectRaw('COUNT(e.id) as taksit_eslesme_adedi')
            ->selectRaw('COALESCE(SUM(e.tutar), 0) as tahsil_edilen_tutar')
            ->selectRaw('MAX(e.tarih) as son_tahsilat_tarihi')
            ->groupBy('plan.para_birimi')
            ->orderBy('plan.para_birimi');

        $this->tahsilatPerformansiFiltreleriUygula($query, $filtreler);

        return $query->get()
            ->map(fn (object $row): array => $this->raporSatiri($row, ['tahsil_edilen_tutar']))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function riskSkoruSatirlari(int $firmaId, int $limit = 10, array $filtreler = []): array
    {
        $bugun = Carbon::today();
        $bugunMetni = $bugun->toDateString();
        $query = $this->acikTaksitSorgusu($firmaId, $filtreler)
            ->selectRaw('c.id as cari_id, c.kod as cari_kod, c.ad as cari_ad, plan.para_birimi as para_birimi')
            ->selectRaw('COUNT(DISTINCT plan.id) as plan_adedi')
            ->selectRaw('COUNT(t.id) as acik_vade_adedi')
            ->selectRaw('COALESCE(SUM(t.kalan_tutar), 0) as acik_toplam')
            ->selectRaw('COALESCE(SUM(CASE WHEN t.vade_tarihi < ? THEN t.kalan_tutar ELSE 0 END), 0) as geciken_toplam', [$bugunMetni])
            ->selectRaw('MIN(t.vade_tarihi) as ilk_vade_tarihi')
            ->selectRaw('MIN(CASE WHEN t.vade_tarihi < ? THEN t.vade_tarihi ELSE NULL END) as ilk_geciken_vade_tarihi', [$bugunMetni])
            ->selectRaw('MAX(t.son_tahsilat_tarihi) as son_tahsilat_tarihi')
            ->groupBy('c.id', 'c.kod', 'c.ad', 'plan.para_birimi');

        $sozIhlalleri = $this->odemeSozuIhlalleri($firmaId, $filtreler);

        $satirlar = $query->get()
            ->map(function (object $row) use ($bugun, $sozIhlalleri): array {
                $satir = $this->raporSatiri($row, ['acik_toplam', 'geciken_toplam']);
                $ilkGeciken = filled($satir['ilk_geciken_vade_tarihi'] ?? null)
                    ? Carbon::parse((string) $satir['ilk_geciken_vade_tarihi'])->startOfDay()
                    : null;
                $gecikmeGunu = $ilkGeciken && $ilkGeciken->lt($bugun)
                    ? (int) $ilkGeciken->diffInDays($bugun)
                    : 0;
                $anahtar = (int) ($satir['cari_id'] ?? 0).'|'.strtoupper((string) ($satir['para_birimi'] ?? 'TRY'));
                $sozIhlaliAdedi = (int) ($sozIhlalleri[$anahtar] ?? 0);
                $riskSkoru = $this->riskSkoruHesapla(
                    (float) ($satir['acik_toplam'] ?? 0),
                    (float) ($satir['geciken_toplam'] ?? 0),
                    $gecikmeGunu,
                    (int) ($satir['acik_vade_adedi'] ?? 0),
                    $sozIhlaliAdedi,
                );

                $satir['gecikme_gunu'] = $gecikmeGunu;
                $satir['odeme_sozu_ihlali_adedi'] = $sozIhlaliAdedi;
                $satir['risk_skoru'] = $riskSkoru;
                $satir['risk_seviyesi'] = $this->riskSeviyesi($riskSkoru);
                $satir['onerilen_aksiyon'] = $this->riskAksiyonu($riskSkoru, $sozIhlaliAdedi, $gecikmeGunu);

                return $satir;
            })
            ->sortByDesc('risk_skoru')
            ->values();

        if ($limit > 0) {
            $satirlar = $satirlar->take($limit)->values();
        }

        return $satirlar->all();
    }

    private function acikTaksitSorgusu(int $firmaId, array $filtreler = []): Builder
    {
        $query = DB::table('muhasebe_alacak_plan_taksitleri as t')
            ->join('muhasebe_alacak_planlari as plan', 'plan.id', '=', 't.alacak_plan_id')
            ->join('cariler as c', 'c.id', '=', 't.cari_id')
            ->where('t.firma_id', $firmaId)
            ->where('plan.firma_id', $firmaId)
            ->whereNull('t.deleted_at')
            ->whereNull('plan.deleted_at')
            ->where('t.kalan_tutar', '>', 0)
            ->whereNotIn('t.durum', ['odendi', 'iptal'])
            ->whereIn('plan.durum', ['aktif', 'kismi_odendi', 'gecikti']);

        $vadeBaslangic = trim((string) ($filtreler['vade_baslangic'] ?? ''));
        $vadeBitis = trim((string) ($filtreler['vade_bitis'] ?? ''));
        $cariId = (int) ($filtreler['cari_id'] ?? 0);
        $kaynakTuru = trim((string) ($filtreler['kaynak_turu'] ?? ''));
        $planTuru = trim((string) ($filtreler['plan_turu'] ?? ''));
        $cariTuru = trim((string) ($filtreler['cari_turu'] ?? ''));
        $cariGrubuId = (int) ($filtreler['cari_grubu_id'] ?? 0);
        $paraBirimi = strtoupper(trim((string) ($filtreler['para_birimi'] ?? '')));

        if ($vadeBaslangic !== '') {
            $query->whereDate('t.vade_tarihi', '>=', $vadeBaslangic);
        }
        if ($vadeBitis !== '') {
            $query->whereDate('t.vade_tarihi', '<=', $vadeBitis);
        }
        if ($cariId > 0) {
            $query->where('t.cari_id', $cariId);
        }
        if ($kaynakTuru !== '') {
            $query->where('plan.kaynak_turu', $kaynakTuru);
        }
        if ($planTuru !== '') {
            $query->where('plan.plan_turu', $planTuru);
        }
        if ($cariTuru !== '') {
            $query->where('c.tur', $cariTuru);
        }
        if ($cariGrubuId > 0) {
            $query->where('c.cari_grubu_id', $cariGrubuId);
        }
        if ($paraBirimi !== '') {
            $query->where('plan.para_birimi', $paraBirimi);
        }

        return $query;
    }

    private function takipNotuSorgusu(int $firmaId, array $filtreler = []): Builder
    {
        $query = DB::table('muhasebe_alacak_takip_notlari as n')
            ->join('cariler as c', 'c.id', '=', 'n.cari_id')
            ->leftJoin('muhasebe_alacak_planlari as plan', 'plan.id', '=', 'n.alacak_plan_id')
            ->leftJoin('muhasebe_alacak_plan_taksitleri as t', 't.id', '=', 'n.alacak_plan_taksiti_id')
            ->where('n.firma_id', $firmaId)
            ->whereNull('n.deleted_at')
            ->selectRaw('n.id as takip_notu_id, n.takip_tipi, n.durum as takip_durumu, n.takip_tarihi, n.sonraki_takip_tarihi, n.odeme_sozu_tarihi, n.odeme_sozu_tutari, n.odeme_sozu_durumu, n.kapanis_tarihi, n.beklenen_tutar, n.para_birimi, n.not, n.sonuc_notu')
            ->selectRaw('c.id as cari_id, c.kod as cari_kod, c.ad as cari_ad')
            ->selectRaw('plan.id as plan_id, plan.kaynak_turu, plan.kaynak_id, plan.plan_turu')
            ->selectRaw('t.id as taksit_id, t.sira_no, t.vade_tarihi, t.kalan_tutar');

        $vadeBaslangic = trim((string) ($filtreler['vade_baslangic'] ?? ''));
        $vadeBitis = trim((string) ($filtreler['vade_bitis'] ?? ''));
        $cariId = (int) ($filtreler['cari_id'] ?? 0);
        $kaynakTuru = trim((string) ($filtreler['kaynak_turu'] ?? ''));
        $planTuru = trim((string) ($filtreler['plan_turu'] ?? ''));
        $cariTuru = trim((string) ($filtreler['cari_turu'] ?? ''));
        $cariGrubuId = (int) ($filtreler['cari_grubu_id'] ?? 0);
        $paraBirimi = strtoupper(trim((string) ($filtreler['para_birimi'] ?? '')));

        if ($vadeBaslangic !== '') {
            $query->whereDate('t.vade_tarihi', '>=', $vadeBaslangic);
        }
        if ($vadeBitis !== '') {
            $query->whereDate('t.vade_tarihi', '<=', $vadeBitis);
        }
        if ($cariId > 0) {
            $query->where('n.cari_id', $cariId);
        }
        if ($kaynakTuru !== '') {
            $query->where('plan.kaynak_turu', $kaynakTuru);
        }
        if ($planTuru !== '') {
            $query->where('plan.plan_turu', $planTuru);
        }
        if ($cariTuru !== '') {
            $query->where('c.tur', $cariTuru);
        }
        if ($cariGrubuId > 0) {
            $query->where('c.cari_grubu_id', $cariGrubuId);
        }
        if ($paraBirimi !== '') {
            $query->where('n.para_birimi', $paraBirimi);
        }

        return $query;
    }

    private function tahsilatPerformansiFiltreleriUygula(Builder $query, array $filtreler): void
    {
        $cariId = (int) ($filtreler['cari_id'] ?? 0);
        $kaynakTuru = trim((string) ($filtreler['kaynak_turu'] ?? ''));
        $planTuru = trim((string) ($filtreler['plan_turu'] ?? ''));
        $cariTuru = trim((string) ($filtreler['cari_turu'] ?? ''));
        $cariGrubuId = (int) ($filtreler['cari_grubu_id'] ?? 0);
        $paraBirimi = strtoupper(trim((string) ($filtreler['para_birimi'] ?? '')));

        if ($cariId > 0) {
            $query->where('plan.cari_id', $cariId);
        }
        if ($kaynakTuru !== '') {
            $query->where('plan.kaynak_turu', $kaynakTuru);
        }
        if ($planTuru !== '') {
            $query->where('plan.plan_turu', $planTuru);
        }
        if ($cariTuru !== '') {
            $query->where('c.tur', $cariTuru);
        }
        if ($cariGrubuId > 0) {
            $query->where('c.cari_grubu_id', $cariGrubuId);
        }
        if ($paraBirimi !== '') {
            $query->where('plan.para_birimi', $paraBirimi);
        }
    }

    /**
     * @return array<string,int>
     */
    private function odemeSozuIhlalleri(int $firmaId, array $filtreler): array
    {
        $query = DB::table('muhasebe_alacak_takip_notlari as n')
            ->leftJoin('muhasebe_alacak_planlari as plan', 'plan.id', '=', 'n.alacak_plan_id')
            ->join('cariler as c', 'c.id', '=', 'n.cari_id')
            ->where('n.firma_id', $firmaId)
            ->whereNull('n.deleted_at')
            ->where(function (Builder $q): void {
                $q->where('n.odeme_sozu_durumu', 'tutulmadi')
                    ->orWhere(function (Builder $alt): void {
                        $alt->where('n.durum', 'odeme_sozu')
                            ->whereNotNull('n.odeme_sozu_tarihi')
                            ->where('n.odeme_sozu_tarihi', '<', now())
                            ->whereNotIn('n.odeme_sozu_durumu', ['tutuldu', 'iptal']);
                    });
            })
            ->selectRaw("n.cari_id, COALESCE(plan.para_birimi, n.para_birimi, 'TRY') as para_birimi, COUNT(n.id) as ihlal_adedi")
            ->groupBy('n.cari_id', DB::raw("COALESCE(plan.para_birimi, n.para_birimi, 'TRY')"));

        $cariId = (int) ($filtreler['cari_id'] ?? 0);
        $kaynakTuru = trim((string) ($filtreler['kaynak_turu'] ?? ''));
        $planTuru = trim((string) ($filtreler['plan_turu'] ?? ''));
        $cariTuru = trim((string) ($filtreler['cari_turu'] ?? ''));
        $cariGrubuId = (int) ($filtreler['cari_grubu_id'] ?? 0);
        $paraBirimi = strtoupper(trim((string) ($filtreler['para_birimi'] ?? '')));

        if ($cariId > 0) {
            $query->where('n.cari_id', $cariId);
        }
        if ($kaynakTuru !== '') {
            $query->where('plan.kaynak_turu', $kaynakTuru);
        }
        if ($planTuru !== '') {
            $query->where('plan.plan_turu', $planTuru);
        }
        if ($cariTuru !== '') {
            $query->where('c.tur', $cariTuru);
        }
        if ($cariGrubuId > 0) {
            $query->where('c.cari_grubu_id', $cariGrubuId);
        }
        if ($paraBirimi !== '') {
            $query->whereRaw('COALESCE(plan.para_birimi, n.para_birimi, ?) = ?', ['TRY', $paraBirimi]);
        }

        return $query->get()
            ->mapWithKeys(fn (object $row): array => [
                (int) $row->cari_id.'|'.strtoupper((string) ($row->para_birimi ?: 'TRY')) => (int) $row->ihlal_adedi,
            ])
            ->all();
    }

    private function riskSkoruHesapla(float $acikToplam, float $gecikenToplam, int $gecikmeGunu, int $acikVadeAdedi, int $sozIhlaliAdedi): int
    {
        $skor = 0;
        if ($gecikenToplam > 0) {
            $skor += 25;
        }

        $skor += match (true) {
            $gecikmeGunu >= 90 => 30,
            $gecikmeGunu >= 60 => 24,
            $gecikmeGunu >= 30 => 18,
            $gecikmeGunu >= 7 => 10,
            $gecikmeGunu > 0 => 5,
            default => 0,
        };

        $gecikenOran = $acikToplam > 0 ? $gecikenToplam / $acikToplam : 0.0;
        $skor += match (true) {
            $gecikenOran >= 0.75 => 20,
            $gecikenOran >= 0.50 => 15,
            $gecikenOran >= 0.25 => 10,
            $gecikenOran > 0 => 5,
            default => 0,
        };

        $skor += match (true) {
            $acikVadeAdedi >= 5 => 10,
            $acikVadeAdedi >= 3 => 6,
            $acikVadeAdedi >= 2 => 3,
            default => 0,
        };

        $skor += min(16, $sozIhlaliAdedi * 8);
        $skor += match (true) {
            $acikToplam >= 50000 => 14,
            $acikToplam >= 10000 => 10,
            $acikToplam >= 5000 => 6,
            $acikToplam >= 1000 => 3,
            default => 0,
        };

        return min(100, $skor);
    }

    private function riskSeviyesi(int $skor): string
    {
        return match (true) {
            $skor >= 75 => 'kritik',
            $skor >= 55 => 'yuksek',
            $skor >= 35 => 'orta',
            default => 'normal',
        };
    }

    private function riskAksiyonu(int $skor, int $sozIhlaliAdedi, int $gecikmeGunu): string
    {
        if ($skor >= 75 || $sozIhlaliAdedi > 1) {
            return 'Yonetici onayli tahsilat aramasi ve yeni odeme plani';
        }
        if ($skor >= 55 || $gecikmeGunu >= 30) {
            return 'Bugun telefon aramasi ve yazili mutabakat';
        }
        if ($skor >= 35) {
            return 'WhatsApp/SMS hatirlatma ve takip notu';
        }

        return 'Standart vade takibi';
    }

    /**
     * @return array<string, string>
     */
    private function bosYaslandirmaSatiri(string $paraBirimi): array
    {
        return [
            'para_birimi' => $paraBirimi,
            'vadesi_gelmemis' => '0.00',
            'bugun' => '0.00',
            'geciken_1_30' => '0.00',
            'geciken_31_60' => '0.00',
            'geciken_61_90' => '0.00',
            'geciken_90_plus' => '0.00',
            'toplam' => '0.00',
        ];
    }

    /**
     * @param array<int, string> $decimalAlanlar
     * @return array<string, mixed>
     */
    private function raporSatiri(object $row, array $decimalAlanlar): array
    {
        $satir = (array) $row;
        foreach ($decimalAlanlar as $alan) {
            $satir[$alan] = $this->decimal($satir[$alan] ?? 0);
        }

        if (isset($satir['para_birimi'])) {
            $satir['para_birimi'] = strtoupper((string) ($satir['para_birimi'] ?: 'TRY'));
        }

        return $satir;
    }

    private function decimal(mixed $tutar): string
    {
        return number_format((float) $tutar, 2, '.', '');
    }

    private function decimalTopla(string $sol, string $sag): string
    {
        return bcadd($this->decimal($sol), $this->decimal($sag), 2);
    }

    /**
     * @return array<string, mixed>
     */
    private function takipNotuSatiri(object $row, bool $ajandaDurumuEkle): array
    {
        $satir = (array) $row;
        $satir['para_birimi'] = strtoupper((string) ($satir['para_birimi'] ?: 'TRY'));
        $satir['beklenen_tutar'] = $this->decimal($satir['beklenen_tutar'] ?? 0);
        $satir['odeme_sozu_tutari'] = $this->decimal($satir['odeme_sozu_tutari'] ?? 0);
        $satir['kalan_tutar'] = $this->decimal($satir['kalan_tutar'] ?? 0);

        if ($ajandaDurumuEkle) {
            $sonrakiTakip = filled($satir['sonraki_takip_tarihi'] ?? null)
                ? Carbon::parse((string) $satir['sonraki_takip_tarihi'])
                : null;
            $simdi = now();
            $satir['ajanda_durumu'] = match (true) {
                $sonrakiTakip === null => 'plansiz',
                $sonrakiTakip->lt($simdi) => 'gecikti',
                $sonrakiTakip->isSameDay($simdi) => 'bugun',
                default => 'yaklasan',
            };
            $satir['takip_gecikme_gunu'] = $sonrakiTakip && $sonrakiTakip->lt($simdi)
                ? (int) $sonrakiTakip->startOfDay()->diffInDays($simdi->copy()->startOfDay())
                : 0;
        }

        return $satir;
    }

    private function tahsilatOnceligi(int $gecikmeGunu, float $gecikenToplam, float $bugunToplam, int $acikVadeAdedi): string
    {
        if ($gecikenToplam > 0 && $gecikmeGunu >= 30) {
            return 'kritik';
        }

        if ($gecikenToplam > 0 || $acikVadeAdedi >= 3) {
            return 'yuksek';
        }

        if ($bugunToplam > 0) {
            return 'bugun';
        }

        return 'normal';
    }
}
