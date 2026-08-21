<?php

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Filament\Clusters\Muhasebe\Pages\BarkodluSatisGecmisiSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\VadeTakipSayfasi;
use App\Filament\Clusters\Muhasebe\Resources\CariKartiKaynagi;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi;
use App\Models\Firma;
use App\Models\User;
use App\Models\Muhasebe\AlacakPlani;
use App\Models\Muhasebe\AlacakPlanOnayTalebi;
use App\Models\Muhasebe\AlacakPlanTaksiti;
use App\Models\Muhasebe\AlacakPlanRevizyonu;
use App\Models\Muhasebe\AlacakTahsilatEslesmesi;
use App\Models\Muhasebe\AlacakTakipNotu;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\CariHareketi;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\StokKarti;
use App\Models\TeknikServis\TeknikServisDurumTanimi;
use App\Models\TeknikServis\TeknikServisKaydi;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Guvenlik\MuhasebeFilamentErisimYardimcisi;
use App\Muhasebe\Servisler\AlacakHatirlatmaMesajServisi;
use App\Muhasebe\Servisler\AlacakHatirlatmaServisi;
use App\Muhasebe\Servisler\AlacakPlanOnayServisi;
use App\Muhasebe\Servisler\AlacakPlanServisi;
use App\Muhasebe\Servisler\AlacakPlanDogrulamaServisi;
use App\Muhasebe\Servisler\AlacakRaporServisi;
use App\Muhasebe\Servisler\AlacakTakipNotuServisi;
use App\Muhasebe\Servisler\BarkodluSatisAlacakOzetServisi;
use App\Muhasebe\Servisler\BarkodluSatisServisi;
use App\Muhasebe\Servisler\CariAlacakTakipOzetServisi;
use App\Muhasebe\Servisler\FinansHareketServisi;
use App\TeknikServis\Servisler\TeknikServisAlacakOzetServisi;
use App\Services\TenantContextService;
use App\Support\MuhasebeYetkiSablonlari;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

DB::beginTransaction();

try {
    $suffix = Str::lower(Str::random(8));
    $user = User::query()->create([
        'name' => 'Smoke User '.$suffix,
        'email' => 'smoke-'.$suffix.'@example.test',
        'password' => 'password',
        'super_admin_mi' => true,
    ]);
    Auth::login($user);

    $firma = Firma::query()->create([
        'ad' => 'Smoke Firma '.$suffix,
        'firma_kodu' => 'SMK'.$suffix,
        'durum' => 'aktif',
        'onaylandi_mi' => true,
    ]);
    session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);

    $cari = Cari::query()->create([
        'firma_id' => $firma->id,
        'kod' => 'SMK-CARI-'.$suffix,
        'ad' => 'Smoke Cari '.$suffix,
        'tur' => 'musteri',
        'durum' => 'aktif',
        'para_birimi' => 'TRY',
        'vade_gunu' => 15,
        'gsm' => '05321234567',
        'telefon' => '02293520724',
        'email' => 'smoke-cari-'.$suffix.'@example.test',
    ]);

    $kasa = KasaHesabi::query()->create([
        'firma_id' => $firma->id,
        'kod' => 'SMK-KASA-'.$suffix,
        'ad' => 'Smoke Kasa '.$suffix,
        'para_birimi' => 'TRY',
        'durum' => 'aktif',
    ]);

    $stok = StokKarti::query()->create([
        'firma_id' => $firma->id,
        'kod' => 'SMK-STOK-'.$suffix,
        'ad' => 'Smoke Urun '.$suffix,
        'slug' => 'smoke-urun-'.$suffix,
        'barkod' => '869'.$suffix,
        'tur' => 'ticari_mal',
        'birim' => 'AD',
        'alis_fiyati' => '50.00',
        'satis_fiyati' => '100.00',
        'indirimli_fiyat' => '0.00',
        'para_birimi' => 'TRY',
        'kdv_orani' => '20.00',
        'durum' => 'aktif',
        'stok_takip' => true,
        'stok_miktari' => '10.0000',
        'rezerve_miktar' => '0.0000',
        'minimum_stok' => '0.0000',
        'guncel_birim_maliyet' => '50.00',
        'stok_degeri' => '500.00',
        'negative_flag' => false,
    ]);

    $satis = app(BarkodluSatisServisi::class)->satisTamamla($firma->id, $user->id, [
        'satis_tarihi' => now()->format('Y-m-d H:i:s'),
        'cari_id' => $cari->id,
        'odeme_tipi' => 'taksitli',
        'para_birimi' => 'TRY',
        'vade_tarihi' => now()->addDays(10)->toDateString(),
        'taksit_sayisi' => 3,
        'taksit_araligi_gun' => 30,
        'kalemler' => [[
            'stok_id' => $stok->id,
            'stok_adi' => $stok->ad,
            'birim' => 'AD',
            'miktar' => 1,
            'birim_fiyat' => 100,
            'iskonto_tutari' => 0,
            'kdv_orani' => 20,
        ]],
        'eksi_stok_izinli' => false,
    ]);

    $plan = AlacakPlani::query()
        ->where('firma_id', $firma->id)
        ->where('kaynak_turu', 'barkodlu_satis')
        ->where('kaynak_id', $satis->id)
        ->firstOrFail();
    $taksitler = AlacakPlanTaksiti::query()
        ->where('alacak_plan_id', $plan->id)
        ->orderBy('sira_no')
        ->get();

    if ($taksitler->count() !== 3) {
        throw new RuntimeException('Taksit sayisi beklenenden farkli: '.$taksitler->count());
    }
    if (number_format((float) $plan->toplam_tutar, 2, '.', '') !== '120.00') {
        throw new RuntimeException('Plan toplam tutari hatali: '.$plan->toplam_tutar);
    }

    $satisFinansOzeti = app(BarkodluSatisAlacakOzetServisi::class)->ozet($satis->fresh(['cari', 'finansHareketleri']) ?? $satis);
    if ((int) ($satisFinansOzeti['plan']?->id ?? 0) !== (int) $plan->id) {
        throw new RuntimeException('Barkodlu satis finans ozeti alacak planini bulamadi.');
    }
    if (number_format((float) $satisFinansOzeti['plan_kalan_tutar'], 2, '.', '') !== '120.00') {
        throw new RuntimeException('Barkodlu satis finans ozeti plan kalanini hatali hesapladi: '.$satisFinansOzeti['plan_kalan_tutar']);
    }
    if (number_format((float) $satisFinansOzeti['plansiz_kalan_tutar'], 2, '.', '') !== '0.00') {
        throw new RuntimeException('Barkodlu satis finans ozeti planli alacagi plansiz saydi: '.$satisFinansOzeti['plansiz_kalan_tutar']);
    }
    if ((string) ($satisFinansOzeti['durum_etiketi'] ?? '') !== 'Planlı Açık') {
        throw new RuntimeException('Barkodlu satis finans ozeti planli acik durumunu uretmedi.');
    }

    $cariAlacak = CariHareketi::query()
        ->where('firma_id', $firma->id)
        ->where('belge_turu', 'satis')
        ->sum('alacak');
    if (number_format((float) $cariAlacak, 2, '.', '') !== '120.00') {
        throw new RuntimeException('Cari satis alacagi hatali: '.$cariAlacak);
    }

    $ilkTaksit = $taksitler->first();
    $ikinciTaksit = $taksitler->get(1);
    $sonuc = app(FinansHareketServisi::class)->tahsilatKasadanKaydet(
        $firma->id,
        $cari->id,
        $kasa->id,
        '70.00',
        'TRY',
        now(),
        'Smoke vade tahsilati',
        'alacak_plan_taksiti',
        $ilkTaksit->id,
    );

    $ilkTaksit->refresh();
    $ikinciTaksit->refresh();
    $plan->refresh();
    if ((string) $ilkTaksit->durum !== 'odendi' || number_format((float) $ilkTaksit->kalan_tutar, 2, '.', '') !== '0.00') {
        throw new RuntimeException('Tahsilat taksiti kapatmadi. Durum='.$ilkTaksit->durum.' kalan='.$ilkTaksit->kalan_tutar);
    }
    if ((string) $ikinciTaksit->durum !== 'kismi_odendi' || number_format((float) $ikinciTaksit->kalan_tutar, 2, '.', '') !== '10.00') {
        throw new RuntimeException('Tahsilat ikinci taksite dagilmadi. Durum='.$ikinciTaksit->durum.' kalan='.$ikinciTaksit->kalan_tutar);
    }
    if (number_format((float) $plan->odenen_tutar, 2, '.', '') !== '70.00' || number_format((float) $plan->kalan_tutar, 2, '.', '') !== '50.00') {
        throw new RuntimeException('Plan tahsilat ozeti hatali. Odenen='.$plan->odenen_tutar.' kalan='.$plan->kalan_tutar);
    }
    if (AlacakTahsilatEslesmesi::query()->where('finans_hareketi_id', $sonuc['finans']->id)->count() !== 2) {
        throw new RuntimeException('Tahsilat eslesmeleri olusmadi.');
    }

    app(FinansHareketServisi::class)->tersKayitOlustur($sonuc['finans'], 'Smoke ters kayit');
    $ilkTaksit->refresh();
    $ikinciTaksit->refresh();
    $plan->refresh();
    if ((string) $ilkTaksit->durum === 'odendi' || number_format((float) $ilkTaksit->kalan_tutar, 2, '.', '') !== '40.00') {
        throw new RuntimeException('Ters kayit taksiti geri acmadi. Durum='.$ilkTaksit->durum.' kalan='.$ilkTaksit->kalan_tutar);
    }
    if ((string) $ikinciTaksit->durum === 'odendi' || number_format((float) $ikinciTaksit->kalan_tutar, 2, '.', '') !== '40.00') {
        throw new RuntimeException('Ters kayit ikinci taksiti geri acmadi. Durum='.$ikinciTaksit->durum.' kalan='.$ikinciTaksit->kalan_tutar);
    }
    if (number_format((float) $plan->odenen_tutar, 2, '.', '') !== '0.00' || number_format((float) $plan->kalan_tutar, 2, '.', '') !== '120.00') {
        throw new RuntimeException('Ters kayit plan ozetini geri almadi. Odenen='.$plan->odenen_tutar.' kalan='.$plan->kalan_tutar);
    }
    if (AlacakTahsilatEslesmesi::query()->where('finans_hareketi_id', $sonuc['finans']->id)->exists()) {
        throw new RuntimeException('Ters kayit tahsilat eslesmesini silmedi.');
    }

    app(BarkodluSatisServisi::class)->satisIptalEt($firma->id, $satis->id, $user->id, 'Smoke iptal');
    $plan->refresh();
    if ((string) $plan->durum !== 'iptal' || number_format((float) $plan->kalan_tutar, 2, '.', '') !== '0.00') {
        throw new RuntimeException('Satis iptali alacak planini iptal etmedi. Durum='.$plan->durum.' kalan='.$plan->kalan_tutar);
    }
    $aktifSatisAlacagi = CariHareketi::query()
        ->where('firma_id', $firma->id)
        ->where('belge_turu', 'satis')
        ->where('durum', 'aktif')
        ->sum('alacak');
    if (number_format((float) $aktifSatisAlacagi, 2, '.', '') !== '0.00') {
        throw new RuntimeException('Satis iptali aktif satis alacagini kapatmadi: '.$aktifSatisAlacagi);
    }
    $aktifSatisBorcu = CariHareketi::query()
        ->where('firma_id', $firma->id)
        ->where('belge_turu', 'satis')
        ->where('durum', 'aktif')
        ->sum('borc');
    if (number_format((float) $aktifSatisBorcu, 2, '.', '') !== '0.00') {
        throw new RuntimeException('Satis iptali yapay aktif borc birakti: '.$aktifSatisBorcu);
    }

    $manuelPlan = app(AlacakPlanServisi::class)->olustur($firma->id, [
        'cari_id' => $cari->id,
        'kaynak_turu' => 'manuel',
        'plan_turu' => 'taksit',
        'toplam_tutar' => '90.00',
        'pesinat_tutari' => '0.00',
        'para_birimi' => 'TRY',
        'baslangic_tarihi' => now()->toDateString(),
        'ilk_vade_tarihi' => now()->addDays(5)->toDateString(),
        'taksit_sayisi' => 2,
        'taksit_araligi_gun' => 15,
        'aciklama' => 'Smoke manuel plan',
        'olusturan_id' => $user->id,
    ]);
    $manuelTaksitSayisi = AlacakPlanTaksiti::query()->where('alacak_plan_id', $manuelPlan->id)->count();
    if ($manuelTaksitSayisi !== 2) {
        throw new RuntimeException('Manuel plan taksit sayisi hatali: '.$manuelTaksitSayisi);
    }
    $manuelAktifAlacak = CariHareketi::query()
        ->where('firma_id', $firma->id)
        ->where('belge_turu', 'satis')
        ->where('durum', 'aktif')
        ->sum('alacak');
    if (number_format((float) $manuelAktifAlacak, 2, '.', '') !== '90.00') {
        throw new RuntimeException('Manuel plan aktif cari alacagi hatali: '.$manuelAktifAlacak);
    }

    app(AlacakPlanServisi::class)->planiIptalEt($manuelPlan, 'Smoke manuel plan iptal');
    $manuelPlan->refresh();
    if ((string) $manuelPlan->durum !== 'iptal') {
        throw new RuntimeException('Manuel plan iptal olmadi: '.$manuelPlan->durum);
    }
    $manuelAktifAlacak = CariHareketi::query()
        ->where('firma_id', $firma->id)
        ->where('belge_turu', 'satis')
        ->where('durum', 'aktif')
        ->sum('alacak');
    $manuelAktifBorc = CariHareketi::query()
        ->where('firma_id', $firma->id)
        ->where('belge_turu', 'satis')
        ->where('durum', 'aktif')
        ->sum('borc');
    if (number_format((float) $manuelAktifAlacak, 2, '.', '') !== '0.00' || number_format((float) $manuelAktifBorc, 2, '.', '') !== '0.00') {
        throw new RuntimeException('Manuel plan iptali cari bakiyeyi temizlemedi. Borc='.$manuelAktifBorc.' alacak='.$manuelAktifAlacak);
    }

    $revizyonPlan = app(AlacakPlanServisi::class)->olustur($firma->id, [
        'cari_id' => $cari->id,
        'kaynak_turu' => 'manuel',
        'plan_turu' => 'taksit',
        'toplam_tutar' => '120.00',
        'pesinat_tutari' => '0.00',
        'para_birimi' => 'TRY',
        'baslangic_tarihi' => now()->toDateString(),
        'ilk_vade_tarihi' => now()->addDays(8)->toDateString(),
        'taksit_sayisi' => 2,
        'taksit_araligi_gun' => 10,
        'aciklama' => 'Smoke revizyon plan',
        'olusturan_id' => $user->id,
    ]);
    $ilkRevizyonVadesi = AlacakPlanTaksiti::query()
        ->where('alacak_plan_id', $revizyonPlan->id)
        ->orderBy('sira_no')
        ->value('vade_tarihi');
    app(AlacakPlanServisi::class)->planiRevizeEt($revizyonPlan, [
        'revizyon_turu' => 'vade_ertele',
        'erteleme_gun' => 5,
        'aciklama' => 'Smoke vade erteleme',
        'olusturan_id' => $user->id,
    ]);
    $ertelenenIlkVade = AlacakPlanTaksiti::query()
        ->where('alacak_plan_id', $revizyonPlan->id)
        ->orderBy('sira_no')
        ->value('vade_tarihi');
    if (\Carbon\Carbon::parse((string) $ertelenenIlkVade)->toDateString() !== \Carbon\Carbon::parse((string) $ilkRevizyonVadesi)->addDays(5)->toDateString()) {
        throw new RuntimeException('Vade erteleme revizyonu beklenen vade tarihini uretmedi.');
    }
    app(AlacakPlanServisi::class)->planiRevizeEt($revizyonPlan->fresh() ?? $revizyonPlan, [
        'revizyon_turu' => 'kalan_yeniden_taksitlendir',
        'ilk_vade_tarihi' => now()->addDays(12)->toDateString(),
        'taksit_sayisi' => 3,
        'taksit_araligi_gun' => 20,
        'aciklama' => 'Smoke yeniden taksitlendirme',
        'olusturan_id' => $user->id,
    ]);
    $aktifRevizyonTaksitSayisi = AlacakPlanTaksiti::query()
        ->where('alacak_plan_id', $revizyonPlan->id)
        ->whereNotIn('durum', ['iptal'])
        ->count();
    if ($aktifRevizyonTaksitSayisi !== 3) {
        throw new RuntimeException('Yeniden taksitlendirme aktif taksit sayisi hatali: '.$aktifRevizyonTaksitSayisi);
    }
    if (AlacakPlanRevizyonu::query()->where('alacak_plan_id', $revizyonPlan->id)->count() !== 2) {
        throw new RuntimeException('Plan revizyon gecmisi beklenen sayida olusmadi.');
    }
    app(AlacakPlanServisi::class)->planiIptalEt($revizyonPlan->fresh() ?? $revizyonPlan, 'Smoke revizyon plan iptal');

    $servisDurumu = TeknikServisDurumTanimi::query()->create([
        'firma_id' => $firma->id,
        'ad' => 'Smoke Kabul '.$suffix,
        'kod' => 'SMK-KBL-'.$suffix,
        'aktif' => true,
        'siralama' => 1,
        'varsayilan_mi' => true,
    ]);

    $servisKaydi = TeknikServisKaydi::query()->create([
        'firma_id' => $firma->id,
        'servis_tipi' => 'arizali_cihaz',
        'oncelik' => 'normal',
        'servis_kanali' => 'magaza',
        'cari_id' => $cari->id,
        'model_no' => 'SMOKE-MODEL',
        'seri_no' => 'SMOKE-SERI-'.$suffix,
        'musteri_sikayeti' => 'Smoke teknik servis ariza kaydi',
        'kabul_tarihi' => now(),
        'fis_no' => 'SMK-FIS-'.$suffix,
        'servis_durumu_id' => $servisDurumu->id,
        'toplam_tutar' => '150.00',
        'odenen_tutar' => '30.00',
        'odeme_durumu' => 'kismi',
        'olusturan_id' => $user->id,
    ]);

    $servisPlan = app(AlacakPlanServisi::class)->teknikServisIcinOlustur($servisKaydi->fresh(['cari']) ?? $servisKaydi, [
        'plan_turu' => 'taksit',
        'toplam_tutar' => '150.00',
        'pesinat_tutari' => '30.00',
        'para_birimi' => 'TRY',
        'ilk_vade_tarihi' => now()->addDays(20)->toDateString(),
        'taksit_sayisi' => 3,
        'taksit_araligi_gun' => 30,
    ]);

    if ((string) $servisPlan->kaynak_turu !== 'teknik_servis' || (int) $servisPlan->kaynak_id !== (int) $servisKaydi->id) {
        throw new RuntimeException('Teknik servis plan kaynagi hatali.');
    }
    if (number_format((float) $servisPlan->odenen_tutar, 2, '.', '') !== '30.00' || number_format((float) $servisPlan->kalan_tutar, 2, '.', '') !== '120.00') {
        throw new RuntimeException('Teknik servis plan ozeti hatali. Odenen='.$servisPlan->odenen_tutar.' kalan='.$servisPlan->kalan_tutar);
    }
    $servisPlanTaksitSayisi = AlacakPlanTaksiti::query()->where('alacak_plan_id', $servisPlan->id)->count();
    if ($servisPlanTaksitSayisi !== 3) {
        throw new RuntimeException('Teknik servis plan taksit sayisi hatali: '.$servisPlanTaksitSayisi);
    }
    $servisPlanCariHareketSayisi = AlacakPlanTaksiti::query()
        ->where('alacak_plan_id', $servisPlan->id)
        ->whereNotNull('cari_hareket_id')
        ->count();
    if ($servisPlanCariHareketSayisi !== 0) {
        throw new RuntimeException('Teknik servis plani beklenmeyen cari hareket uretti: '.$servisPlanCariHareketSayisi);
    }

    $teknikFinansOzeti = app(TeknikServisAlacakOzetServisi::class)->ozet($servisKaydi->fresh(['cari']) ?? $servisKaydi);
    if ((int) ($teknikFinansOzeti['plan']?->id ?? 0) !== (int) $servisPlan->id) {
        throw new RuntimeException('Teknik servis finans ozeti aktif plani bulamadi.');
    }
    if (number_format((float) $teknikFinansOzeti['tahsilat_toplami'], 2, '.', '') !== '30.00') {
        throw new RuntimeException('Teknik servis finans ozeti tahsilat toplaminda hatali: '.$teknikFinansOzeti['tahsilat_toplami']);
    }
    if (number_format((float) $teknikFinansOzeti['plan_kalan_tutar'], 2, '.', '') !== '120.00') {
        throw new RuntimeException('Teknik servis finans ozeti plan kalanini hatali hesapladi: '.$teknikFinansOzeti['plan_kalan_tutar']);
    }
    if (number_format((float) $teknikFinansOzeti['plansiz_kalan_tutar'], 2, '.', '') !== '0.00') {
        throw new RuntimeException('Teknik servis finans ozeti planli alacagi plansiz saydi: '.$teknikFinansOzeti['plansiz_kalan_tutar']);
    }

    $teslimKontrolu = app(TeknikServisAlacakOzetServisi::class)->teslimKontrolu($servisKaydi->fresh(['cari']) ?? $servisKaydi);
    if ((bool) $teslimKontrolu['engellendi'] || ! (bool) $teslimKontrolu['uyari']) {
        throw new RuntimeException('Teknik servis planli teslim kontrolu beklenen uyariyi uretmedi.');
    }

    $plansizServisKaydi = TeknikServisKaydi::query()->create([
        'firma_id' => $firma->id,
        'servis_tipi' => 'arizali_cihaz',
        'oncelik' => 'normal',
        'servis_kanali' => 'magaza',
        'cari_id' => $cari->id,
        'model_no' => 'SMOKE-PLANSIZ',
        'seri_no' => 'SMOKE-PLANSIZ-'.$suffix,
        'musteri_sikayeti' => 'Smoke plansiz teknik servis',
        'kabul_tarihi' => now(),
        'fis_no' => 'SMK-PLANSIZ-'.$suffix,
        'servis_durumu_id' => $servisDurumu->id,
        'toplam_tutar' => '80.00',
        'odenen_tutar' => '0.00',
        'odeme_durumu' => 'odenmedi',
        'olusturan_id' => $user->id,
    ]);
    $plansizTeslimKontrolu = app(TeknikServisAlacakOzetServisi::class)->teslimKontrolu($plansizServisKaydi->fresh(['cari']) ?? $plansizServisKaydi);
    if (! (bool) $plansizTeslimKontrolu['engellendi']) {
        throw new RuntimeException('Teknik servis plansiz acik alacak teslim kontrolunde engellenmedi.');
    }

    $servisPlanTekrar = app(AlacakPlanServisi::class)->teknikServisIcinOlustur($servisKaydi->fresh(['cari']) ?? $servisKaydi, [
        'plan_turu' => 'veresiye',
        'toplam_tutar' => '150.00',
        'pesinat_tutari' => '0.00',
        'para_birimi' => 'TRY',
        'ilk_vade_tarihi' => now()->addDays(45)->toDateString(),
    ]);
    if ((int) $servisPlanTekrar->id !== (int) $servisPlan->id) {
        throw new RuntimeException('Teknik servis icin ikinci aktif plan olustu.');
    }

    $raporServisi = app(AlacakRaporServisi::class);
    $yaslandirmaTry = collect($raporServisi->yaslandirmaOzeti($firma->id))->firstWhere('para_birimi', 'TRY');
    if (! $yaslandirmaTry || number_format((float) $yaslandirmaTry['toplam'], 2, '.', '') !== '120.00') {
        throw new RuntimeException('Yaslandirma raporu teknik servis kalanini gormedi.');
    }
    if (number_format((float) $yaslandirmaTry['vadesi_gelmemis'], 2, '.', '') !== '120.00') {
        throw new RuntimeException('Yaslandirma raporu gelecek vade dagilimi hatali.');
    }

    $cariRaporSatiri = collect($raporServisi->cariOzetleri($firma->id, 0))->firstWhere('cari_id', $cari->id);
    if (! $cariRaporSatiri || number_format((float) $cariRaporSatiri['acik_toplam'], 2, '.', '') !== '120.00') {
        throw new RuntimeException('Cari ozet raporu acik toplami hatali.');
    }

    $kaynakRaporSatiri = collect($raporServisi->kaynakOzetleri($firma->id))->first(
        fn (array $satir): bool => (string) ($satir['kaynak_turu'] ?? '') === 'teknik_servis'
            && (string) ($satir['plan_turu'] ?? '') === 'taksit'
    );
    if (! $kaynakRaporSatiri || number_format((float) $kaynakRaporSatiri['acik_toplam'], 2, '.', '') !== '120.00') {
        throw new RuntimeException('Kaynak ozet raporu teknik servis toplamini hatali hesapladi.');
    }

    $planRaporSatiri = collect($raporServisi->planOzetleri($firma->id))->firstWhere('plan_id', $servisPlan->id);
    if (! $planRaporSatiri || number_format((float) $planRaporSatiri['kalan_tutar'], 2, '.', '') !== '120.00') {
        throw new RuntimeException('Plan ozet raporu teknik servis planini hatali hesapladi.');
    }
    $segmentliYaslandirma = collect($raporServisi->yaslandirmaOzeti($firma->id, [
        'cari_turu' => 'musteri',
        'cari_id' => $cari->id,
    ]))->firstWhere('para_birimi', 'TRY');
    if (! $segmentliYaslandirma || number_format((float) $segmentliYaslandirma['toplam'], 2, '.', '') !== '120.00') {
        throw new RuntimeException('Segmentli yaslandirma raporu hatali.');
    }
    $bosSegment = $raporServisi->yaslandirmaOzeti($firma->id, ['cari_turu' => 'bayi']);
    if ($bosSegment !== []) {
        throw new RuntimeException('Bos segment filtresi sonuc uretmemeliydi.');
    }
    $donemliYaslandirma = collect($raporServisi->yaslandirmaOzeti($firma->id, [
        'vade_baslangic' => now()->addDays(45)->toDateString(),
    ]))->firstWhere('para_birimi', 'TRY');
    if (! $donemliYaslandirma || number_format((float) $donemliYaslandirma['toplam'], 2, '.', '') !== '80.00') {
        throw new RuntimeException('Donem filtreli yaslandirma raporu hatali.');
    }

    $hatirlatma = app(AlacakHatirlatmaServisi::class)->ozet($firma->id, 90, 5);
    if ((int) ($hatirlatma['yaklasan']['adet'] ?? 0) !== 3) {
        throw new RuntimeException('Vade hatirlatma yaklasan adet hatali.');
    }
    $hatirlatmaToplam = collect($hatirlatma['yaklasan']['para_toplamlari'] ?? [])->firstWhere('para_birimi', 'TRY');
    if (! $hatirlatmaToplam || number_format((float) $hatirlatmaToplam['toplam'], 2, '.', '') !== '120.00') {
        throw new RuntimeException('Vade hatirlatma yaklasan toplam hatali.');
    }
    $hatirlatmaMesajlari = app(AlacakHatirlatmaMesajServisi::class)->mesajlar($firma->id, 'whatsapp', 90, 5);
    $ilkHatirlatmaMesaji = $hatirlatmaMesajlari[0] ?? null;
    if (! $ilkHatirlatmaMesaji || ($ilkHatirlatmaMesaji['durum'] ?? '') !== 'hazir' || trim((string) ($ilkHatirlatmaMesaji['whatsapp_url'] ?? '')) === '') {
        throw new RuntimeException('WhatsApp vade hatirlatma mesaji hazirlanamadi.');
    }
    if (! str_contains((string) ($ilkHatirlatmaMesaji['mesaj'] ?? ''), '120,00')) {
        throw new RuntimeException('WhatsApp vade hatirlatma mesaji kalan toplami icermiyor.');
    }
    $emailHatirlatmaMesajlari = app(AlacakHatirlatmaMesajServisi::class)->mesajlar($firma->id, 'email', 90, 5);
    if (($emailHatirlatmaMesajlari[0]['hedef'] ?? '') !== 'smoke-cari-'.$suffix.'@example.test') {
        throw new RuntimeException('E-posta vade hatirlatma hedefi hatali.');
    }

    $oncelikSatiri = collect($raporServisi->tahsilatOncelikSatirlari($firma->id, 5))->firstWhere('cari_id', $cari->id);
    if (! $oncelikSatiri || number_format((float) $oncelikSatiri['acik_toplam'], 2, '.', '') !== '120.00') {
        throw new RuntimeException('Tahsilat oncelik listesi cari toplam hatali.');
    }

    $takipSonucu = app(AlacakTakipNotuServisi::class)->topluOlustur(
        AlacakPlanTaksiti::query()->where('alacak_plan_id', $servisPlan->id)->orderBy('sira_no')->get(),
        [
            'takip_tipi' => 'arama',
            'durum' => 'planlandi',
            'takip_tarihi' => now(),
            'sonraki_takip_tarihi' => now()->addDay(),
            'not' => 'Smoke takip notu',
            'olusturan_id' => $user->id,
        ]
    );
    if ((int) ($takipSonucu['olusturulan'] ?? 0) !== 3) {
        throw new RuntimeException('Toplu takip notu beklenen sayida olusmadi.');
    }
    $sozTaksiti = AlacakPlanTaksiti::query()->where('alacak_plan_id', $servisPlan->id)->orderBy('sira_no')->firstOrFail();
    $sozNotu = app(AlacakTakipNotuServisi::class)->olustur($sozTaksiti, [
        'takip_tipi' => 'arama',
        'durum' => 'odeme_sozu',
        'takip_tarihi' => now(),
        'sonraki_takip_tarihi' => now()->addDay(),
        'odeme_sozu_tarihi' => now()->addDay(),
        'odeme_sozu_tutari' => '40.00',
        'not' => 'Smoke odeme sozu',
        'olusturan_id' => $user->id,
    ]);
    app(AlacakTakipNotuServisi::class)->guncelle($sozNotu, [
        'durum' => 'odeme_sozu',
        'odeme_sozu_tarihi' => now()->addDay(),
        'odeme_sozu_tutari' => '40.00',
        'odeme_sozu_durumu' => 'tutulmadi',
        'sonuc_notu' => 'Smoke soz sonucu',
    ]);
    $sozNotu->refresh();
    if ((string) $sozNotu->odeme_sozu_durumu !== 'tutulmadi') {
        throw new RuntimeException('Odeme sozu durumu guncellenmedi.');
    }
    $kapanacakNot = AlacakTakipNotu::query()
        ->where('alacak_plan_id', $servisPlan->id)
        ->where('durum', 'planlandi')
        ->firstOrFail();
    app(AlacakTakipNotuServisi::class)->kapat($kapanacakNot, 'Smoke takip kapatma');
    $kapanacakNot->refresh();
    if ((string) $kapanacakNot->durum !== 'tamamlandi') {
        throw new RuntimeException('Takip notu kapatilamadi.');
    }
    if (AlacakTakipNotu::query()->where('alacak_plan_id', $servisPlan->id)->count() !== 4) {
        throw new RuntimeException('Takip notu kayitlari bulunamadi.');
    }
    $takipAjandasiSatiri = collect($raporServisi->takipAjandasi($firma->id, 5))->firstWhere('cari_id', $cari->id);
    if (! $takipAjandasiSatiri || (string) ($takipAjandasiSatiri['ajanda_durumu'] ?? '') !== 'yaklasan') {
        throw new RuntimeException('Takip ajandasi yaklasan takipleri gormedi.');
    }
    $takipNotlari = $raporServisi->takipNotlari($firma->id, 0);
    if (count($takipNotlari) !== 4) {
        throw new RuntimeException('Takip notlari raporu beklenen sayida kayit uretmedi.');
    }

    $cariAlacakTakipOzeti = app(CariAlacakTakipOzetServisi::class)->ozet($cari->fresh() ?? $cari, 'TRY');
    $anaCariAlacakOzeti = $cariAlacakTakipOzeti['ana_para_ozeti'] ?? [];
    if (number_format((float) ($anaCariAlacakOzeti['acik_toplam'] ?? 0), 2, '.', '') !== '120.00') {
        throw new RuntimeException('Cari alacak takip ozeti acik toplami hatali: '.json_encode($anaCariAlacakOzeti));
    }
    if ((int) ($anaCariAlacakOzeti['plan_adedi'] ?? 0) !== 1 || (int) ($anaCariAlacakOzeti['acik_taksit_adedi'] ?? 0) !== 3) {
        throw new RuntimeException('Cari alacak takip ozeti plan/vade adedi hatali: '.json_encode($anaCariAlacakOzeti));
    }
    if (count($cariAlacakTakipOzeti['takip_notlari'] ?? []) !== 4) {
        throw new RuntimeException('Cari alacak takip ozeti takip notlarini gormedi.');
    }
    if (count($cariAlacakTakipOzeti['odeme_sozleri'] ?? []) !== 1) {
        throw new RuntimeException('Cari alacak takip ozeti odeme sozunu gormedi.');
    }

    $dogrulama = app(AlacakPlanDogrulamaServisi::class)->kontrolEt($firma->id, 100);
    if ((int) ($dogrulama['toplam_sorun'] ?? 0) !== 0) {
        throw new RuntimeException('Alacak plan dogrulama sorun buldu: '.json_encode($dogrulama['sorunlar'] ?? []));
    }

    try {
        app(AlacakPlanServisi::class)->olustur($firma->id, [
            'cari_id' => $cari->id,
            'kaynak_turu' => 'manuel',
            'plan_turu' => 'veresiye',
            'toplam_tutar' => '10.00',
            'pesinat_tutari' => '0.00',
            'para_birimi' => 'USD',
            'ilk_vade_tarihi' => now()->addDays(5)->toDateString(),
        ]);
        throw new RuntimeException('Para birimi uyumsuz manuel plan reddedilmedi.');
    } catch (IsKuraliIstisnasi) {
        // Beklenen koruma.
    }

    $yuksekPlan = app(AlacakPlanServisi::class)->olustur($firma->id, [
        'cari_id' => $cari->id,
        'kaynak_turu' => 'manuel',
        'plan_turu' => 'veresiye',
        'toplam_tutar' => '1500.00',
        'pesinat_tutari' => '0.00',
        'para_birimi' => 'TRY',
        'ilk_vade_tarihi' => now()->addDays(20)->toDateString(),
        'aciklama' => 'Smoke onayli iptal plani',
        'olusturan_id' => $user->id,
    ]);
    $onayServisi = app(AlacakPlanOnayServisi::class);
    if (! $onayServisi->onayGerektirir($yuksekPlan, false)) {
        throw new RuntimeException('Limit ustu alacak planinda onay gerekliligi calismadi.');
    }
    if ($onayServisi->onayGerektirir($yuksekPlan, true)) {
        throw new RuntimeException('Finans onay yetkili kullanici icin gereksiz onay istendi.');
    }
    $onayTalebi = $onayServisi->talepOlustur(
        $yuksekPlan,
        AlacakPlanOnayServisi::TUR_IPTAL,
        ['iptal_nedeni' => 'Smoke limit ustu iptal talebi'],
        'Smoke limit ustu iptal talebi',
        $user->id,
    );
    $tekrarOnayTalebi = $onayServisi->talepOlustur(
        $yuksekPlan,
        AlacakPlanOnayServisi::TUR_IPTAL,
        ['iptal_nedeni' => 'Smoke limit ustu iptal talebi tekrar'],
        'Smoke limit ustu iptal talebi tekrar',
        $user->id,
    );
    if ((int) $tekrarOnayTalebi->getKey() !== (int) $onayTalebi->getKey()) {
        throw new RuntimeException('Ayni plan icin ikinci bekleyen onay talebi olustu.');
    }
    if (AlacakPlanOnayTalebi::query()->where('alacak_plan_id', $yuksekPlan->id)->where('durum', 'bekliyor')->count() !== 1) {
        throw new RuntimeException('Bekleyen onay talebi sayisi hatali.');
    }
    $onayServisi->onayla($onayTalebi, $user->id, 'Smoke finans onayi');
    $onayTalebi->refresh();
    $yuksekPlan->refresh();
    if ((string) $onayTalebi->durum !== AlacakPlanOnayServisi::DURUM_ONAYLANDI || (string) $yuksekPlan->durum !== 'iptal') {
        throw new RuntimeException('Onaylanan limit ustu iptal talebi plana yansimadi.');
    }

    $uiErisimleri = [
        VadeTakipSayfasi::class => VadeTakipSayfasi::canAccess(),
        BarkodluSatisGecmisiSayfasi::class => BarkodluSatisGecmisiSayfasi::canAccess(),
        CariKartiKaynagi::class => CariKartiKaynagi::canViewAny(),
        TeknikServisKaydiKaynagi::class => TeknikServisKaydiKaynagi::canViewAny(),
    ];
    foreach ($uiErisimleri as $sinif => $erisilebilirMi) {
        if (! $erisilebilirMi) {
            throw new RuntimeException('Filament UI erisimi beklenmedik sekilde kapali: '.$sinif);
        }
    }

    $uiUrlListesi = [
        VadeTakipSayfasi::getUrl(),
        BarkodluSatisGecmisiSayfasi::getUrl(),
        CariKartiKaynagi::getUrl('view', ['record' => $cari]),
        TeknikServisKaydiKaynagi::getUrl('edit', ['record' => $servisKaydi]),
    ];
    foreach ($uiUrlListesi as $url) {
        if (! str_contains((string) $url, '/admin/')) {
            throw new RuntimeException('Filament UI URL beklenen admin yolunu icermiyor: '.$url);
        }
    }

    $yetkisizUser = User::query()->create([
        'name' => 'Smoke Yetkisiz User '.$suffix,
        'email' => 'smoke-yetkisiz-'.$suffix.'@example.test',
        'password' => 'password',
        'super_admin_mi' => false,
    ]);
    Auth::login($yetkisizUser);
    if (MuhasebeFilamentErisimYardimcisi::muhasebeYetkisiVarMi(MuhasebeYetkiSablonlari::FINANS_OLUSTUR)) {
        throw new RuntimeException('Yetkisiz kullanici finans olusturma yetkisi aldi.');
    }
    Auth::login($user);

    echo 'SMOKE_OK satis='.$satis->id.' plan='.$plan->id.' teknik_plan='.$servisPlan->id.' taksitler='.$taksitler->count().' iptal='.$plan->durum.PHP_EOL;
} finally {
    DB::rollBack();
}
