<?php

namespace Database\Seeders;

use App\Models\Rol;
use Illuminate\Database\Seeder;

class SaasRolesSeeder extends Seeder
{
    public function run(): void
    {
        $roller = [
            ['ad' => 'Firma Sahibi', 'kod' => 'firma_sahibi', 'aciklama' => 'Firma sahibi; tenant seviyesinde en geniş yetki.', 'sistem_rolu_mu' => true],
            ['ad' => 'Firma Yöneticisi', 'kod' => 'firma_yoneticisi', 'aciklama' => 'Firma operasyonlarını yöneten yönetici.', 'sistem_rolu_mu' => true],
            ['ad' => 'Muhasebe Personeli', 'kod' => 'muhasebe_personeli', 'aciklama' => 'Muhasebe süreçlerini yöneten personel.', 'sistem_rolu_mu' => true],
            ['ad' => 'Teknik Servis Personeli', 'kod' => 'teknik_servis_personeli', 'aciklama' => 'Teknik servis operasyon personeli.', 'sistem_rolu_mu' => true],
            ['ad' => 'Satış Personeli', 'kod' => 'satis_personeli', 'aciklama' => 'Satış ve teklif süreç personeli.', 'sistem_rolu_mu' => true],
            ['ad' => 'Depo Personeli', 'kod' => 'depo_personeli', 'aciklama' => 'Depo ve stok operasyon personeli.', 'sistem_rolu_mu' => true],
            ['ad' => 'Görüntüleyici', 'kod' => 'goruntuleyici', 'aciklama' => 'Sadece güvenli görüntüleme izinlerine sahip kullanıcı.', 'sistem_rolu_mu' => true],
        ];

        foreach ($roller as $rol) {
            Rol::query()->updateOrCreate(
                ['kod' => $rol['kod']],
                $rol
            );
        }
    }
}
