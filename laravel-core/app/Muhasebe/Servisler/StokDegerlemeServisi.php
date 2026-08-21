<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\StokKarti;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StokDegerlemeServisi
{
    public function firmaToplamDeger(int $firmaId): string
    {
        return (string) StokKarti::query()
            ->where('firma_id', $firmaId)
            ->where('stok_takip', true)
            ->sum('stok_degeri');
    }

    /**
     * @return Collection<int,array{kategori_id:int|null,toplam_deger:string,toplam_miktar:string}>
     */
    public function kategoriBazliDeger(int $firmaId): Collection
    {
        return DB::table('stok_kartlari')
            ->selectRaw('kategori_id, SUM(stok_degeri) as toplam_deger, SUM(stok_miktari) as toplam_miktar')
            ->where('firma_id', $firmaId)
            ->where('stok_takip', 1)
            ->groupBy('kategori_id')
            ->get()
            ->map(fn ($row) => [
                'kategori_id' => $row->kategori_id !== null ? (int) $row->kategori_id : null,
                'toplam_deger' => (string) $row->toplam_deger,
                'toplam_miktar' => (string) $row->toplam_miktar,
            ]);
    }

    /**
     * @return Builder<StokKarti>
     */
    public function stokDegerSorgusu(int $firmaId): Builder
    {
        return StokKarti::query()
            ->where('firma_id', $firmaId)
            ->where('stok_takip', true)
            ->orderByDesc('stok_degeri');
    }
}
