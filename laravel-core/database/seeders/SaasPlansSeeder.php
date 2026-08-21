<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class SaasPlansSeeder extends Seeder
{
    public function run(): void
    {
        $planlar = [
            ['ad' => 'Başlangıç', 'kod' => 'baslangic', 'ucret' => 0, 'sure_gun' => 30, 'aktif_mi' => true],
            ['ad' => 'Standart', 'kod' => 'standart', 'ucret' => 999, 'sure_gun' => 30, 'aktif_mi' => true],
            ['ad' => 'Profesyonel', 'kod' => 'profesyonel', 'ucret' => 2499, 'sure_gun' => 30, 'aktif_mi' => true],
        ];

        foreach ($planlar as $plan) {
            Plan::query()->updateOrCreate(
                ['kod' => $plan['kod']],
                $plan
            );
        }
    }
}
