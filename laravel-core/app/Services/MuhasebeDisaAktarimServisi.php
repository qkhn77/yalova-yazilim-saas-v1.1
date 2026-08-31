<?php

namespace App\Services;

use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\Masraf;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Servisler\CariEkstreServisi;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MuhasebeDisaAktarimServisi
{
    /**
     * @return array<int, array<string, scalar|null>>
     */
    public function faturaListesi(int $firmaId, Carbon $baslangic, Carbon $bitis): array
    {
        return Fatura::query()
            ->where('firma_id', $firmaId)
            ->whereBetween('tarih', [$baslangic->copy()->startOfDay(), $bitis->copy()->endOfDay()])
            ->orderBy('tarih')
            ->orderBy('id')
            ->get(['fatura_no', 'tarih', 'tur', 'durum', 'odeme_durumu', 'para_birimi', 'ara_toplam', 'kdv_toplam', 'tevkifat_orani', 'genel_toplam', 'odenecek_tutar', 'odendi_tutari', 'acik_tutar', 'cari_id'])
            ->map(fn (Fatura $f): array => [
                'Fatura No' => (string) ($f->fatura_no ?? ''),
                'Tarih' => optional($f->tarih)->format('Y-m-d'),
                'Tur' => (string) $f->tur->value,
                'Durum' => (string) $f->durum->value,
                'Odeme Durumu' => (string) ($f->odeme_durumu ?? ''),
                'Para Birimi' => (string) ($f->para_birimi ?? 'TRY'),
                'Ara Toplam' => (string) ($f->ara_toplam ?? '0.00'),
                'KDV Toplam' => (string) ($f->kdv_toplam ?? '0.00'),
                'Tevkifat Orani' => (string) ($f->tevkifat_orani ?? '0.00'),
                'Genel Toplam' => (string) ($f->genel_toplam ?? '0.00'),
                'Odenecek Tutar' => (string) ($f->odenecek_tutar ?? '0.00'),
                'Odendi Tutari' => (string) ($f->odendi_tutari ?? '0.00'),
                'Acik Tutar' => (string) ($f->acik_tutar ?? '0.00'),
                'Cari ID' => (int) ($f->cari_id ?? 0),
            ])->all();
    }

    /**
     * @return array<int, array<string, scalar|null>>
     */
    public function acikFaturalar(int $firmaId): array
    {
        return Fatura::query()
            ->where('firma_id', $firmaId)
            ->where('durum', FaturaDurumu::Onayli)
            ->whereRaw('CAST(acik_tutar AS DECIMAL(18,4)) > 0')
            ->orderBy('vade_tarihi')
            ->orderBy('id')
            ->get(['fatura_no', 'tarih', 'vade_tarihi', 'para_birimi', 'genel_toplam', 'odendi_tutari', 'acik_tutar', 'cari_id'])
            ->map(fn (Fatura $f): array => [
                'Fatura No' => (string) ($f->fatura_no ?? ''),
                'Tarih' => optional($f->tarih)->format('Y-m-d'),
                'Vade Tarihi' => optional($f->vade_tarihi)->format('Y-m-d'),
                'Para Birimi' => (string) ($f->para_birimi ?? 'TRY'),
                'Genel Toplam' => (string) ($f->genel_toplam ?? '0.00'),
                'Odendi Tutari' => (string) ($f->odendi_tutari ?? '0.00'),
                'Acik Tutar' => (string) ($f->acik_tutar ?? '0.00'),
                'Cari ID' => (int) ($f->cari_id ?? 0),
            ])->all();
    }

    /**
     * @return array<int, array<string, scalar|null>>
     */
    public function cariEkstre(int $firmaId, int $cariId, string $paraBirimi, Carbon $baslangic, Carbon $bitis): array
    {
        $rapor = app(CariEkstreServisi::class)->ekstre($firmaId, $cariId, $paraBirimi, $baslangic, $bitis);
        /** @var Collection<int, array<string, mixed>> $satirlar */
        $satirlar = $rapor['satirlar'];

        return $satirlar->map(static function (array $row): array {
            $h = $row['hareket'];

            return [
                'Tarih' => optional($h->islem_tarihi)->format('Y-m-d H:i:s'),
                'Belge Turu' => (string) $h->belge_turu->value,
                'Belge ID' => (int) $h->belge_id,
                'Borc' => (string) ($h->borc ?? '0.00'),
                'Alacak' => (string) ($h->alacak ?? '0.00'),
                'Net' => (string) ($row['net'] ?? '0.00'),
                'Bakiye Sonrasi' => (string) ($row['bakiye_sonrasi'] ?? '0.00'),
                'FIFO Acik' => (bool) ($row['fifo_acik'] ?? false) ? 'evet' : 'hayir',
                'Kalan Tutar' => (string) ($row['kalan_tutar'] ?? '0.00'),
                'Aciklama' => (string) ($h->aciklama ?? ''),
            ];
        })->all();
    }

    /**
     * @return array<int, array<string, scalar|null>>
     */
    public function gelirGiderOzeti(int $firmaId, Carbon $baslangic, Carbon $bitis): array
    {
        /** @var array<int, object{para_birimi:string|null, gelir:string|int|float, gider:string|int|float, fatura_adedi:int|string, gelir_fatura_adedi:int|string, gider_fatura_adedi:int|string}> $faturaRows */
        $faturaRows = Fatura::query()
            ->select([
                DB::raw("COALESCE(NULLIF(para_birimi, ''), 'TRY') as para_birimi"),
                DB::raw("SUM(CASE WHEN tur IN ('giden','proforma') THEN genel_toplam ELSE 0 END) as gelir"),
                DB::raw("SUM(CASE WHEN tur IN ('gelen','gelen_fatura','gider','gider_faturasi') THEN genel_toplam ELSE 0 END) as gider"),
                DB::raw('COUNT(*) as fatura_adedi'),
                DB::raw("SUM(CASE WHEN tur IN ('giden','proforma') THEN 1 ELSE 0 END) as gelir_fatura_adedi"),
                DB::raw("SUM(CASE WHEN tur IN ('gelen','gelen_fatura','gider','gider_faturasi') THEN 1 ELSE 0 END) as gider_fatura_adedi"),
            ])
            ->where('firma_id', $firmaId)
            ->where('durum', FaturaDurumu::Onayli)
            ->whereBetween('tarih', [$baslangic->copy()->startOfDay(), $bitis->copy()->endOfDay()])
            ->groupBy('para_birimi')
            ->get()
            ->all();

        $sonuclar = [];
        foreach ($faturaRows as $row) {
            $paraBirimi = strtoupper((string) ($row->para_birimi ?: 'TRY'));
            $sonuclar[$paraBirimi] = [
                'Para Birimi' => $paraBirimi,
                'Fatura Adedi' => (int) ($row->fatura_adedi ?? 0),
                'Gelir Fatura Adedi' => (int) ($row->gelir_fatura_adedi ?? 0),
                'Gider Fatura Adedi' => (int) ($row->gider_fatura_adedi ?? 0),
                'Masraf Adedi' => 0,
                'Gelir Toplam' => bcadd((string) ($row->gelir ?? '0'), '0', 2),
                'Gider Toplam' => bcadd((string) ($row->gider ?? '0'), '0', 2),
            ];
        }

        /** @var array<int, object{para_birimi:string|null, gider:string|int|float, masraf_adedi:int|string}> $masrafRows */
        $masrafRows = Masraf::query()
            ->select([
                DB::raw("COALESCE(NULLIF(para_birimi, ''), 'TRY') as para_birimi"),
                DB::raw('SUM(tutar) as gider'),
                DB::raw('COUNT(*) as masraf_adedi'),
            ])
            ->where('firma_id', $firmaId)
            ->where('durum', Masraf::DURUM_AKTIF)
            ->whereBetween('tarih', [$baslangic->toDateString(), $bitis->toDateString()])
            ->groupBy('para_birimi')
            ->get()
            ->all();

        foreach ($masrafRows as $row) {
            $paraBirimi = strtoupper((string) ($row->para_birimi ?: 'TRY'));
            $sonuclar[$paraBirimi] ??= [
                'Para Birimi' => $paraBirimi,
                'Fatura Adedi' => 0,
                'Gelir Fatura Adedi' => 0,
                'Gider Fatura Adedi' => 0,
                'Masraf Adedi' => 0,
                'Gelir Toplam' => '0.00',
                'Gider Toplam' => '0.00',
            ];
            $sonuclar[$paraBirimi]['Masraf Adedi'] += (int) ($row->masraf_adedi ?? 0);
            $sonuclar[$paraBirimi]['Gider Toplam'] = bcadd(
                (string) $sonuclar[$paraBirimi]['Gider Toplam'],
                (string) ($row->gider ?? '0'),
                2,
            );
        }

        return array_values(array_map(static function (array $row): array {
            $row['Net'] = bcsub((string) $row['Gelir Toplam'], (string) $row['Gider Toplam'], 2);

            return $row;
        }, $sonuclar));
    }

    /**
     * @return array<int, array<string, scalar|null>>
     */
    public function kdvOzeti(int $firmaId, Carbon $baslangic, Carbon $bitis): array
    {
        /** @var array<int, object{para_birimi:string|null, hesaplanan_kdv:string|int|float, tevkifat_orani:string|int|float}> $rows */
        $rows = Fatura::query()
            ->select([
                DB::raw("COALESCE(NULLIF(para_birimi, ''), 'TRY') as para_birimi"),
                DB::raw('SUM(kdv_toplam) as hesaplanan_kdv'),
                DB::raw('AVG(tevkifat_orani) as tevkifat_orani'),
            ])
            ->where('firma_id', $firmaId)
            ->where('durum', FaturaDurumu::Onayli)
            ->whereBetween('tarih', [$baslangic->copy()->startOfDay(), $bitis->copy()->endOfDay()])
            ->groupBy('para_birimi')
            ->get()
            ->all();

        return array_map(static fn ($r): array => [
            'Para Birimi' => strtoupper((string) ($r->para_birimi ?: 'TRY')),
            'Hesaplanan KDV Toplam' => number_format((float) $r->hesaplanan_kdv, 2, '.', ''),
            'Ortalama Tevkifat Orani' => number_format((float) $r->tevkifat_orani, 2, '.', ''),
        ], $rows);
    }

    /**
     * @return array<int, int>
     */
    public function firmaCariIdleri(int $firmaId): array
    {
        return Cari::query()
            ->where('firma_id', $firmaId)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }
}
