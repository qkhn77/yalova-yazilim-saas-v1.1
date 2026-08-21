<?php
$base = dirname(__DIR__);
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Siparişlerin muhasebe bağlantılarını temizle (siparişleri silmiyoruz)
if (Schema::hasTable('siparisler')) {
    $updates = [];
    foreach (['muhasebe_cari_id', 'proforma_fatura_id', 'tahsilat_finans_hareketi_id'] as $col) {
        if (Schema::hasColumn('siparisler', $col)) {
            $updates[$col] = null;
        }
    }
    if (!empty($updates)) {
        DB::table('siparisler')->update($updates);
    }
}

$truncateTables = [
    // Teknik servis bağımlılar
    'teknik_servis_tahsilatlari',
    'teknik_servis_ariza_kayitlari',
    'teknik_servis_durum_gecmisleri',
    'teknik_servis_dokumanlari',
    'teknik_servis_hatirlatmalari',
    'teknik_servis_gorev_atamalari',
    'teknik_servis_aksesuar_kayitlari',
    'teknik_servis_kalemleri',
    'teknik_servis_islem_loglari',
    'teknik_servis_muhasebe_baglantilari',
    'teknik_servis_mesaj_loglari',
    'teknik_servis_kayitlari',
    'teknik_servis_fis_numaralari',

    // Muhasebe/finans bağımlılar
    'cari_hareket_eslesmeleri',
    'fatura_kalemleri',
    'kasa_hareketleri',
    'banka_hareketleri',
    'pos_hareketleri',
    'cari_hareketleri',
    'finans_hareketleri',
    'faturalar',
    'fatura_numara_sayaclari',
    'cariler',
];

DB::statement('SET FOREIGN_KEY_CHECKS=0');
try {
    foreach ($truncateTables as $table) {
        if (Schema::hasTable($table)) {
            DB::table($table)->truncate();
            echo "TRUNCATED: {$table}\n";
        }
    }
} finally {
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
}

echo "DONE\n";
