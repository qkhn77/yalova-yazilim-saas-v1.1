<?php

namespace Database\Seeders;

use App\Models\Muhasebe\Birim;
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
            $eslesen = collect($birim['aliases'])
                ->map(fn (string $kod): ?Birim => Birim::withTrashed()->withoutGlobalScopes()
                    ->where('tanim_firma_kapsami', 0)
                    ->where('kod', $kod)
                    ->first())
                ->filter();

            if ($eslesen->count() > 1) {
                throw new \RuntimeException(sprintf(
                    'Sistem birimi için birden fazla alias mevcut: %s',
                    implode(', ', $eslesen->pluck('kod')->all())
                ));
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

                // Existing IDs, codes, names, active and fixed flags remain
                // untouched. Only a missing, canonical GIB code is completed.
                if (blank($mevcut->gib_birim_kodu)) {
                    $mevcut->gib_birim_kodu = $birim['gib'];
                    $mevcut->saveQuietly();
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
