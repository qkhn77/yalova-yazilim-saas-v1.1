<?php

declare(strict_types=1);

use App\Models\Firma;
use App\Models\Personel\Personel;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$action = $argv[1] ?? 'ensure';
$prefix = 'PERF_ROUTE_';
$now = now();

function firstId(string $table, array $where): ?int
{
    $query = DB::table($table);

    foreach ($where as $column => $value) {
        $query->where($column, $value);
    }

    $id = $query->value('id');

    return $id === null ? null : (int) $id;
}

function ensureRow(string $table, array $where, array $values): int
{
    $id = firstId($table, $where);

    if ($id !== null) {
        DB::table($table)->where('id', $id)->update($values + ['updated_at' => now()]);

        return $id;
    }

    return (int) DB::table($table)->insertGetId($where + $values + [
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function deletePrefixed(string $table, string $column, string $prefix): int
{
    return DB::table($table)
        ->where($column, 'like', $prefix.'%')
        ->delete();
}

function activeFirmId(): int
{
    $firm = Firma::query()
        ->where('durum', 'aktif')
        ->orWhere('onaylandi_mi', true)
        ->orderBy('id')
        ->first()
        ?? Firma::query()->orderBy('id')->first();

    if (! $firm) {
        throw new RuntimeException('Fixture oluşturmak için firma bulunamadı.');
    }

    return (int) $firm->getKey();
}

function ensurePersonel(int $firmaId, string $prefix): int
{
    $existing = Personel::query()->orderBy('id')->value('id');

    if ($existing !== null) {
        return (int) $existing;
    }

    return ensureRow('personeller', ['personel_no' => $prefix.'PERSONEL'], [
        'firma_id' => $firmaId,
        'ad_soyad' => 'Performance Fixture Personel',
        'ad' => 'Performance',
        'soyad' => 'Fixture',
        'calisma_tipi' => 'tam_zamanli',
        'maas_tipi' => 'aylik',
        'maas_tutari' => 0,
        'ucret' => 0,
        'para_birimi' => 'TRY',
        'durum' => 'aktif',
        'notlar' => $prefix.'temporary',
    ]);
}

function ensureFixtures(string $prefix): array
{
    $firmaId = activeFirmId();
    $personelId = ensurePersonel($firmaId, $prefix);
    $today = now()->toDateString();
    $start = now()->setTime(9, 0);
    $end = now()->setTime(18, 0);

    $created = [];
    $simpleDefinitions = [
        'muhasebe_cari_gruplari' => 'CARI_GRUBU',
        'muhasebe_logo_turleri' => 'LOGO_TURU',
        'muhasebe_malzeme_turleri' => 'MALZEME_TURU',
        'muhasebe_marka_ureticileri' => 'MARKA_URETICI',
        'muhasebe_odeme_yontemleri' => 'ODEME_YONTEMI',
        'muhasebe_tasarimlar' => 'TASARIM',
        'muhasebe_varyantlar' => 'VARYANT',
    ];

    foreach ($simpleDefinitions as $table => $suffix) {
        $created[$table] = ensureRow($table, ['kod' => $prefix.$suffix], [
            'firma_id' => $firmaId,
            'is_sabit' => 0,
            'tanim_firma_kapsami' => 0,
            'ad' => 'Performance Fixture '.$suffix,
            'aktif_mi' => 1,
        ]);
    }

    $created['roller'] = ensureRow('roller', [
        'kod' => \App\Support\FirmaIciRolKisitlayici::firmaGrupKodOnEki($firmaId).strtolower($prefix).'grup',
    ], [
        'ad' => 'Performance Fixture Grup',
        'aciklama' => $prefix.'temporary',
        'sistem_rolu_mu' => 0,
    ]);

    $markaId = ensureRow('muhasebe_markalar', ['kod' => $prefix.'MARKA'], [
        'firma_id' => $firmaId,
        'is_sabit' => 0,
        'tanim_firma_kapsami' => 0,
        'ad' => 'Performance Fixture Marka',
        'aktif_mi' => 1,
    ]);

    $created['muhasebe_modeller'] = ensureRow('muhasebe_modeller', ['kod' => $prefix.'STOK_MODELI'], [
        'firma_id' => $firmaId,
        'is_sabit' => 0,
        'tanim_firma_kapsami' => 0,
        'marka_id' => $markaId,
        'ad' => 'Performance Fixture Stok Modeli',
        'aktif_mi' => 1,
    ]);

    $created['personel_avanslari'] = ensureRow('personel_avanslari', ['aciklama' => $prefix.'AVANS'], [
        'firma_id' => $firmaId,
        'personel_id' => $personelId,
        'tarih' => $today,
        'tutar' => 1,
        'para_birimi' => 'TRY',
        'durum' => 'taslak',
        'mahsup_durumu' => 'bekliyor',
        'onay_durumu' => 'bekliyor',
        'kalan_tutar' => 1,
    ]);

    $created['personel_giris_cikislari'] = ensureRow('personel_giris_cikislari', ['aciklama' => $prefix.'GIRIS_CIKIS'], [
        'firma_id' => $firmaId,
        'personel_id' => $personelId,
        'tarih' => $today,
        'giris_at' => $start,
        'cikis_at' => $end,
        'giris_zamani' => $start,
        'cikis_zamani' => $end,
        'kayit_tipi' => 'manuel',
        'kaynak' => 'panel',
        'onay_durumu' => 'onay_bekliyor',
    ]);

    $created['personel_izinleri'] = ensureRow('personel_izinleri', ['aciklama' => $prefix.'IZIN'], [
        'firma_id' => $firmaId,
        'personel_id' => $personelId,
        'izin_turu' => 'yillik',
        'baslangic_tarihi' => $today,
        'bitis_tarihi' => $today,
        'baslangic_at' => $start,
        'bitis_at' => $end,
        'gun_sayisi' => 1,
        'durum' => 'onay_bekliyor',
        'onay_durumu' => 'onay_bekliyor',
    ]);

    $created['personel_maas_donemleri'] = ensureRow('personel_maas_donemleri', ['ad' => $prefix.'MAAS_DONEMI'], [
        'firma_id' => $firmaId,
        'donem_yil' => (int) now()->format('Y'),
        'donem_ay' => (int) now()->format('m'),
        'baslangic_tarihi' => now()->startOfMonth()->toDateString(),
        'bitis_tarihi' => now()->endOfMonth()->toDateString(),
        'durum' => 'taslak',
        'para_birimi' => 'TRY',
        'aciklama' => $prefix.'temporary',
    ]);

    $created['personel_vardiyalari'] = ensureRow('personel_vardiyalari', ['notlar' => $prefix.'VARDIYA'], [
        'firma_id' => $firmaId,
        'personel_id' => $personelId,
        'tarih' => $today,
        'baslangic_at' => $start,
        'bitis_at' => $end,
        'baslangic_saati' => '09:00:00',
        'bitis_saati' => '18:00:00',
        'vardiya_tipi' => 'normal',
        'durum' => 'planlandi',
    ]);

    $salonId = ensureRow('restoran_salonlari', ['kod' => $prefix.'SALON'], [
        'firma_id' => $firmaId,
        'ad' => 'Performance Fixture Salon',
        'aktif_mi' => 1,
        'siralama' => 999,
    ]);

    $masaId = ensureRow('restoran_masalari', ['kod' => $prefix.'MASA'], [
        'firma_id' => $firmaId,
        'salon_id' => $salonId,
        'ad' => 'Performance Fixture Masa',
        'kapasite' => 2,
        'durum' => 'bos',
        'aktif_mi' => 1,
        'siralama' => 999,
    ]);

    $kategoriId = ensureRow('restoran_menu_kategorileri', ['slug' => strtolower($prefix).'kategori'], [
        'firma_id' => $firmaId,
        'ad' => 'Performance Fixture Kategori',
        'aktif_mi' => 1,
        'siralama' => 999,
    ]);

    $menuUrunuId = ensureRow('restoran_menu_urunleri', ['ad' => $prefix.'MENU_URUNU'], [
        'firma_id' => $firmaId,
        'kategori_id' => $kategoriId,
        'aciklama' => 'Performance fixture',
        'fiyat' => 1,
        'kdv_orani' => 0,
        'aktif_mi' => 1,
        'qr_menu_gorunur_mu' => 1,
        'stokta_var_mi' => 1,
        'siralama' => 999,
    ]);

    $created['restoran_receteleri'] = ensureRow('restoran_receteleri', ['ad' => $prefix.'RECETE'], [
        'firma_id' => $firmaId,
        'menu_urunu_id' => $menuUrunuId,
        'aktif_mi' => 1,
        'notlar' => $prefix.'temporary',
    ]);

    $adisyonId = ensureRow('restoran_adisyonlari', ['adisyon_no' => $prefix.'ADISYON'], [
        'firma_id' => $firmaId,
        'masa_id' => $masaId,
        'adisyon_no' => $prefix.'ADISYON',
        'acilis_at' => now(),
        'durum' => 'acik',
        'siparis_tipi' => 'masa',
        'musteri_sayisi' => 1,
        'para_birimi' => 'TRY',
        'notlar' => $prefix.'temporary',
    ]);

    $created['restoran_adisyon_kalemleri'] = ensureRow('restoran_adisyon_kalemleri', ['urun_adi' => $prefix.'ADISYON_KALEMI'], [
        'firma_id' => $firmaId,
        'adisyon_id' => $adisyonId,
        'menu_urunu_id' => $menuUrunuId,
        'miktar' => 1,
        'birim_fiyat' => 1,
        'kdv_orani' => 0,
        'ara_tutar' => 1,
        'toplam_tutar' => 1,
        'durum' => 'yeni',
    ]);

    $created['siparisler'] = ensureRow('siparisler', ['siparis_no' => $prefix.'SIPARIS'], [
        'firma_id' => $firmaId,
        'musteri_ad_soyad' => 'Performance Fixture Müşteri',
        'musteri_email' => 'perf-fixture@local.test',
        'musteri_telefon' => '5550000000',
        'teslimat_adresi' => 'Performance fixture adres',
        'teslimat_ulke' => 'TR',
        'para_birimi' => 'TRY',
        'durum' => 'beklemede',
    ]);

    $created['teklifler'] = ensureRow('teklifler', ['teklif_no' => $prefix.'TEKLIF'], [
        'firma_id' => $firmaId,
        'durum' => 'taslak',
        'baslik' => 'Performance Fixture Teklif',
        'tarih' => now(),
        'para_birimi' => 'TRY',
        'aciklama' => $prefix.'temporary',
    ]);

    $created['firma_id'] = $firmaId;
    $created['personel_id'] = $personelId;
    $created['restoran_salonlari'] = $salonId;
    $created['restoran_masalari'] = $masaId;
    $created['restoran_menu_kategorileri'] = $kategoriId;
    $created['restoran_menu_urunleri'] = $menuUrunuId;
    $created['restoran_adisyonlari'] = $adisyonId;
    $created['muhasebe_markalar'] = $markaId;

    return $created;
}

function cleanupFixtures(string $prefix): array
{
    $deleted = [];

    $adisyonIds = DB::table('restoran_adisyonlari')->where('adisyon_no', 'like', $prefix.'%')->pluck('id');
    $menuUrunuIds = DB::table('restoran_menu_urunleri')->where('ad', 'like', $prefix.'%')->pluck('id');

    $deleted['restoran_adisyon_kalemleri'] = DB::table('restoran_adisyon_kalemleri')
        ->where('urun_adi', 'like', $prefix.'%')
        ->when($adisyonIds->isNotEmpty(), fn ($query) => $query->orWhereIn('adisyon_id', $adisyonIds))
        ->delete();
    $deleted['restoran_receteleri'] = DB::table('restoran_receteleri')
        ->where('ad', 'like', $prefix.'%')
        ->when($menuUrunuIds->isNotEmpty(), fn ($query) => $query->orWhereIn('menu_urunu_id', $menuUrunuIds))
        ->delete();
    $deleted['restoran_adisyonlari'] = deletePrefixed('restoran_adisyonlari', 'adisyon_no', $prefix);
    $deleted['restoran_menu_urunleri'] = deletePrefixed('restoran_menu_urunleri', 'ad', $prefix);
    $deleted['restoran_menu_kategorileri'] = DB::table('restoran_menu_kategorileri')->where('slug', strtolower($prefix).'kategori')->delete();
    $deleted['restoran_masalari'] = deletePrefixed('restoran_masalari', 'kod', $prefix);
    $deleted['restoran_salonlari'] = deletePrefixed('restoran_salonlari', 'kod', $prefix);

    $deleted['siparisler'] = deletePrefixed('siparisler', 'siparis_no', $prefix);
    $deleted['teklifler'] = deletePrefixed('teklifler', 'teklif_no', $prefix);

    $deleted['personel_avanslari'] = deletePrefixed('personel_avanslari', 'aciklama', $prefix);
    $deleted['personel_giris_cikislari'] = deletePrefixed('personel_giris_cikislari', 'aciklama', $prefix);
    $deleted['personel_izinleri'] = deletePrefixed('personel_izinleri', 'aciklama', $prefix);
    $deleted['personel_maas_donemleri'] = deletePrefixed('personel_maas_donemleri', 'ad', $prefix);
    $deleted['personel_vardiyalari'] = deletePrefixed('personel_vardiyalari', 'notlar', $prefix);
    $deleted['personeller'] = deletePrefixed('personeller', 'personel_no', $prefix);
    $deleted['roller'] = DB::table('roller')
        ->where('kod', 'like', 'firma_%_grup_'.strtolower($prefix).'%')
        ->delete();

    foreach ([
        'muhasebe_modeller',
        'muhasebe_markalar',
        'muhasebe_cari_gruplari',
        'muhasebe_logo_turleri',
        'muhasebe_malzeme_turleri',
        'muhasebe_marka_ureticileri',
        'muhasebe_odeme_yontemleri',
        'muhasebe_tasarimlar',
        'muhasebe_varyantlar',
    ] as $table) {
        $deleted[$table] = deletePrefixed($table, 'kod', $prefix);
    }

    return $deleted;
}

if ($action === 'ensure') {
    echo json_encode([
        'created' => ensureFixtures($prefix),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;

    return;
}

if ($action === 'delete') {
    echo json_encode([
        'deleted' => cleanupFixtures($prefix),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;

    return;
}

fwrite(STDERR, "Unknown action: {$action}".PHP_EOL);
exit(1);
