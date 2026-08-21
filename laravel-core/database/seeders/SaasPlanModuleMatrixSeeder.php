<?php

namespace Database\Seeders;

use App\Models\Modul;
use App\Models\Plan;
use App\Models\PlanModulu;
use Illuminate\Database\Seeder;

class SaasPlanModuleMatrixSeeder extends Seeder
{
    public function run(): void
    {
        $matrix = [
            'baslangic' => [
                'web',
                'urunler',
                'teknik_servis',
                'teklif_yonetimi',
            ],
            'standart' => [
                'web',
                'urunler',
                'teknik_servis',
                'muhasebe',
                'masraf_takip',
                'barkodlu_satis',
                'depo',
                'teklif_yonetimi',
                'proje_yonetimi',
            ],
            'profesyonel' => [
                'muhasebe',
                'masraf_takip',
                'teknik_servis',
                'barkodlu_satis',
                'depo',
                'restoran',
                'proje_yonetimi',
                'personel_takip',
                'teklif_yonetimi',
                'e_ticaret',
                'bt_varlik_yonetimi',
                'web',
                'urunler',
            ],
        ];

        foreach ($matrix as $planKodu => $modulKodlari) {
            $plan = Plan::query()->where('kod', $planKodu)->first();
            if (! $plan) {
                continue;
            }

            foreach ($modulKodlari as $modulKodu) {
                $modul = Modul::query()->where('kod', $modulKodu)->first();
                if (! $modul) {
                    continue;
                }

                PlanModulu::query()->updateOrCreate(
                    ['plan_id' => $plan->id, 'modul_id' => $modul->id],
                    []
                );
            }
        }
    }
}
