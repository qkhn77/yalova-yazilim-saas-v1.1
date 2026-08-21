<?php

namespace App\Muhasebe\Servisler;

use App\Models\Firma;
use App\Models\Muhasebe\CariHareketi;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\FaturaFinansKapama;
use App\Models\Muhasebe\FaturaKalemi;
use App\Models\Muhasebe\StokHareketi;
use App\Muhasebe\Enumlar\CariHareketBelgeTuru;
use App\Muhasebe\Enumlar\CariHareketDurumu;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Muhasebe\Enumlar\StokBelgeTuru;
use App\Muhasebe\Enumlar\StokHareketDurumu;
use App\Muhasebe\Enumlar\StokHareketIslemTuru;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MuhasebeSistemDogrulamaServisi
{
    /**
     * @return array<int, array{kod: string, detay: string, firma_id?: int, kaynak_id?: int}>
     */
    public function sistemTutarlilikKontrolu(?int $firmaId = null, bool $kayitLogla = true): array
    {
        $hatalar = [];
        $firmaIds = $firmaId !== null
            ? [$firmaId]
            : Firma::query()->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach ($firmaIds as $fid) {
            $hatalar = array_merge($hatalar, $this->kontrolFaturaCari($fid));
            $hatalar = array_merge($hatalar, $this->kontrolFaturaStok($fid));
            $hatalar = array_merge($hatalar, $this->kontrolFinansFaturaKapama($fid));
            $hatalar = array_merge($hatalar, $this->kontrolStokHareketFaturaCapraz($fid));
            $hatalar = array_merge($hatalar, $this->kontrolFinansHareketleri($fid));
        }

        if ($kayitLogla) {
            foreach ($hatalar as $h) {
                Log::channel($this->logKanali())->warning('muhasebe.sistem.tutarsizlik', $h);
            }
        }

        return $hatalar;
    }

    /**
     * Finans hareketinin kaynak modüller ve alt muhasebe satırlarıyla
     * tutarlılığını kontrol eder. Bu kontrol salt okunurdur; onarım işlemi
     * kaynak modülün kendi servisi üzerinden yapılmalıdır.
     *
     * @return array<int, array{kod: string, detay: string, firma_id: int, kaynak_id: int}>
     */
    private function kontrolFinansHareketleri(int $firmaId): array
    {
        $hatalar = [];
        $ekle = static function (array &$liste, string $kod, string $detay, int $firmaId, int $kaynakId): void {
            $liste[] = compact('kod', 'detay') + ['firma_id' => $firmaId, 'kaynak_id' => $kaynakId];
        };

        $teknik = DB::table('teknik_servis_tahsilatlari as ts')
            ->join('finans_hareketleri as f', 'f.id', '=', 'ts.finans_hareketi_id')
            ->where('ts.firma_id', $firmaId)
            ->where('ts.durum', 'aktif')
            ->where('f.durum', '!=', 'aktif')
            ->limit(500)
            ->pluck('ts.id');
        foreach ($teknik as $id) {
            $ekle($hatalar, 'teknik_tahsilat_finans_iptal', 'Aktif teknik servis tahsilatı iptal edilmiş finans hareketine bağlı.', $firmaId, (int) $id);
        }

        $restoran = DB::table('restoran_adisyon_tahsilatlari as rt')
            ->join('finans_hareketleri as f', 'f.id', '=', 'rt.finans_hareketi_id')
            ->where('rt.firma_id', $firmaId)
            ->where('rt.durum', 'aktif')
            ->where('f.durum', '!=', 'aktif')
            ->limit(500)
            ->pluck('rt.id');
        foreach ($restoran as $id) {
            $ekle($hatalar, 'restoran_tahsilat_finans_iptal', 'Aktif restoran tahsilatı iptal edilmiş finans hareketine bağlı.', $firmaId, (int) $id);
        }

        $eslesmeler = DB::table('muhasebe_alacak_tahsilat_eslesmeleri as e')
            ->join('finans_hareketleri as f', 'f.id', '=', 'e.finans_hareketi_id')
            ->where('e.firma_id', $firmaId)
            ->where('f.durum', '!=', 'aktif')
            ->limit(500)
            ->pluck('e.id');
        foreach ($eslesmeler as $id) {
            $ekle($hatalar, 'alacak_eslesme_finans_iptal', 'Alacak eşleşmesi iptal edilmiş finans hareketini kullanıyor.', $firmaId, (int) $id);
        }

        $negatif = DB::table('finans_hareketleri')
            ->where('firma_id', $firmaId)
            ->where('durum', 'aktif')
            ->where('tutar', '<=', 0)
            ->limit(500)
            ->pluck('id');
        foreach ($negatif as $id) {
            $ekle($hatalar, 'finans_tutar_gecersiz', 'Aktif finans hareketinin tutarı sıfırdan büyük olmalıdır.', $firmaId, (int) $id);
        }

        $acikOrijinaller = DB::table('finans_hareketleri as ters')
            ->join('finans_hareketleri as orijinal', 'orijinal.id', '=', 'ters.iptal_edilen_hareket_id')
            ->where('ters.firma_id', $firmaId)
            ->where('ters.durum', 'aktif')
            ->where('orijinal.durum', 'aktif')
            ->limit(500)
            ->pluck('ters.id');
        foreach ($acikOrijinaller as $id) {
            $ekle($hatalar, 'ters_kayit_orijinali_aktif', 'Aktif ters kaydın orijinal finans hareketi hâlâ aktif.', $firmaId, (int) $id);
        }

        return $hatalar;
    }

    /**
     * @return array<int, array{kod: string, detay: string, firma_id?: int, kaynak_id?: int}>
     */
    private function kontrolFaturaCari(int $firmaId): array
    {
        $hatalar = [];
        $faturalar = Fatura::query()
            ->withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->where('durum', FaturaDurumu::Onayli)
            ->get(['id', 'firma_id', 'tur', 'cari_id']);

        $kontrolEdilecekFaturaIds = $faturalar
            ->filter(function (Fatura $fatura): bool {
                $tur = $fatura->tur instanceof FaturaTuru ? $fatura->tur : FaturaTuru::from((string) $fatura->tur);

                return $tur->kayitUretirMi()
                    && $fatura->cari_id !== null
                    && $tur->cariYonu() !== 'yok';
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($kontrolEdilecekFaturaIds === []) {
            return [];
        }

        $aktifCariHareketliFaturaIds = CariHareketi::query()
            ->withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->where('belge_turu', CariHareketBelgeTuru::Fatura)
            ->whereIn('belge_id', $kontrolEdilecekFaturaIds)
            ->where('durum', CariHareketDurumu::Aktif)
            ->pluck('belge_id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        foreach ($faturalar as $fatura) {
            $tur = $fatura->tur instanceof FaturaTuru ? $fatura->tur : FaturaTuru::from((string) $fatura->tur);
            if (! $tur->kayitUretirMi()) {
                continue;
            }
            if ($fatura->cari_id === null) {
                continue;
            }
            if ($tur->cariYonu() === 'yok') {
                continue;
            }
            if (! $aktifCariHareketliFaturaIds->has((int) $fatura->id)) {
                $hatalar[] = [
                    'kod' => 'fatura_cari_eksik',
                    'detay' => 'Onaylı fatura için aktif cari hareketi yok.',
                    'firma_id' => $firmaId,
                    'kaynak_id' => (int) $fatura->id,
                ];
            }
        }

        return $hatalar;
    }

    /**
     * Fatura onayı ile aynı stok üretim kuralı ({@see FaturaIslemServisi::stokIslemTuruFaturadan}).
     *
     * @return array<int, array{kod: string, detay: string, firma_id?: int, kaynak_id?: int}>
     */
    private function kontrolFaturaStok(int $firmaId): array
    {
        $hatalar = [];
        $faturalar = Fatura::query()
            ->withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->where('durum', FaturaDurumu::Onayli)
            ->get(['id', 'firma_id', 'tur']);

        $stokKontrolFaturaIds = $faturalar
            ->filter(function (Fatura $fatura): bool {
                $tur = $fatura->tur instanceof FaturaTuru ? $fatura->tur : FaturaTuru::from((string) $fatura->tur);

                return $tur->kayitUretirMi() && $this->stokIslemTuruFaturadan($tur) !== null;
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($stokKontrolFaturaIds === []) {
            return [];
        }

        $fizikselKalemliFaturaIds = FaturaKalemi::query()
            ->withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->whereIn('fatura_id', $stokKontrolFaturaIds)
            ->whereNotNull('stok_id')
            ->where(function ($query): void {
                $query->where('hizmet_mi', false)->orWhereNull('hizmet_mi');
            })
            ->distinct()
            ->pluck('fatura_id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        if ($fizikselKalemliFaturaIds->isEmpty()) {
            return [];
        }

        $aktifStokHareketliFaturaIds = StokHareketi::query()
            ->where('firma_id', $firmaId)
            ->where('belge_turu', StokBelgeTuru::Fatura)
            ->whereIn('belge_id', $stokKontrolFaturaIds)
            ->where('durum', StokHareketDurumu::Aktif)
            ->distinct()
            ->pluck('belge_id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        foreach ($faturalar as $fatura) {
            $tur = $fatura->tur instanceof FaturaTuru ? $fatura->tur : FaturaTuru::from((string) $fatura->tur);
            if (! $tur->kayitUretirMi()) {
                continue;
            }
            $stokIslem = $this->stokIslemTuruFaturadan($tur);
            if ($stokIslem === null) {
                continue;
            }
            if (! $fizikselKalemliFaturaIds->has((int) $fatura->id)) {
                continue;
            }
            if (! $aktifStokHareketliFaturaIds->has((int) $fatura->id)) {
                $hatalar[] = [
                    'kod' => 'fatura_stok_eksik',
                    'detay' => 'Onaylı fatura için stok kalemi varken aktif stok hareketi yok.',
                    'firma_id' => $firmaId,
                    'kaynak_id' => (int) $fatura->id,
                ];
            }
        }

        return $hatalar;
    }

    private function stokIslemTuruFaturadan(FaturaTuru $tur): ?StokHareketIslemTuru
    {
        return match ($tur->kanonik()) {
            FaturaTuru::Giden => StokHareketIslemTuru::Satis,
            FaturaTuru::Gelen => StokHareketIslemTuru::Alis,
            FaturaTuru::Gider => null,
            FaturaTuru::SatisIadesi => StokHareketIslemTuru::SatisIadesi,
            FaturaTuru::AlisIadesi => StokHareketIslemTuru::AlisIadesi,
            default => null,
        };
    }

    /**
     * @return array<int, array{kod: string, detay: string, firma_id?: int, kaynak_id?: int}>
     */
    private function kontrolFinansFaturaKapama(int $firmaId): array
    {
        $hatalar = [];
        $kapamalar = FaturaFinansKapama::query()
            ->withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->select(['id', 'firma_id', 'fatura_id', 'finans_hareket_id'])
            ->with([
                'fatura' => fn ($q) => $q->withoutGlobalScopes()->select(['id', 'firma_id', 'cari_id']),
                'finansHareketi' => fn ($q) => $q->withoutGlobalScopes()->select(['id', 'firma_id', 'cari_id']),
            ])
            ->get();

        foreach ($kapamalar as $k) {
            $fatura = $k->fatura;
            $finans = $k->finansHareketi;
            if ($fatura === null) {
                $hatalar[] = [
                    'kod' => 'kapama_fatura_yok',
                    'detay' => 'Fatura kapama kaydı için fatura bulunamadı.',
                    'firma_id' => $firmaId,
                    'kaynak_id' => (int) $k->id,
                ];

                continue;
            }
            if ($finans === null) {
                $hatalar[] = [
                    'kod' => 'kapama_finans_yok',
                    'detay' => 'Fatura kapama kaydı için finans hareketi bulunamadı.',
                    'firma_id' => $firmaId,
                    'kaynak_id' => (int) $k->id,
                ];

                continue;
            }
            if ((int) $fatura->firma_id !== $firmaId || (int) $k->firma_id !== $firmaId) {
                $hatalar[] = [
                    'kod' => 'kapama_firma_fatura',
                    'detay' => 'Kapama / fatura firma_id tutarsız.',
                    'firma_id' => $firmaId,
                    'kaynak_id' => (int) $k->id,
                ];
            }
            if ((int) $finans->firma_id !== $firmaId || (int) $k->firma_id !== (int) $finans->firma_id) {
                $hatalar[] = [
                    'kod' => 'kapama_firma_finans',
                    'detay' => 'Kapama / finans firma_id tutarsız.',
                    'firma_id' => $firmaId,
                    'kaynak_id' => (int) $k->id,
                ];
            }
            if ($fatura->cari_id !== null && $finans->cari_id !== null
                && (int) $fatura->cari_id !== (int) $finans->cari_id) {
                $hatalar[] = [
                    'kod' => 'kapama_cari_fatura_finans',
                    'detay' => 'Kapamada fatura cari_id ile finans cari_id eşleşmiyor.',
                    'firma_id' => $firmaId,
                    'kaynak_id' => (int) $k->id,
                ];
            }
        }

        return $hatalar;
    }

    /**
     * @return array<int, array{kod: string, detay: string, firma_id?: int, kaynak_id?: int}>
     */
    private function kontrolStokHareketFaturaCapraz(int $firmaId): array
    {
        $hatalar = [];
        $hareketler = StokHareketi::query()
            ->withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->where('belge_turu', StokBelgeTuru::Fatura)
            ->where('durum', StokHareketDurumu::Aktif)
            ->get(['id', 'firma_id', 'belge_id']);

        $faturaIds = $hareketler
            ->pluck('belge_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $faturalar = $faturaIds === []
            ? collect()
            : Fatura::query()
                ->withoutGlobalScopes()
                ->whereIn('id', $faturaIds)
                ->get(['id', 'firma_id'])
                ->keyBy('id');

        foreach ($hareketler as $h) {
            $fatura = $faturalar->get((int) $h->belge_id);
            if ($fatura === null) {
                $hatalar[] = [
                    'kod' => 'stok_hareket_fatura_yok',
                    'detay' => 'Stok hareketinin belge_id faturası bulunamadı.',
                    'firma_id' => $firmaId,
                    'kaynak_id' => (int) $h->id,
                ];

                continue;
            }
            if ((int) $fatura->firma_id !== $firmaId) {
                $hatalar[] = [
                    'kod' => 'stok_hareket_fatura_firma',
                    'detay' => 'Stok hareketi ile fatura firma_id uyuşmuyor.',
                    'firma_id' => $firmaId,
                    'kaynak_id' => (int) $h->id,
                ];
            }
        }

        return $hatalar;
    }

    private function logKanali(): string
    {
        return (string) config('muhasebe.sistem.log_channel', config('muhasebe.stok.log_channel', 'muhasebe'));
    }
}
