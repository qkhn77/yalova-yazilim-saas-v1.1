<?php

namespace App\Muhasebe\Servisler;

use App\Models\Masraf\DuzenliFaturaTanimi;
use App\Models\Muhasebe\MasrafKategorisi;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use Illuminate\Support\Facades\DB;

final class DuzenliFaturaTanimiServisi
{
    /** @param array<string, mixed> $alanlar */
    public function kaydet(int $firmaId, array $alanlar, ?int $tanimId = null): DuzenliFaturaTanimi
    {
        $ad = trim((string) ($alanlar['ad'] ?? ''));
        if ($ad === '') {
            throw new IsKuraliIstisnasi('Düzenli fatura tanımı adı zorunludur.');
        }

        $kategori = MasrafKategorisi::query()
            ->where('firma_id', $firmaId)
            ->whereKey((int) ($alanlar['masraf_kategorisi_id'] ?? 0))
            ->where('aktif_mi', true)
            ->where('secilir_mi', true)
            ->whereHas('ustKategori', fn ($query) => $query->where('kod', 'duzenli_faturalar'))
            ->first();
        if (! $kategori) {
            throw new IsKuraliIstisnasi('Düzenli fatura tanımı yalnızca Düzenli Faturalar alt türlerinden biriyle oluşturulabilir.');
        }

        $ayni = DuzenliFaturaTanimi::query()
            ->where('firma_id', $firmaId)
            ->where('masraf_kategorisi_id', $kategori->id)
            ->where('ad', $ad)
            ->when($tanimId !== null, fn ($query) => $query->whereKeyNot($tanimId))
            ->exists();
        if ($ayni) {
            throw new IsKuraliIstisnasi('Bu düzenli fatura tanımı zaten mevcut.');
        }

        return DB::transaction(function () use ($firmaId, $alanlar, $ad, $kategori, $tanimId): DuzenliFaturaTanimi {
            $tanim = $tanimId === null
                ? new DuzenliFaturaTanimi()
                : DuzenliFaturaTanimi::query()->where('firma_id', $firmaId)->whereKey($tanimId)->firstOrFail();

            $tanim->fill([
                'firma_id' => $firmaId,
                'masraf_kategorisi_id' => $kategori->id,
                'ad' => $ad,
                'abone_no' => filled($alanlar['abone_no'] ?? null) ? trim((string) $alanlar['abone_no']) : null,
                'tedarikci' => filled($alanlar['tedarikci'] ?? null) ? trim((string) $alanlar['tedarikci']) : null,
                'aktif_mi' => (bool) ($alanlar['aktif_mi'] ?? true),
                'notlar' => $alanlar['notlar'] ?? null,
            ])->save();

            return $tanim->fresh(['kategori:id,ad']);
        });
    }

    public function durumDegistir(int $firmaId, int $tanimId): DuzenliFaturaTanimi
    {
        $tanim = DuzenliFaturaTanimi::query()->where('firma_id', $firmaId)->whereKey($tanimId)->firstOrFail();
        $tanim->update(['aktif_mi' => ! $tanim->aktif_mi]);

        return $tanim->fresh();
    }
}
