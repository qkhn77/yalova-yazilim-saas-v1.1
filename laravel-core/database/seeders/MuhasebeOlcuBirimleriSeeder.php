<?php

namespace Database\Seeders;

use App\Models\Muhasebe\Birim;
use App\Muhasebe\Servisler\BirimKodResolver;
use Illuminate\Database\Seeder;

class MuhasebeOlcuBirimleriSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            // Legacy installations may already have ADET/KILO. Preserve those
            // codes and use them as aliases instead of creating AD/KGM twins.
            ['kod' => 'AD', 'aliases' => ['AD', 'ADET'], 'ad' => 'Adet', 'gib' => 'C62'],
            ['kod' => 'MTR', 'aliases' => ['MTR'], 'ad' => 'Metre', 'gib' => 'MTR'],
            ['kod' => 'MTK', 'aliases' => ['MTK'], 'ad' => 'Metrekare', 'gib' => 'MTK'],
            ['kod' => 'MTQ', 'aliases' => ['MTQ'], 'ad' => 'Metrekup', 'gib' => 'MTQ'],
            ['kod' => 'KGM', 'aliases' => ['KGM', 'KILO'], 'ad' => 'Kilogram', 'gib' => 'KGM'],
        ] as $birim) {
            $eslesen = Birim::withTrashed()->withoutGlobalScopes()
                ->where('tanim_firma_kapsami', 0)
                ->where(function ($query) use ($birim): void {
                    BirimKodResolver::whereCode($query, 'kod', $birim['kod']);
                })
                ->get()
                ->filter();

            if ($eslesen->count() > 1) {
                // A legacy alias and its canonical counterpart may coexist in
                // an existing installation. Do not delete, rename or update
                // the alias here; the later data migration will reconcile FKs.
                $canonical = $eslesen->firstWhere('kod', $birim['kod']);
                if ($canonical) {
                    if (blank($canonical->gib_birim_kodu)) {
                        $canonical->gib_birim_kodu = $birim['gib'];
                    }
                    $canonical->is_sabit = true;
                    $canonical->aktif_mi = true;
                    $canonical->saveQuietly();
                }
                continue;
            }

            /** @var Birim|null $mevcut */
            $mevcut = $eslesen->first();
            if ($mevcut) {
                if ($mevcut->trashed()) {
                    throw new \RuntimeException(sprintf(
                        'Sistem birimi pasif/silinmiş durumda, otomatik geri açılamaz: %s',
                        $mevcut->kod
                    ));
                }

                // Legacy aliases are preserved byte-for-byte. Canonical rows
                // may be completed with the system metadata required by a
                // fresh install; this never renames or creates an alias row.
                if ($mevcut->kod === $birim['kod']) {
                    $degisti = false;
                    if (blank($mevcut->gib_birim_kodu)) {
                        $mevcut->gib_birim_kodu = $birim['gib'];
                        $degisti = true;
                    }
                    if (! $mevcut->is_sabit) {
                        $mevcut->is_sabit = true;
                        $degisti = true;
                    }
                    if (! $mevcut->aktif_mi) {
                        $mevcut->aktif_mi = true;
                        $degisti = true;
                    }
                    if ($degisti) {
                        $mevcut->saveQuietly();
                    }
                }

                continue;
            }

            Birim::withoutGlobalScopes()->create([
                'firma_id' => null,
                'tanim_firma_kapsami' => 0,
                'kod' => $birim['kod'],
                'ad' => $birim['ad'],
                'gib_birim_kodu' => $birim['gib'],
                'is_sabit' => true,
                'aktif_mi' => true,
                'varsayilan_mi' => false,
            ]);
        }
    }
}
