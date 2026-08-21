<?php
$base = dirname(__DIR__);
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tables = [
    'cariler','cari_hareketleri','cari_hareket_eslesmeleri',
    'faturalar','fatura_kalemleri','fatura_finans_kapamalari','fatura_numara_sayaclari',
    'finans_hareketleri','kasa_hareketleri','banka_hareketleri','pos_hareketleri',
    'teknik_servis_kayitlari','teknik_servis_tahsilatlari','teknik_servis_ariza_kayitlari',
    'teknik_servis_durum_gecmisleri','teknik_servis_dokumanlari','teknik_servis_hatirlatmalari',
    'teknik_servis_gorev_atamalari','teknik_servis_aksesuar_kayitlari','teknik_servis_kalemleri',
    'teknik_servis_islem_loglari','teknik_servis_muhasebe_baglantilari','teknik_servis_mesaj_loglari',
    'teknik_servis_fis_numaralari',
    'siparisler'
];

foreach ($tables as $t) {
    $exists = Illuminate\Support\Facades\Schema::hasTable($t);
    if (! $exists) { echo $t."=MISSING\n"; continue; }
    $count = Illuminate\Support\Facades\DB::table($t)->count();
    echo $t.'='.$count."\n";
}
