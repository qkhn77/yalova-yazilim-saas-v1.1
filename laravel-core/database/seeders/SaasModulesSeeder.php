<?php

namespace Database\Seeders;

use App\Models\Modul;
use Illuminate\Database\Seeder;

class SaasModulesSeeder extends Seeder
{
    public function run(): void
    {
        $moduller = [
            ['ad' => 'Muhasebe', 'kod' => 'muhasebe', 'aciklama' => 'Cari, fatura ve finans süreçleri.', 'aktif_mi' => true, 'siralama' => 10],
            ['ad' => 'Masraf Takibi', 'kod' => 'masraf_takip', 'aciklama' => 'Personel, elektrik, araç ve işletme masrafları.', 'aktif_mi' => true, 'siralama' => 20],
            ['ad' => 'Teknik Servis', 'kod' => 'teknik_servis', 'aciklama' => 'Servis kabul, cihaz ve onarım süreçleri.', 'aktif_mi' => true, 'siralama' => 30],
            ['ad' => 'Barkodlu Satış', 'kod' => 'barkodlu_satis', 'aciklama' => 'POS ve barkodlu satış operasyonları.', 'aktif_mi' => true, 'siralama' => 40],
            ['ad' => 'Depo', 'kod' => 'depo', 'aciklama' => 'Depo ve stok hareketleri.', 'aktif_mi' => true, 'siralama' => 50],
            ['ad' => 'Restoran', 'kod' => 'restoran', 'aciklama' => 'Restoran sipariş ve masa yönetimi.', 'aktif_mi' => true, 'siralama' => 60],
            ['ad' => 'Proje Yönetimi', 'kod' => 'proje_yonetimi', 'aciklama' => 'Proje planlama ve takip süreçleri.', 'aktif_mi' => true, 'siralama' => 70],
            ['ad' => 'Personel Takip', 'kod' => 'personel_takip', 'aciklama' => 'Personel takibi ve görev yönetimi.', 'aktif_mi' => true, 'siralama' => 80],
            ['ad' => 'Teklif Yönetimi', 'kod' => 'teklif_yonetimi', 'aciklama' => 'Teklif oluşturma ve takip süreçleri.', 'aktif_mi' => true, 'siralama' => 90],
            ['ad' => 'E-ticaret', 'kod' => 'e_ticaret', 'aciklama' => 'E-ticaret ve çevrim içi satış entegrasyonları.', 'aktif_mi' => true, 'siralama' => 100],
            ['ad' => 'BT Varlık Yönetimi', 'kod' => 'bt_varlik_yonetimi', 'aciklama' => 'BT envanter ve varlık yaşam döngüsü.', 'aktif_mi' => true, 'siralama' => 110],
            ['ad' => 'Web', 'kod' => 'web', 'aciklama' => 'Web site içerik ve sayfa yönetimi.', 'aktif_mi' => true, 'siralama' => 120],
            // Step 4 ile uyum: ürün kaynak görünürlüğü bu modül kodunu kullanıyor.
            ['ad' => 'Ürün Yönetimi', 'kod' => 'urunler', 'aciklama' => 'Ürün ve ürün kategorisi yönetimi.', 'aktif_mi' => true, 'siralama' => 130],
            ['ad' => 'Ajanda ve Görevler', 'kod' => 'sekreter', 'aciklama' => 'Ajanda, görev, not ve hatırlatmalar.', 'aktif_mi' => true, 'siralama' => 140],
        ];

        foreach ($moduller as $modul) {
            Modul::query()->updateOrCreate(
                ['kod' => $modul['kod']],
                $modul
            );
        }
    }
}
