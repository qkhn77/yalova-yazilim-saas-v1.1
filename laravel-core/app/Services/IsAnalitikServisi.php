<?php

namespace App\Services;

use App\Models\Ecommerce\Odeme;
use App\Models\Ecommerce\Siparis;
use App\Models\Ecommerce\SiparisKalemi;
use App\Models\Muhasebe\StokKarti;
use App\Models\SistemOlayi;
use App\Muhasebe\Enumlar\HesapDurumu;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class IsAnalitikServisi
{
    /**
     * @return array<string, mixed>
     */
    public function olustur(int $firmaId): array
    {
        $cacheKey = sprintf('is_analitik:%d', $firmaId);

        /** @var array<string, mixed> $data */
        $data = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($firmaId): array {
            $now = Carbon::now();
            $todayStart = $now->copy()->startOfDay();
            $todayEnd = $now->copy()->endOfDay();
            $weekStart = $now->copy()->startOfWeek()->startOfDay();
            $monthStart = $now->copy()->startOfMonth()->startOfDay();
            $trend7Start = $now->copy()->subDays(6)->startOfDay();

            $todayCount = $this->siparisSayisi($firmaId, $todayStart, $todayEnd);
            $weekCount = $this->siparisSayisi($firmaId, $weekStart, $todayEnd);
            $monthCount = $this->siparisSayisi($firmaId, $monthStart, $todayEnd);

            $todayRevenueByCurrency = $this->ciroByCurrency($firmaId, $todayStart, $todayEnd);
            $weekRevenueByCurrency = $this->ciroByCurrency($firmaId, $weekStart, $todayEnd);
            $monthRevenueByCurrency = $this->ciroByCurrency($firmaId, $monthStart, $todayEnd);

            $paymentStats = $this->odemeOranlari($firmaId, $monthStart, $todayEnd);
            $cancelRate = $this->iptalOrani($firmaId, $monthStart, $todayEnd);

            return [
                'kpi' => [
                    'bugun_siparis' => $todayCount,
                    'hafta_siparis' => $weekCount,
                    'ay_siparis' => $monthCount,
                    'bugun_ciro_pb' => $todayRevenueByCurrency,
                    'hafta_ciro_pb' => $weekRevenueByCurrency,
                    'ay_ciro_pb' => $monthRevenueByCurrency,
                    'odeme_basarili_orani' => $paymentStats['basarili_oran'],
                    'odeme_basarisiz_orani' => $paymentStats['basarisiz_oran'],
                    'iptal_orani' => $cancelRate,
                ],
                'trend' => [
                    'siparis_7' => $this->gunlukSiparisTrend($firmaId, $trend7Start, $todayEnd),
                    'odeme_dagilim' => $paymentStats['dagilim'],
                ],
                'listeler' => [
                    'en_cok_satanlar' => $this->enCokSatanlar($firmaId),
                    'en_cok_goruntulenenler' => $this->enCokGoruntulenenler($firmaId),
                    'kritik_stoklar' => $this->kritikStoklar($firmaId),
                    'sorunlu_olaylar' => $this->sorunluOlaylar($firmaId),
                ],
                'operasyon' => [
                    'odeme_bekleyen' => $this->odemeBekleyenSayisi($firmaId),
                    'terk_edilmis' => $this->terkEdilmisSayisi($firmaId),
                    'negatif_stok' => $this->negatifStokSayisi($firmaId),
                    'rezerv_sorunlu' => $this->rezervSorunluSayisi($firmaId),
                ],
            ];
        });

        return $data;
    }

    private function siparisSayisi(int $firmaId, Carbon $start, Carbon $end): int
    {
        return Siparis::query()
            ->where('firma_id', $firmaId)
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }

    /**
     * @return array<string, string>
     */
    private function ciroByCurrency(int $firmaId, Carbon $start, Carbon $end): array
    {
        /** @var array<int, object{para_birimi: string|null, toplam: string|int|float}> $rows */
        $rows = Siparis::query()
            ->select([
                DB::raw("COALESCE(NULLIF(para_birimi, ''), 'TRY') as para_birimi"),
                DB::raw('SUM(genel_toplam) as toplam'),
            ])
            ->where('firma_id', $firmaId)
            ->whereIn('durum', $this->ciroyaDahilDurumlar())
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('para_birimi')
            ->get()
            ->all();

        $out = [];
        foreach ($rows as $row) {
            $pb = strtoupper((string) ($row->para_birimi ?: 'TRY'));
            $out[$pb] = number_format((float) $row->toplam, 2, '.', '');
        }

        ksort($out);

        return $out;
    }

    /**
     * @return array{basarili_oran: float, basarisiz_oran: float, dagilim: array<string, int>}
     */
    private function odemeOranlari(int $firmaId, Carbon $start, Carbon $end): array
    {
        $base = Odeme::query()
            ->whereHas('siparis', fn ($q) => $q->where('firma_id', $firmaId))
            ->whereBetween('created_at', [$start, $end]);

        $all = (clone $base)->count();
        $ok = (clone $base)->where('durum', Odeme::DURUM_BASARILI)->count();
        $fail = (clone $base)->where('durum', Odeme::DURUM_BASARISIZ)->count();
        $pending = (clone $base)->where('durum', Odeme::DURUM_BEKLEMEDE)->count();
        $cancel = (clone $base)->where('durum', Odeme::DURUM_IPTAL)->count();

        if ($all === 0) {
            return [
                'basarili_oran' => 0.0,
                'basarisiz_oran' => 0.0,
                'dagilim' => ['basarili' => 0, 'basarisiz' => 0, 'beklemede' => 0, 'iptal' => 0],
            ];
        }

        return [
            'basarili_oran' => round(($ok / $all) * 100, 2),
            'basarisiz_oran' => round(($fail / $all) * 100, 2),
            'dagilim' => ['basarili' => $ok, 'basarisiz' => $fail, 'beklemede' => $pending, 'iptal' => $cancel],
        ];
    }

    private function iptalOrani(int $firmaId, Carbon $start, Carbon $end): float
    {
        $base = Siparis::query()
            ->where('firma_id', $firmaId)
            ->whereBetween('created_at', [$start, $end]);
        $all = (clone $base)->count();
        if ($all === 0) {
            return 0.0;
        }

        $cancel = (clone $base)->whereIn('durum', $this->iptalDurumlari())->count();

        return round(($cancel / $all) * 100, 2);
    }

    /**
     * @return array<int, array{gun:string, adet:int}>
     */
    private function gunlukSiparisTrend(int $firmaId, Carbon $start, Carbon $end): array
    {
        /** @var array<int, object{gun:string, adet:int|string}> $rows */
        $rows = Siparis::query()
            ->select([
                DB::raw('DATE(created_at) as gun'),
                DB::raw('COUNT(*) as adet'),
            ])
            ->where('firma_id', $firmaId)
            ->whereBetween('created_at', [$start, $end])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy(DB::raw('DATE(created_at)'))
            ->get()
            ->all();

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row->gun] = (int) $row->adet;
        }

        $out = [];
        $cursor = $start->copy();
        while ($cursor <= $end) {
            $key = $cursor->toDateString();
            $out[] = ['gun' => $key, 'adet' => (int) ($map[$key] ?? 0)];
            $cursor->addDay();
        }

        return $out;
    }

    /**
     * @return array<string, array<int, array{gun:string, ciro:string}>>
     */
    private function gunlukCiroTrendByCurrency(int $firmaId, Carbon $start, Carbon $end): array
    {
        /** @var array<int, object{gun:string, para_birimi:string|null, toplam:string|int|float}> $rows */
        $rows = Siparis::query()
            ->select([
                DB::raw('DATE(created_at) as gun'),
                DB::raw("COALESCE(NULLIF(para_birimi, ''), 'TRY') as para_birimi"),
                DB::raw('SUM(genel_toplam) as toplam'),
            ])
            ->where('firma_id', $firmaId)
            ->whereIn('durum', $this->ciroyaDahilDurumlar())
            ->whereBetween('created_at', [$start, $end])
            ->groupBy(DB::raw('DATE(created_at)'), 'para_birimi')
            ->orderBy(DB::raw('DATE(created_at)'))
            ->get()
            ->all();

        $currencies = [];
        $map = [];
        foreach ($rows as $row) {
            $pb = strtoupper((string) ($row->para_birimi ?: 'TRY'));
            $currencies[$pb] = true;
            $map[$pb][(string) $row->gun] = number_format((float) $row->toplam, 2, '.', '');
        }
        ksort($currencies);

        $out = [];
        foreach (array_keys($currencies) as $pb) {
            $cursor = $start->copy();
            $out[$pb] = [];
            while ($cursor <= $end) {
                $key = $cursor->toDateString();
                $out[$pb][] = ['gun' => $key, 'ciro' => (string) ($map[$pb][$key] ?? '0.00')];
                $cursor->addDay();
            }
        }

        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sonSiparisler(int $firmaId): array
    {
        return Siparis::query()
            ->where('firma_id', $firmaId)
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'siparis_no', 'musteri_ad_soyad', 'durum', 'genel_toplam', 'para_birimi', 'created_at'])
            ->toArray();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function enCokSatanlar(int $firmaId): array
    {
        /** @var array<int, object{stok_karti_id:int|null, urun_adi_snapshot:string|null, toplam_miktar:string|int|float, toplam_tutar:string|int|float}> $rows */
        $rows = SiparisKalemi::query()
            ->join('siparisler', 'siparisler.id', '=', 'siparis_kalemleri.siparis_id')
            ->where('siparisler.firma_id', $firmaId)
            ->whereIn('siparisler.durum', $this->ciroyaDahilDurumlar())
            ->select([
                'siparis_kalemleri.stok_karti_id',
                DB::raw('MAX(siparis_kalemleri.urun_adi_snapshot) as urun_adi_snapshot'),
                DB::raw('SUM(siparis_kalemleri.miktar) as toplam_miktar'),
                DB::raw('SUM(siparis_kalemleri.satir_toplami) as toplam_tutar'),
            ])
            ->groupBy('siparis_kalemleri.stok_karti_id')
            ->orderByDesc(DB::raw('SUM(siparis_kalemleri.miktar)'))
            ->limit(10)
            ->get()
            ->all();

        return array_map(fn ($r): array => [
            'stok_karti_id' => $r->stok_karti_id,
            'urun_adi' => (string) ($r->urun_adi_snapshot ?: 'Bilinmeyen urun'),
            'toplam_miktar' => (float) $r->toplam_miktar,
            'toplam_tutar' => number_format((float) $r->toplam_tutar, 2, '.', ''),
        ], $rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function enCokGoruntulenenler(int $firmaId): array
    {
        return StokKarti::query()
            ->withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->where('durum', HesapDurumu::Aktif)
            ->orderByDesc('goruntulenme_sayisi')
            ->limit(10)
            ->get(['id', 'ad', 'kod', 'goruntulenme_sayisi', 'stok_miktari', 'minimum_stok'])
            ->map(fn (StokKarti $s): array => [
                'stok_karti_id' => (int) $s->id,
                'ad' => (string) $s->ad,
                'kod' => (string) $s->kod,
                'goruntulenme_sayisi' => (int) ($s->goruntulenme_sayisi ?? 0),
                'stok_miktari' => (float) ($s->stok_miktari ?? 0),
                'minimum_stok' => (float) ($s->minimum_stok ?? 0),
                'yuksek_ilgi_dusuk_stok' => ((int) ($s->goruntulenme_sayisi ?? 0) >= 10)
                    && ((float) ($s->stok_miktari ?? 0) <= (float) ($s->minimum_stok ?? 0)),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function kritikStoklar(int $firmaId): array
    {
        return StokKarti::query()
            ->withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->where('durum', HesapDurumu::Aktif)
            ->where('stok_takip', true)
            ->whereRaw('CAST(stok_miktari AS DECIMAL(18,4)) <= CAST(minimum_stok AS DECIMAL(18,4))')
            ->orderBy('stok_miktari')
            ->limit(10)
            ->get(['id', 'ad', 'kod', 'stok_miktari', 'minimum_stok'])
            ->toArray();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sorunluOlaylar(int $firmaId): array
    {
        /** @var array<int, object{tip:string, adet:int|string}> $rows */
        $rows = SistemOlayi::query()
            ->withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->whereIn('seviye', ['warning', 'error', 'critical'])
            ->where('created_at', '>=', now()->subDays(30))
            ->select(['tip', DB::raw('COUNT(*) as adet')])
            ->groupBy('tip')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit(10)
            ->get()
            ->all();

        return array_map(fn ($r): array => [
            'tip' => (string) $r->tip,
            'adet' => (int) $r->adet,
        ], $rows);
    }

    private function odemeBekleyenSayisi(int $firmaId): int
    {
        return Siparis::query()
            ->where('firma_id', $firmaId)
            ->whereIn('durum', $this->onayBekleyenDurumlar())
            ->count();
    }

    private function terkEdilmisSayisi(int $firmaId): int
    {
        return Siparis::query()
            ->where('firma_id', $firmaId)
            ->whereIn('durum', $this->onayBekleyenDurumlar())
            ->whereNotNull('odeme_suresi_bitis_at')
            ->where('odeme_suresi_bitis_at', '<', now())
            ->count();
    }

    /**
     * @return list<string>
     */
    private function ciroyaDahilDurumlar(): array
    {
        return [
            Siparis::DURUM_ONAYLANDI_YENI,
            Siparis::DURUM_GONDERILDI,
            Siparis::DURUM_TESLIM_EDILDI,
            Siparis::DURUM_ODENDI,
            Siparis::DURUM_HAZIRLANIYOR,
            Siparis::DURUM_KARGOLANDI,
            Siparis::DURUM_TAMAMLANDI,
        ];
    }

    /**
     * @return list<string>
     */
    private function iptalDurumlari(): array
    {
        return [
            Siparis::DURUM_IPTAL_EDILDI,
            Siparis::DURUM_IPTAL,
        ];
    }

    /**
     * @return list<string>
     */
    private function onayBekleyenDurumlar(): array
    {
        return [
            Siparis::DURUM_ONAY_BEKLIYOR,
            Siparis::DURUM_ODEME_BEKLENIYOR,
        ];
    }

    private function negatifStokSayisi(int $firmaId): int
    {
        return StokKarti::query()
            ->withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->where('durum', HesapDurumu::Aktif)
            ->where('negative_flag', true)
            ->count();
    }

    private function rezervSorunluSayisi(int $firmaId): int
    {
        return StokKarti::query()
            ->withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->where('durum', HesapDurumu::Aktif)
            ->where('stok_takip', true)
            ->whereRaw('CAST(rezerve_miktar AS DECIMAL(18,4)) > CAST(stok_miktari AS DECIMAL(18,4))')
            ->count();
    }
}
