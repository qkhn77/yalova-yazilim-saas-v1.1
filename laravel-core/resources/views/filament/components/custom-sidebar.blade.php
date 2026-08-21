@php
    use App\Filament\Clusters\Muhasebe\Resources\BankaHesabiKaynagi;
    use App\Filament\Clusters\Muhasebe\Pages\BekleyenFatura;
    use App\Filament\Clusters\Muhasebe\Pages\BarkodEtiketYazdirmaSayfasi;
    use App\Filament\Clusters\Muhasebe\Pages\BarkodluSatisBarkodListesiSayfasi;
    use App\Filament\Clusters\Muhasebe\Pages\BarkodluSatisAyarlarSayfasi;
    use App\Filament\Clusters\Muhasebe\Pages\SatisFisDuzenleSayfasi;
    use App\Filament\Clusters\Muhasebe\Pages\BarkodluSatisGecmisiSayfasi;
    use App\Filament\Clusters\Muhasebe\Pages\BarkodluSatisIadeGecmisiSayfasi;
    use App\Filament\Clusters\Muhasebe\Pages\BarkodluSatisMuhasebeMutabakatSayfasi;
    use App\Filament\Clusters\Muhasebe\Pages\BarkodluSatisSayfasi;
    use App\Filament\Clusters\Muhasebe\Pages\HizliSatisSayfasi;
    use App\Filament\Clusters\Muhasebe\Pages\CariEkstreSayfasi;
    use App\Filament\Clusters\Muhasebe\Pages\CariYaslandirmaSayfasi;
    use App\Filament\Clusters\Muhasebe\Pages\CariGrupKaynagiSayfasi;
    use App\Filament\Clusters\Muhasebe\Pages\CariHareketleriSayfasi;
    use App\Filament\Clusters\Muhasebe\Resources\CariKartiKaynagi;
    use App\Filament\Clusters\Muhasebe\Pages\CekYonetimiSayfasi;
    use App\Filament\Clusters\Muhasebe\Pages\FinansDashboardSayfasi;
    use App\Filament\Clusters\Muhasebe\Pages\FinansHareketleriListesiSayfasi;
    use App\Filament\Clusters\Muhasebe\Pages\GelenFatura;
    use App\Filament\Clusters\Muhasebe\Pages\GelenIadeFaturasiSayfasi;
    use App\Filament\Clusters\Muhasebe\Pages\GiderFaturasiSayfasi;
    use App\Filament\Clusters\Muhasebe\Pages\GidenFatura;
    use App\Filament\Clusters\Muhasebe\Pages\GelirGiderRaporuSayfasi;
    use App\Filament\Clusters\Muhasebe\Pages\IptalFatura;
    use App\Filament\Clusters\Muhasebe\Pages\GidenIadeFaturasiSayfasi;
    use App\Filament\Clusters\Muhasebe\Resources\KasaHesabiKaynagi;
    use App\Filament\Clusters\Muhasebe\Pages\KritikStoklarSayfasi;
    use App\Filament\Clusters\Muhasebe\Pages\MuhasebeDashboardSayfasi;
    use App\Filament\Clusters\Muhasebe\Pages\NetteFaturaEntegrasyonSayfasi;
    use App\Filament\Clusters\Muhasebe\Pages\StokDepoListesiSayfasi;
    use App\Filament\Clusters\Muhasebe\Pages\StokDepoTransferSayfasi;
    use App\Filament\Clusters\Muhasebe\Pages\StokDepoTransferGecmisiSayfasi;
    use App\Filament\Clusters\Muhasebe\Pages\StokDepoSayimSayfasi;
    use App\Filament\Clusters\Muhasebe\Pages\StokDepoSayimGecmisiSayfasi;
    use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\DepoTanimKaynagi;
    use App\Filament\Clusters\MasrafTakip\Pages\MasrafTakibiSayfasi;
    use App\Filament\Clusters\MasrafTakip\Pages\MasrafKategorileriSayfasi;
    use App\Filament\Clusters\MasrafTakip\Pages\MasrafRaporlariSayfasi;
    use App\Filament\Clusters\MasrafTakip\Pages\AraclarSayfasi;
    use App\Filament\Clusters\MasrafTakip\Pages\DuzenliFaturaTanimlariSayfasi;
    use App\Filament\Clusters\ProjeYonetimi\Pages\IsletmeProjeleriSayfasi;
    use App\Filament\Clusters\ProjeYonetimi\Pages\ProjeRaporlariSayfasi;
    use App\Filament\Clusters\MasrafTakip\Pages\MasrafButceleriSayfasi;
    use App\Filament\Clusters\Muhasebe\Resources\ParaBirimiTanimKaynagi;
    use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\DovizKuruTanimKaynagi;
    use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\BirimTanimKaynagi;
    use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\CariGrubuTanimKaynagi;
    use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeLogoTuruTanimKaynagi;
    use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeMalzemeTuruTanimKaynagi;
    use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeMarkaTanimKaynagi;
    use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeMarkaUreticiTanimKaynagi;
    use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeStokModeliTanimKaynagi;
    use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeTasarimTanimKaynagi;
    use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeVaryantTanimKaynagi;
    use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\OdemeYontemiTanimKaynagi;
    use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\VergiOraniTanimKaynagi;
    use App\Filament\Clusters\Muhasebe\Pages\ProformaFaturaSayfasi;
    use App\Filament\Clusters\Muhasebe\Pages\SenetYonetimiSayfasi;
    use App\Filament\Clusters\Muhasebe\Pages\StokHareketleriSayfasi;
    use App\Filament\Clusters\Muhasebe\Resources\StokKategoriKaynagi;
    use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi;
    use App\Filament\Clusters\Muhasebe\Resources\StokKartiKaynagi;
    use App\Filament\Clusters\Muhasebe\Pages\StokTuruTanimlariSayfasi;
    use App\Filament\Clusters\Muhasebe\Pages\TumFaturalarSayfasi;
    use App\Filament\Clusters\Muhasebe\Pages\VadeTakipSayfasi;
    use App\Filament\Clusters\Muhasebe\Pages\VergiOraniKaynagiSayfasi;
    use App\Filament\Clusters\Muhasebe\Resources\PosHesabiKaynagi;
    use App\Filament\Clusters\TeklifYonetimi\Resources\TeklifKaynagi;
    use App\Filament\Clusters\TeklifYonetimi\Resources\TeklifSablonKaynagi;
    use App\Filament\Clusters\PersonelTakip\Resources\PersonelAvansKaynagi;
    use App\Filament\Clusters\PersonelTakip\Resources\PersonelDepartmanKaynagi;
    use App\Filament\Clusters\PersonelTakip\Resources\PersonelGirisCikisKaynagi;
    use App\Filament\Clusters\PersonelTakip\Resources\PersonelGorevKaynagi;
    use App\Filament\Clusters\PersonelTakip\Resources\PersonelIzinKaynagi;
    use App\Filament\Clusters\PersonelTakip\Resources\PersonelKaynagi;
    use App\Filament\Clusters\PersonelTakip\Resources\PersonelMaasDonemiKaynagi;
    use App\Filament\Clusters\PersonelTakip\Resources\PersonelVardiyaKaynagi;
    use App\Filament\Clusters\PersonelTakip\Resources\PersonelVardiyaSablonuKaynagi;
    use App\Filament\Clusters\PersonelTakip\Resources\SubeKaynagi;
    use App\Filament\Clusters\PersonelTakip\Pages\PersonelAyarlariSayfasi;
    use App\Filament\Clusters\PersonelTakip\Pages\PersonelPinTerminalSayfasi;
    use App\Filament\Clusters\PersonelTakip\Pages\PersonelRaporlariSayfasi;
    use App\Filament\Clusters\PersonelTakip\Pages\PersonelTakipOzetSayfasi;
    use App\Filament\Clusters\Restoran\Resources\RestoranAdisyonKaynagi;
    use App\Filament\Clusters\Restoran\Resources\RestoranMasaKaynagi;
    use App\Filament\Clusters\Restoran\Resources\RestoranMenuKategoriKaynagi;
    use App\Filament\Clusters\Restoran\Resources\RestoranMenuUrunKaynagi;
    use App\Filament\Clusters\Restoran\Resources\RestoranSalonKaynagi;
    use App\Filament\Clusters\Restoran\Pages\RestoranMasaEkraniSayfasi;
    use App\Filament\Clusters\Restoran\Pages\RestoranMutfakEkraniSayfasi;
    use App\Filament\Clusters\Restoran\Pages\RestoranPaketServisSayfasi;
    use App\Filament\Clusters\Restoran\Pages\RestoranRaporlariSayfasi;

    use App\Filament\Clusters\TeknikServis\Pages\BakimHatirlatmalariSayfasi;
    use App\Filament\Clusters\TeknikServis\Pages\GarantiliCihazlarSayfasi;
    use App\Filament\Clusters\TeknikServis\Pages\ServisFormuSablonuSayfasi;
    use App\Filament\Clusters\TeknikServis\Pages\ServisFisiSablonuSayfasi;
    use App\Filament\Clusters\TeknikServis\Pages\ServisKabulFormuSablonuSayfasi;
    use App\Filament\Clusters\TeknikServis\Pages\ServisTalepFormuSablonuSayfasi;
    use App\Filament\Clusters\TeknikServis\Pages\TeknikServisDashboardSayfasi;
    use App\Filament\Clusters\TeknikServis\Pages\TeknikServisDurumBazliRaporuSayfasi;
    use App\Filament\Clusters\TeknikServis\Pages\TeknikServisGarantiBakimRaporuSayfasi;
    use App\Filament\Clusters\TeknikServis\Pages\TeknikServisGenelAyarlarSayfasi;
    use App\Filament\Clusters\TeknikServis\Pages\TeknikServisIslemLoglariSayfasi;
    use App\Filament\Clusters\TeknikServis\Pages\TeknikServisKarlilikRaporuSayfasi;
    use App\Filament\Clusters\TeknikServis\Pages\TeknikServisMesajGecmisiSayfasi;
    use App\Filament\Clusters\TeknikServis\Pages\TeknikServisPersonelPerformansRaporuSayfasi;
    use App\Filament\Clusters\TeknikServis\Pages\TeknikServisTahsilatServisRaporuSayfasi;
    use App\Filament\Clusters\TeknikServis\Pages\TelegramSayfasi;
    use App\Filament\Clusters\TeknikServis\Pages\TeslimEdildiBelgesiSablonuSayfasi;
    use App\Filament\Clusters\TeknikServis\Pages\WhatsappSablonlariSayfasi;
    use App\Filament\Clusters\TeknikServis\Resources\TeknikServisAksesuarKaynagi;
    use App\Filament\Clusters\TeknikServis\Resources\TeknikServisArizaKaynagi;
    use App\Filament\Clusters\TeknikServis\Resources\TeknikServisCihazKaynagi;
    use App\Filament\Clusters\TeknikServis\Resources\TeknikServisDurumuTanimKaynagi;
    use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi;
    use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKayitliCihaziKaynagi;
    use App\Filament\Clusters\TeknikServis\Resources\TeknikServisMarkaKaynagi;
    use App\Filament\Clusters\Sekreter\Pages\AjandaSayfasi;
    use App\Filament\Clusters\Sekreter\Pages\GenelBakisSayfasi;
    use App\Filament\Clusters\Sekreter\Resources\GorevKaynagi;
    use App\Filament\Clusters\Sekreter\Resources\NotKaynagi;

    use App\Filament\Clusters\Web\Pages\BilgiSayfalari;
    use App\Filament\Clusters\Web\Pages\Iletisim;
    use App\Filament\Clusters\Web\Pages\Hakkimizda;
    use App\Filament\Clusters\Web\Pages\WebModuller;
    use App\Filament\Clusters\Web\Pages\WebServisListesi;
    use App\Filament\Clusters\Web\Pages\WebServisKategori;
    use App\Filament\Clusters\Web\Pages\WebServisAyarlar;
    use App\Filament\Clusters\Web\Pages\WebProje;
    use App\Filament\Clusters\Web\Pages\WebProjeKategori;
    use App\Filament\Clusters\Web\Pages\WebProjeAyarlar;
    use App\Filament\Clusters\Web\Pages\BlogListesi;
    use App\Filament\Clusters\Web\Pages\BlogKategori;
    use App\Filament\Clusters\Web\Pages\BlogAyarlar;
    use App\Filament\Clusters\Web\Pages\WebGenelAyarlar;
    use App\Filament\Clusters\Web\Pages\WebAboneler;
    use App\Filament\Clusters\Web\Pages\WebApiAyarlar;
    use App\Filament\Clusters\Web\Pages\WebMailGonderim;
    use App\Filament\Clusters\Web\Pages\WebMailAyarlar;
    use App\Filament\Clusters\Web\Pages\WebMailSablonlari;
    use App\Filament\Clusters\Web\Pages\ModulNedenBiz;
    use App\Filament\Clusters\Web\Pages\ModulNelerYapiyoruz;
    use App\Filament\Clusters\Web\Pages\ModulMenu;
    use App\Filament\Clusters\Web\Pages\ModulReferanslar;
    use App\Filament\Clusters\Web\Pages\ModulRakamlarlaBiz;
    use App\Filament\Clusters\Web\Pages\ModulTeknikDestek;
    use App\Filament\Clusters\Web\Pages\ModulMusteriYorumlari;
    use App\Filament\Clusters\Web\Pages\ModulFaqs;
    use App\Filament\Clusters\Web\Pages\UtilityMenuAyarlari;

    use App\Filament\Pages\FirmaAyarlariSayfasi;
    use App\Filament\Pages\SistemBakimModuSayfasi;
    use App\Filament\Pages\SistemYonetimiAyarlariSayfasi;
    use App\Filament\Clusters\Ayarlar\Pages\MesajMerkeziSayfasi;
    use App\Filament\Resources\FirmaKullaniciGrubuKaynagi;
    use App\Filament\Resources\FirmaIciKullaniciKaynagi;
    use App\Filament\Resources\FirmaYonetimKaynagi;
    use App\Filament\Resources\ModulYonetimKaynagi;
    use App\Models\FirmaKullanici;
    use App\Filament\Resources\DenetimKayidiKaynagi;
    use App\Filament\Resources\PlanYonetimKaynagi;
    use App\Filament\Resources\RolYonetimKaynagi;
    use App\Filament\Resources\UserResource;
    use App\Filament\Resources\YetkiYonetimKaynagi;
    use App\Support\MuhasebeYetkiSablonlari;
    use App\Support\MasrafTakipYetkiSablonlari;
    use App\Support\PersonelTakip\PersonelTakipYetkiSablonlari;
    use App\Support\Restoran\RestoranYetkiSablonlari;
    use App\Support\SaaSemaYardimcisi;
    use App\Support\TeklifYetkiSablonlari;
    use App\Filament\Clusters\Restoran\Resources\RestoranAdisyonKalemiKaynagi;

    $adminPrefix = \App\Providers\Filament\AdminPanelProvider::adminPath();
    $currentPath = request()->path();
    $horizontalLogo = app(\App\Services\AdminLogoServisi::class)->url();
    $isDashboard = request()->is($adminPrefix) || request()->is($adminPrefix.'/');
    $isSekreter = str_starts_with($currentPath, $adminPrefix.'/sekreter');

    $isDepoYonetimi = str_starts_with($currentPath, $adminPrefix.'/muhasebe/stok/depo-')
        || str_starts_with($currentPath, $adminPrefix.'/muhasebe/stok/depolar-')
        || str_starts_with($currentPath, $adminPrefix.'/muhasebe/tanimlar/depolar');
    $isMuhasebe = str_starts_with($currentPath, $adminPrefix.'/muhasebe') && ! $isDepoYonetimi;
    $isFatura = str_starts_with($currentPath, $adminPrefix.'/muhasebe/fatura')
        || str_starts_with($currentPath, $adminPrefix.'/muhasebe/faturalar');
    $isMuhasebeEntegrasyon = str_starts_with($currentPath, $adminPrefix.'/muhasebe/entegrasyonlar');
    $isFinans = str_starts_with($currentPath, $adminPrefix.'/muhasebe/finans');
    $isBarkodluSatis = str_starts_with($currentPath, $adminPrefix.'/muhasebe/satis');
    $isBarkodluSatisAyar = str_starts_with($currentPath, $adminPrefix.'/muhasebe/satis/barkodlu-satis-ayarlar')
        || str_starts_with($currentPath, $adminPrefix.'/muhasebe/satis/barkodlu-satis-fis-sablonlari');
    $isMuhasebeCari = str_starts_with($currentPath, $adminPrefix.'/muhasebe/cari-yonetimi');
    $isMuhasebeStok = str_starts_with($currentPath, $adminPrefix.'/muhasebe/stok') && ! $isDepoYonetimi;
    $isMuhasebeRapor = str_starts_with($currentPath, $adminPrefix.'/muhasebe/raporlar');
    $isMuhasebeTanim = str_starts_with($currentPath, $adminPrefix.'/muhasebe/tanimlar') && ! $isDepoYonetimi;
    $isMasrafTakip = str_starts_with($currentPath, $adminPrefix.'/masraf-takip');
    $isProjeYonetimi = str_starts_with($currentPath, $adminPrefix.'/proje-yonetimi');

    $isTeknikServis = str_starts_with($currentPath, $adminPrefix.'/teknik-servis');
    $isTeklifYonetimi = str_starts_with($currentPath, $adminPrefix.'/teklif-yonetimi');
    $isPersonelTakip = str_starts_with($currentPath, $adminPrefix.'/personel-takip');
    $isPersonelTanimlar = str_starts_with($currentPath, $adminPrefix.'/personel-takip/tanimlar');
    $isPersonelRaporlar = str_starts_with($currentPath, $adminPrefix.'/personel-takip/raporlar');
    $isRestoran = str_starts_with($currentPath, $adminPrefix.'/restoran');
    $isRestoranTanimlar = str_starts_with($currentPath, $adminPrefix.'/restoran/tanimlar');
    $isRestoranQrMenu = str_starts_with($currentPath, $adminPrefix.'/restoran/qr-menu');
    $tsKayitBase = $adminPrefix.'/teknik-servis/servis-kayitlari';
    $isTsKayitOlustur = str_starts_with($currentPath, $tsKayitBase.'/olustur');
    $isTsKayitListe = str_starts_with($currentPath, $tsKayitBase.'/liste');
    $isTsKayitIndexOrKayit = str_starts_with($currentPath, $tsKayitBase)
        && ! str_contains($currentPath, '/liste/')
        && ! str_contains($currentPath, '/olustur/');
    $isTsTanimlar = str_starts_with($currentPath, $adminPrefix.'/teknik-servis/tanimlar');
    $isTsRaporlar = str_starts_with($currentPath, $adminPrefix.'/teknik-servis/raporlar');
    $isTsGarantiHatirlatma = str_starts_with($currentPath, $adminPrefix.'/teknik-servis/operasyon/garantili-cihazlar')
        || str_starts_with($currentPath, $adminPrefix.'/teknik-servis/operasyon/bakim-hatirlatmalari');
    $isTsOperasyonLogMesaj = str_starts_with($currentPath, $adminPrefix.'/teknik-servis/operasyon/islem-loglari')
        || str_starts_with($currentPath, $adminPrefix.'/teknik-servis/operasyon/mesaj-gecmisi');
    $isTsGenelAyarlarSayfa = request()->is($adminPrefix.'/teknik-servis/genel-ayarlar');
    $isTsTelegramSayfa = request()->is($adminPrefix.'/teknik-servis/ayarlar/telegram');
    $isTsSablonlar = str_starts_with($currentPath, $adminPrefix.'/teknik-servis/sablonlar');
    $isTsAyarlarGrubu = $isTsTanimlar || $isTsGenelAyarlarSayfa || $isTsTelegramSayfa || $isTsSablonlar || $isTsOperasyonLogMesaj;

    $isUrunYonetimi = str_starts_with($currentPath, $adminPrefix.'/web/urunler')
        || str_starts_with($currentPath, $adminPrefix.'/products')
        || str_starts_with($currentPath, $adminPrefix.'/product-categories')
        || str_starts_with($currentPath, $adminPrefix.'/e-ticaret/varyasyon-yonetimi');
    $isETicaret = (str_starts_with($currentPath, $adminPrefix.'/e-ticaret')
        || str_starts_with($currentPath, $adminPrefix.'/siparisler'))
        && ! $isUrunYonetimi;
    $isETicaretSiparis = str_starts_with($currentPath, $adminPrefix.'/siparisler');
    $isETicaretMesaj = str_starts_with($currentPath, $adminPrefix.'/e-ticaret/mesaj-yonetimi');
    $isETicaretKampanya = str_starts_with($currentPath, $adminPrefix.'/e-ticaret/kampanya-yonetimi');
    $isETicaretPazaryeri = str_starts_with($currentPath, $adminPrefix.'/e-ticaret/pazaryeri-entegrasyonu');
    $isETicaretKargo = str_starts_with($currentPath, $adminPrefix.'/e-ticaret/kargo-yonetimi');
    $isETicaretOdeme = str_starts_with($currentPath, $adminPrefix.'/e-ticaret/odeme-yonetimi');
    $isETicaretBildirim = str_starts_with($currentPath, $adminPrefix.'/e-ticaret/bildirim-yonetimi');

    $isWeb = str_starts_with($currentPath, $adminPrefix.'/web') && ! $isUrunYonetimi;
    $isSayfalar = str_starts_with($currentPath, $adminPrefix.'/web/sayfalar');
    $isServisler = str_starts_with($currentPath, $adminPrefix.'/web/servisler');
    $isProjeler = str_starts_with($currentPath, $adminPrefix.'/web/projeler');
    $isBloglar = str_starts_with($currentPath, $adminPrefix.'/web/bloglar');
    $isWebAyarlar = str_starts_with($currentPath, $adminPrefix.'/web/web-ayarlar');
    $isWebMenuAyarlari = str_starts_with($currentPath, $adminPrefix.'/web/web-ayarlar/menu-ayarlari');
    $isWebModuller = str_starts_with($currentPath, $adminPrefix.'/web/web-moduller');
    $isAbonelikSistemi = request()->is($adminPrefix.'/web/web-ayarlar/aboneler')
        || request()->is($adminPrefix.'/web/web-ayarlar/mail-gonderim')
        || request()->is($adminPrefix.'/web/web-ayarlar/mail-sablonlari');

    $isAyarlar = str_starts_with($currentPath, $adminPrefix.'/ayarlar');
    $isMesajMerkezi = str_starts_with($currentPath, $adminPrefix.'/ayarlar/mesaj-merkezi');
    $isKullaniciAyarlari = str_starts_with($currentPath, $adminPrefix.'/ayarlar/kullanici-ayarlari')
        || str_starts_with($currentPath, $adminPrefix.'/firma-ici-kullanicilar')
        || str_starts_with($currentPath, $adminPrefix.'/firma-kullanici-gruplari');
    $isFirmaIciKullanicilar = str_starts_with($currentPath, $adminPrefix.'/firma-ici-kullanicilar');
    $isFirmaKullaniciGruplari = str_starts_with($currentPath, $adminPrefix.'/firma-kullanici-gruplari');
    $isFirmaAyarlariSayfasi = request()->is($adminPrefix.'/firma-ayarlari');
    $isSistemYonetimi = str_starts_with($currentPath, $adminPrefix.'/sistem-');
    $hasLegacyProductRoutes = \Illuminate\Support\Facades\Route::has('filament.admin.resources.products.index')
        && \Illuminate\Support\Facades\Route::has('filament.admin.resources.product-categories.index');
    $hasWebProductRoutes = \Illuminate\Support\Facades\Route::has('filament.admin.web.resources.urunler.urun-listesi.index')
        && \Illuminate\Support\Facades\Route::has('filament.admin.web.resources.urunler.urun-kategorileri.index');
    $hasProductAdminRoutes = $hasLegacyProductRoutes || $hasWebProductRoutes;

    $aktifKullanici = auth()->user();
    $superAdminMi = $aktifKullanici
        && ((bool) ($aktifKullanici->super_admin_mi ?? false) || (bool) ($aktifKullanici->is_admin ?? false));
    $tenantContext = app(\App\Services\TenantContextService::class);
    $aktifFirmaId = $tenantContext->aktifFirmaId();
    $sidebarService = app(\App\Services\SidebarService::class);
    $can = fn (?string $modulKodu, ?string $yetkiKodu): bool => $sidebarService->menuGorunurMu($aktifKullanici, $aktifFirmaId, $modulKodu, $yetkiKodu);
    $sekreterBolumuGorunur = $can('sekreter', 'sekreter.goruntule');
    $muhasebeYetki = fn (string $yetkiKodu): bool => $can('muhasebe', $yetkiKodu);
    $masrafTakipYetki = fn (string $yetkiKodu): bool => $can('masraf_takip', $yetkiKodu);
    $barkodluSatisYetki = fn (string $yetkiKodu): bool => $can('barkodlu_satis', $yetkiKodu);
    $cariMenusuHerhangi = $muhasebeYetki(MuhasebeYetkiSablonlari::CARI_GORUNTULE)
        || $muhasebeYetki(MuhasebeYetkiSablonlari::CARI_OLUSTUR)
        || $muhasebeYetki(MuhasebeYetkiSablonlari::CARI_GUNCELLE)
        || $muhasebeYetki(MuhasebeYetkiSablonlari::CARI_SIL);
    $cariListeRaporMenusu = $muhasebeYetki(MuhasebeYetkiSablonlari::CARI_GORUNTULE)
        || $muhasebeYetki(MuhasebeYetkiSablonlari::CARI_GUNCELLE)
        || $muhasebeYetki(MuhasebeYetkiSablonlari::CARI_SIL);
    $posMenusuHerhangi = $muhasebeYetki(MuhasebeYetkiSablonlari::POS_GORUNTULE)
        || $muhasebeYetki(MuhasebeYetkiSablonlari::POS_OLUSTUR)
        || $muhasebeYetki(MuhasebeYetkiSablonlari::POS_GUNCELLE)
        || $muhasebeYetki(MuhasebeYetkiSablonlari::POS_SIL);
    $posListeMenusu = $muhasebeYetki(MuhasebeYetkiSablonlari::POS_GORUNTULE)
        || $muhasebeYetki(MuhasebeYetkiSablonlari::POS_GUNCELLE)
        || $muhasebeYetki(MuhasebeYetkiSablonlari::POS_SIL);
    $stokMenusuHerhangi = $muhasebeYetki(MuhasebeYetkiSablonlari::STOK_GORUNTULE)
        || $muhasebeYetki(MuhasebeYetkiSablonlari::STOK_OLUSTUR)
        || $muhasebeYetki(MuhasebeYetkiSablonlari::STOK_GUNCELLE)
        || $muhasebeYetki(MuhasebeYetkiSablonlari::STOK_SIL);
    $stokListeMenusu = $muhasebeYetki(MuhasebeYetkiSablonlari::STOK_GORUNTULE)
        || $muhasebeYetki(MuhasebeYetkiSablonlari::STOK_GUNCELLE)
        || $muhasebeYetki(MuhasebeYetkiSablonlari::STOK_SIL);
    $depoMenusuHerhangi = $muhasebeYetki(MuhasebeYetkiSablonlari::DEPO_GORUNTULE)
        || $muhasebeYetki(MuhasebeYetkiSablonlari::DEPO_OLUSTUR)
        || $muhasebeYetki(MuhasebeYetkiSablonlari::DEPO_GUNCELLE);
    $faturaMenusuHerhangi = $muhasebeYetki(MuhasebeYetkiSablonlari::FATURA_GORUNTULE)
        || $muhasebeYetki(MuhasebeYetkiSablonlari::FATURA_OLUSTUR)
        || $muhasebeYetki(MuhasebeYetkiSablonlari::FATURA_GUNCELLE)
        || $muhasebeYetki(MuhasebeYetkiSablonlari::FATURA_SIL)
        || $muhasebeYetki(MuhasebeYetkiSablonlari::FATURA_ONAY);
    $barkodluSatisMenusuHerhangi = $barkodluSatisYetki(MuhasebeYetkiSablonlari::BARKODLU_SATIS_GORUNTULE)
        || $barkodluSatisYetki(MuhasebeYetkiSablonlari::BARKODLU_SATIS_OLUSTUR)
        || $barkodluSatisYetki(MuhasebeYetkiSablonlari::BARKODLU_SATIS_GUNCELLE)
        || $barkodluSatisYetki(MuhasebeYetkiSablonlari::BARKODLU_SATIS_ETIKET_YAZDIR)
        || $barkodluSatisYetki(MuhasebeYetkiSablonlari::BARKODLU_SATIS_IPTAL)
        || $barkodluSatisYetki(MuhasebeYetkiSablonlari::BARKODLU_SATIS_IADE);
    $teklifYonetimiMenusuHerhangi = $can('teklif_yonetimi', TeklifYetkiSablonlari::GORUNTULE)
        || $can('teklif_yonetimi', TeklifYetkiSablonlari::OLUSTUR)
        || $can('teklif_yonetimi', TeklifYetkiSablonlari::GUNCELLE)
        || $can('teklif_yonetimi', TeklifYetkiSablonlari::SIL);
    $teklifListeMenusu = $can('teklif_yonetimi', TeklifYetkiSablonlari::GORUNTULE)
        || $can('teklif_yonetimi', TeklifYetkiSablonlari::GUNCELLE)
        || $can('teklif_yonetimi', TeklifYetkiSablonlari::SIL);
    $bolum = fn (string $anahtar): bool => $sidebarService->sidebarBolumGorunurMu($aktifKullanici, $aktifFirmaId, $anahtar);
    $masrafTakipBolumuGorunur = $bolum('masraf_takip')
        || $masrafTakipYetki(MasrafTakipYetkiSablonlari::GORUNTULE)
        || $masrafTakipYetki(MasrafTakipYetkiSablonlari::OLUSTUR)
        || $masrafTakipYetki(MasrafTakipYetkiSablonlari::GUNCELLE)
        || $masrafTakipYetki(MasrafTakipYetkiSablonlari::SIL);
    $projeYonetimiBolumuGorunur = $bolum('proje_yonetimi') || $masrafTakipBolumuGorunur;
    $personelYetki = fn (string $yetkiKodu): bool => $can('personel_takip', $yetkiKodu);
    $personelBolumuGorunur = $bolum('personel_takip')
        || $personelYetki(PersonelTakipYetkiSablonlari::GORUNTULE)
        || $personelYetki(PersonelTakipYetkiSablonlari::VARDIYA_GORUNTULE)
        || $personelYetki(PersonelTakipYetkiSablonlari::GIRIS_CIKIS_GORUNTULE)
        || $personelYetki(PersonelTakipYetkiSablonlari::GIRIS_CIKIS_ONAYLA)
        || $personelYetki(PersonelTakipYetkiSablonlari::IZIN_GORUNTULE)
        || $personelYetki(PersonelTakipYetkiSablonlari::IZIN_OLUSTUR)
        || $personelYetki(PersonelTakipYetkiSablonlari::IZIN_DUZENLE)
        || $personelYetki(PersonelTakipYetkiSablonlari::AVANS_GORUNTULE)
        || $personelYetki(PersonelTakipYetkiSablonlari::MAAS_GORUNTULE)
        || $personelYetki(PersonelTakipYetkiSablonlari::RAPOR_GORUNTULE)
        || $personelYetki(PersonelTakipYetkiSablonlari::TANIM_GORUNTULE);
    $personelListeGorunur = $personelYetki(PersonelTakipYetkiSablonlari::GORUNTULE)
        || $personelYetki(PersonelTakipYetkiSablonlari::GUNCELLE)
        || $personelYetki(PersonelTakipYetkiSablonlari::SIL);
    $personelVardiyaGorunur = $personelYetki(PersonelTakipYetkiSablonlari::VARDIYA_GORUNTULE)
        || $personelYetki(PersonelTakipYetkiSablonlari::VARDIYA_DUZENLE);
    $personelGirisCikisGorunur = $personelYetki(PersonelTakipYetkiSablonlari::GIRIS_CIKIS_GORUNTULE)
        || $personelYetki(PersonelTakipYetkiSablonlari::GIRIS_CIKIS_DUZENLE)
        || $personelYetki(PersonelTakipYetkiSablonlari::GIRIS_CIKIS_ONAYLA);
    $personelIzinGorunur = $personelYetki(PersonelTakipYetkiSablonlari::IZIN_GORUNTULE)
        || $personelYetki(PersonelTakipYetkiSablonlari::IZIN_OLUSTUR)
        || $personelYetki(PersonelTakipYetkiSablonlari::IZIN_DUZENLE)
        || $personelYetki(PersonelTakipYetkiSablonlari::IZIN_ONAYLA);
    $personelAvansGorunur = $personelYetki(PersonelTakipYetkiSablonlari::AVANS_GORUNTULE)
        || $personelYetki(PersonelTakipYetkiSablonlari::AVANS_OLUSTUR)
        || $personelYetki(PersonelTakipYetkiSablonlari::AVANS_ONAYLA);
    $personelMaasGorunur = $personelYetki(PersonelTakipYetkiSablonlari::MAAS_GORUNTULE)
        || $personelYetki(PersonelTakipYetkiSablonlari::MAAS_HESAPLA)
        || $personelYetki(PersonelTakipYetkiSablonlari::MAAS_ODEME_YAP);
    $personelRaporGorunur = $personelYetki(PersonelTakipYetkiSablonlari::RAPOR_GORUNTULE);
    $personelTanimGorunur = $personelYetki(PersonelTakipYetkiSablonlari::TANIM_GORUNTULE)
        || $personelYetki(PersonelTakipYetkiSablonlari::TANIM_GUNCELLE);
    $restoranYetki = fn (string $yetkiKodu): bool => $can('restoran', $yetkiKodu);
    $restoranBolumuGorunur = $bolum('restoran')
        || $restoranYetki(RestoranYetkiSablonlari::GORUNTULE)
        || $restoranYetki(RestoranYetkiSablonlari::MASA_GORUNTULE)
        || $restoranYetki(RestoranYetkiSablonlari::ADISYON_GORUNTULE)
        || $restoranYetki(RestoranYetkiSablonlari::QR_MENU_GORUNTULE)
        || $restoranYetki(RestoranYetkiSablonlari::RAPOR_GORUNTULE);
    $restoranMasaGorunur = $restoranYetki(RestoranYetkiSablonlari::MASA_GORUNTULE)
        || $restoranYetki(RestoranYetkiSablonlari::MASA_DUZENLE);
    $restoranAdisyonGorunur = $restoranYetki(RestoranYetkiSablonlari::ADISYON_GORUNTULE)
        || $restoranYetki(RestoranYetkiSablonlari::ADISYON_OLUSTUR)
        || $restoranYetki(RestoranYetkiSablonlari::ADISYON_GUNCELLE)
        || $restoranYetki(RestoranYetkiSablonlari::ADISYON_TAHSILAT);
    $restoranMutfakGorunur = $restoranYetki(RestoranYetkiSablonlari::MUTFAK_GORUNTULE)
        || $restoranYetki(RestoranYetkiSablonlari::MUTFAK_GUNCELLE);
    $restoranPaketServisGorunur = $restoranYetki(RestoranYetkiSablonlari::PAKET_SERVIS_GORUNTULE)
        || $restoranYetki(RestoranYetkiSablonlari::PAKET_SERVIS_GUNCELLE);
    $restoranQrMenuGorunur = $restoranYetki(RestoranYetkiSablonlari::QR_MENU_GORUNTULE)
        || $restoranYetki(RestoranYetkiSablonlari::QR_MENU_GUNCELLE);
    $restoranRaporGorunur = $restoranYetki(RestoranYetkiSablonlari::RAPOR_GORUNTULE);
    $eTicaretBolumuGorunur = $bolum('e_ticaret');
    $eTicaretSiparisGorunur = $can('e_ticaret', 'e_ticaret_siparis.goruntule')
        || $can('e_ticaret', 'e_ticaret_siparis.guncelle');
    $eTicaretMesajGorunur = $can('e_ticaret', 'e_ticaret_mesaj.goruntule')
        || $can('e_ticaret', 'e_ticaret_mesaj.guncelle');
    $eTicaretMusteriMesajGorunur = $can('e_ticaret', 'e_ticaret_mesaj.musteri_goruntule');
    $eTicaretUrunMesajGorunur = $can('e_ticaret', 'e_ticaret_mesaj.urun_goruntule');
    $eTicaretKampanyaGorunur = $can('e_ticaret', 'e_ticaret_kampanya.goruntule')
        || $can('e_ticaret', 'e_ticaret_kampanya.guncelle');
    $eTicaretPazaryeriGorunur = $can('e_ticaret', 'e_ticaret_pazaryeri.goruntule')
        || $can('e_ticaret', 'e_ticaret_pazaryeri.guncelle');
    $eTicaretVaryasyonGorunur = $can('e_ticaret', 'e_ticaret_varyasyon.goruntule')
        || $can('e_ticaret', 'e_ticaret_varyasyon.guncelle');
    $eTicaretKargoGorunur = $can('e_ticaret', 'e_ticaret_kargo.goruntule')
        || $can('e_ticaret', 'e_ticaret_kargo.guncelle');
    $eTicaretOdemeGorunur = $can('e_ticaret', 'e_ticaret_odeme.goruntule')
        || $can('e_ticaret', 'e_ticaret_odeme.guncelle');
    $eTicaretBildirimGorunur = $can('e_ticaret', 'e_ticaret_bildirim.goruntule')
        || $can('e_ticaret', 'e_ticaret_bildirim.guncelle');
    $urunYonetimiGorunur = $hasProductAdminRoutes
        && ($can('web', 'urun.goruntule') || $can('web', 'urun_kategori.goruntule') || $eTicaretVaryasyonGorunur);
    $webBolumuGorunur = $bolum('web');

    $firmaAyarlariGorunur = $aktifFirmaId
        && SaaSemaYardimcisi::firmalarTablosuVarMi()
        && ($__firmaCtx = $tenantContext->aktifFirma())
        && $aktifKullanici?->can('update', $__firmaCtx);
    $firmaIciKullanicilarGorunur = SaaSemaYardimcisi::firmaKullanicilariTablosuVarMi()
        && ($aktifKullanici?->can('viewAny', FirmaKullanici::class) ?? false);

    $sistemFirmalarMenusu = $superAdminMi && SaaSemaYardimcisi::firmalarTablosuVarMi();
    $sistemPlanlarMenusu = $superAdminMi && SaaSemaYardimcisi::planlarTablosuVarMi();
    $sistemModullerMenusu = $superAdminMi && SaaSemaYardimcisi::modullerTablosuVarMi();
    $sistemYetkilerMenusu = $superAdminMi && SaaSemaYardimcisi::yetkilerTablosuVarMi();
    $sistemRollerMenusu = $superAdminMi && SaaSemaYardimcisi::rollerTablosuVarMi();
    $sistemDenetimMenusu = $superAdminMi && SaaSemaYardimcisi::tabloVarMi('denetim_kayitlari');
    $sistemKullanicilarMenusu = $superAdminMi && SaaSemaYardimcisi::tabloVarMi('users');
    $sistemBakimModuMenusu = $superAdminMi;
    $sistemYonetimiMenusuGorunur = $sistemModullerMenusu || $sistemYetkilerMenusu || $sistemRollerMenusu || $sistemFirmalarMenusu || $sistemPlanlarMenusu || $sistemDenetimMenusu || $sistemKullanicilarMenusu || $sistemBakimModuMenusu;

    // Pasif modüller tanıtım amacıyla görünür; içeriklerine erişim yerine
    // merkezi bilgilendirme sayfasına yönlendirilirler.
    $pasifModuller = collect();
    if ($aktifKullanici && $aktifFirmaId && ! $superAdminMi && SaaSemaYardimcisi::modullerTablosuVarMi()) {
        $modulErisim = app(\App\Services\ModulErisimService::class);
        $pasifModuller = \App\Models\Modul::query()
            ->where('aktif_mi', true)
            ->orderBy('siralama')
            ->orderBy('ad')
            ->get()
            ->filter(fn (\App\Models\Modul $modul): bool => $modulErisim->modulDurumu((int) $aktifFirmaId, (string) $modul->kod) === 'kapali')
            ->values();
    }
    $pasifModul = fn (string $kod) => $pasifModuller->firstWhere('kod', $kod);
    $pasifModulIkonu = fn (string $kod): string => match ($kod) {
        'depo', 'restoran' => 'heroicon-o-building-storefront',
        'urunler' => 'heroicon-o-cube',
        'masraf_takip' => 'heroicon-o-receipt-percent',
        'proje_yonetimi' => 'heroicon-o-building-office-2',
        'teklif_yonetimi' => 'heroicon-o-document-currency-dollar',
        'personel_takip' => 'heroicon-o-user-group',
        'barkodlu_satis' => 'heroicon-o-qr-code',
        'teknik_servis' => 'heroicon-o-wrench-screwdriver',
        'e_ticaret' => 'heroicon-o-shopping-cart',
        'web' => 'heroicon-o-globe-alt',
        default => 'heroicon-o-cube',
    };
@endphp

<style>
    body.fi-panel-admin.saas-layout-horizontal {
        --saas-admin-logo-image: url("{{ str_replace(['\\', '"', "\n", "\r"], ['', '\\"', '', ''], $horizontalLogo) }}");
    }
    body.fi-panel-admin .custom-sidebar .nav-item--locked .nav-item-icon {
        color: #f59e0b !important;
    }
</style>

<div
    x-data="{
        sekreterOpen: @js($isSekreter),
        muhasebeOpen: @js($isMuhasebe),
        muhasebeCariOpen: @js($isMuhasebeCari),
        muhasebeStokOpen: @js($isMuhasebeStok),
        depoYonetimiOpen: @js($isDepoYonetimi),
        muhasebeFaturalarOpen: @js($isFatura || $isMuhasebeEntegrasyon),
        finansOpen: @js($isFinans),
        teklifYonetimiOpen: @js($isTeklifYonetimi),
        personelTakipOpen: @js($isPersonelTakip),
        personelTanimlarOpen: @js($isPersonelTanimlar),
        personelRaporlarOpen: @js($isPersonelRaporlar),
        restoranOpen: @js($isRestoran),
        restoranTanimlarOpen: @js($isRestoranTanimlar),
        restoranQrMenuOpen: @js($isRestoranQrMenu),
        barkodluSatisOpen: @js($isBarkodluSatis),
        barkodluSatisAyarOpen: @js($isBarkodluSatisAyar),
        muhasebeRaporOpen: @js($isMuhasebeRapor),
        muhasebeTanimOpen: @js($isMuhasebeTanim),
        masrafTakipOpen: @js($isMasrafTakip),
        projeYonetimiOpen: @js($isProjeYonetimi),
        teknikServisOpen: @js($isTeknikServis),
        tsKayitCreateOpen: @js($isTsKayitOlustur),
        tsKayitListeOpen: @js($isTsKayitListe || $isTsKayitIndexOrKayit),
        tsGarantiOpen: @js($isTsGarantiHatirlatma),
        tsRaporOpen: @js($isTsRaporlar),
        tsAyarlarOpen: @js($isTsAyarlarGrubu),
        tsSablonlarOpen: @js($isTsSablonlar),
        eTicaretOpen: @js($isETicaret),
        eTicaretMesajOpen: @js($isETicaretMesaj),
        eTicaretBildirimOpen: @js($isETicaretBildirim),
        urunYonetimiOpen: @js($isUrunYonetimi),
        webOpen: @js($isWeb),
        sayfalarOpen: @js($isSayfalar),
        servislerOpen: @js($isServisler),
        projelerOpen: @js($isProjeler),
        bloglarOpen: @js($isBloglar),
        webAyarlarOpen: @js($isWebAyarlar),
        webMenuAyarlariOpen: @js($isWebMenuAyarlari),
        webModullerOpen: @js($isWebModuller),
        abonelikSistemiOpen: @js($isAbonelikSistemi),
        ayarlarOpen: @js($isAyarlar),
        kullaniciAyarlariOpen: @js($isKullaniciAyarlari),
        sistemYonetimiOpen: @js($isSistemYonetimi),
        depoYonetimiLinkClick() {
            this.depoYonetimiOpen = true
            this.muhasebeOpen = false
            this.muhasebeCariOpen = false
            this.muhasebeStokOpen = false
            this.muhasebeFaturalarOpen = false
            this.finansOpen = false
            this.muhasebeRaporOpen = false
            this.muhasebeTanimOpen = false
        },
        urunYonetimiLinkClick() {
            this.urunYonetimiOpen = true
            this.eTicaretOpen = false
            this.webOpen = false
        },
        closeHorizontalGroup(group) {
            if (! group) return

            const trigger = group.previousElementSibling
            group.classList.remove('is-horizontal-open')

            if (trigger?.getAttribute('aria-expanded') === 'true') {
                trigger.dispatchEvent(new MouseEvent('click', { bubbles: false }))
            }
        },
        closeHorizontalDropdowns(root, except = null) {
            const container = except?.parentElement ?? root.querySelector(':scope > nav')

            container?.querySelectorAll(':scope > .nav-group.is-horizontal-open').forEach((group) => {
                if (group !== except) this.closeHorizontalGroup(group)
            })
        },
        toggleHorizontalDropdown(event, root) {
            if (! document.body.classList.contains('saas-layout-horizontal')) return

            const nav = root.querySelector(':scope > nav')
            const trigger = event.target.closest('button.nav-item')

            // Ana ve iç içe menü düğmelerini destekle; yalnızca bu sidebar
            // içindeki düğmeleri işleme al.
            if (! trigger || ! nav.contains(trigger)) return

            const group = trigger.nextElementSibling
            if (! group?.classList.contains('nav-group')) return

            const shouldOpen = ! group.classList.contains('is-horizontal-open')
            this.closeHorizontalDropdowns(root, group)

            if (! shouldOpen) {
                group.style.removeProperty('inset-inline-start')
                group.style.removeProperty('top')
                group.classList.remove('is-horizontal-open')
                return
            }

            group.style.top = `${trigger.offsetTop + trigger.offsetHeight + 2}px`
            group.classList.add('is-horizontal-open')
            const maxLeft = Math.max(0, nav.clientWidth - group.offsetWidth)
            group.style.insetInlineStart = `${Math.min(trigger.offsetLeft, maxLeft)}px`
        },
    }"
    x-init="$nextTick(() => {
        if (! document.body.classList.contains('saas-layout-horizontal')) return

        $root.querySelectorAll(':scope > nav > button.nav-item[aria-expanded=true]').forEach((button) => {
            button.dispatchEvent(new MouseEvent('click', { bubbles: false }))
        })
    })"
    x-on:click="toggleHorizontalDropdown($event, $root)"
    x-on:click.outside="closeHorizontalDropdowns($root)"
    x-on:keydown.escape.stop="closeHorizontalDropdowns($root)"
    class="custom-sidebar"
    data-admin-navigation
>
    <a href="{{ url($adminPrefix) }}" class="horizontal-sidebar-brand" aria-label="Yönetim paneli ana sayfa">
        <img src="{{ $horizontalLogo }}" alt="Yalova Bilgisayar" loading="lazy" />
    </a>
    <nav class="flex flex-col gap-0.5" aria-label="Ana menü">
        {{-- Dashboard --}}
        <a
            href="{{ url(\App\Providers\Filament\AdminPanelProvider::adminPath()) }}"
            class="nav-item {{ $isDashboard ? 'is-active' : '' }}"
        >
            <span class="nav-item-start">
                <x-filament::icon icon="heroicon-o-presentation-chart-bar" class="nav-item-icon" />
                <span>Gösterge Paneli</span>
            </span>
        </a>

        @if($sistemYonetimiMenusuGorunur)
        <div class="section-gap" aria-hidden="true"></div>
        <button
            type="button"
            class="nav-item {{ $isSistemYonetimi ? 'is-active' : '' }}"
            x-on:click="sistemYonetimiOpen = !sistemYonetimiOpen"
            :aria-expanded="sistemYonetimiOpen"
        >
            <span class="nav-item-start">
                <x-filament::icon icon="heroicon-o-server-stack" class="nav-item-icon" />
                <span>Sistem yönetimi</span>
            </span>
            <svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
        </button>
        <div x-show="sistemYonetimiOpen" x-collapse class="nav-group">
            @if($sistemModullerMenusu)
            <a
                href="{{ ModulYonetimKaynagi::getUrl() }}"
                class="nav-item {{ str_starts_with($currentPath, $adminPrefix.'/sistem-moduller') ? 'is-active' : '' }}"
            ><span>Modüller</span></a>
            @endif
            @if($sistemYetkilerMenusu)
            <a
                href="{{ YetkiYonetimKaynagi::getUrl() }}"
                class="nav-item {{ str_starts_with($currentPath, $adminPrefix.'/sistem-yetkiler') ? 'is-active' : '' }}"
            ><span>Yetkiler</span></a>
            @endif
            @if($sistemRollerMenusu)
            <a
                href="{{ RolYonetimKaynagi::getUrl() }}"
                class="nav-item {{ str_starts_with($currentPath, $adminPrefix.'/sistem-roller') ? 'is-active' : '' }}"
            ><span>Roller</span></a>
            @endif
            @if($sistemFirmalarMenusu)
            <a
                href="{{ FirmaYonetimKaynagi::getUrl() }}"
                class="nav-item {{ str_starts_with($currentPath, $adminPrefix.'/sistem-firmalar') ? 'is-active' : '' }}"
            ><span>Firmalar</span></a>
            @endif
            @if($sistemKullanicilarMenusu)
            <a
                href="{{ UserResource::getUrl() }}"
                class="nav-item {{ str_starts_with($currentPath, $adminPrefix.'/sistem-kullanicilar') ? 'is-active' : '' }}"
            ><span>Kullanıcılar (users)</span></a>
            @endif
            @if($sistemPlanlarMenusu)
            <a
                href="{{ PlanYonetimKaynagi::getUrl() }}"
                class="nav-item {{ str_starts_with($currentPath, $adminPrefix.'/sistem-planlar') ? 'is-active' : '' }}"
            ><span>Planlar</span></a>
            @endif
            @if($sistemDenetimMenusu)
            <a
                href="{{ DenetimKayidiKaynagi::getUrl() }}"
                class="nav-item {{ str_starts_with($currentPath, $adminPrefix.'/sistem-denetim-kayitlari') ? 'is-active' : '' }}"
            ><span>Denetim kayıtları</span></a>
            @endif
            @if($sistemBakimModuMenusu)
            <a
                href="{{ SistemBakimModuSayfasi::getUrl() }}"
                class="nav-item {{ request()->is($adminPrefix.'/sistem-bakim-modu') ? 'is-active' : '' }}"
            ><span>Bakım modu</span></a>
            <a
                href="{{ SistemYonetimiAyarlariSayfasi::getUrl() }}"
                class="nav-item {{ request()->is($adminPrefix.'/sistem-ayarlari') ? 'is-active' : '' }}"
            ><span>Sistem ayarları</span></a>
            @endif
        </div>
        @endif

        @if($sekreterBolumuGorunur)
        <div class="section-gap" aria-hidden="true"></div>
        <button type="button" class="nav-item {{ $isSekreter ? 'is-active' : '' }}" x-on:click="sekreterOpen = !sekreterOpen" :aria-expanded="sekreterOpen">
            <span class="nav-item-start"><x-filament::icon icon="heroicon-o-clipboard-document-list" class="nav-item-icon" /><span>Ajanda ve Görevler</span></span>
            <svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
        </button>
        <div x-show="sekreterOpen" x-collapse class="nav-group">
            <a href="{{ GenelBakisSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/sekreter/genel-bakis') ? 'is-active' : '' }}"><span>Genel Bakış</span></a>
            <a href="{{ AjandaSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/sekreter/ajanda') ? 'is-active' : '' }}"><span>Ajanda</span></a>
            @if($can('sekreter', 'sekreter.goruntule'))
                <a href="{{ GorevKaynagi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/sekreter/gorevler*') ? 'is-active' : '' }}"><span>Görevler</span></a>
                <a href="{{ NotKaynagi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/sekreter/notlar*') ? 'is-active' : '' }}"><span>Notlar</span></a>
            @endif
        </div>
        @endif

        @if($bolum('muhasebe'))
        <div class="section-gap" aria-hidden="true"></div>

        {{-- Muhasebe --}}
        <button
            type="button"
            class="nav-item {{ $isMuhasebe ? 'is-active' : '' }}"
            x-on:click="muhasebeOpen = !muhasebeOpen"
            :aria-expanded="muhasebeOpen"
        >
            <span class="nav-item-start">
                <x-filament::icon icon="heroicon-o-currency-dollar" class="nav-item-icon" />
                <span>Muhasebe</span>
            </span>
            <svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
        </button>
        <div x-show="muhasebeOpen" x-collapse class="nav-group">
            @if($muhasebeYetki(MuhasebeYetkiSablonlari::MUHASEBE_GORUNTULE))
                <a href="{{ MuhasebeDashboardSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/muhasebe-panel') ? 'is-active' : '' }}"><span>Muhasebe özeti</span></a>
            @endif

            @if($cariMenusuHerhangi)
                <button type="button" class="nav-item {{ $isMuhasebeCari ? 'is-active' : '' }}" x-on:click="muhasebeCariOpen = !muhasebeCariOpen" :aria-expanded="muhasebeCariOpen">
                    <span>Cari Yönetimi</span><svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                </button>
                <div x-show="muhasebeCariOpen" x-collapse class="nav-group">
                    @if($cariListeRaporMenusu)
                        <a href="{{ CariKartiKaynagi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/cari-yonetimi/cariler*') ? 'is-active' : '' }}"><span>Cari Listesi</span></a>
                        <a href="{{ CariHareketleriSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/cari-yonetimi/cari-hareketleri') ? 'is-active' : '' }}"><span>Cari Hareketleri</span></a>
                        <a href="{{ CariEkstreSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/cari-yonetimi/cari-ekstreleri') ? 'is-active' : '' }}"><span>Cari Ekstreleri</span></a>
                        <a href="{{ CariYaslandirmaSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/cari-yonetimi/cari-yaslandirma') ? 'is-active' : '' }}"><span>Cari Yaşlandırma</span></a>
                    @endif
                    @if($muhasebeYetki(MuhasebeYetkiSablonlari::CARI_OLUSTUR))
                        <a href="{{ CariKartiKaynagi::getUrl('create') }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/cari-yonetimi/cariler/create') ? 'is-active' : '' }}"><span>Cari Ekle</span></a>
                    @endif
                </div>
            @endif

            @if($stokMenusuHerhangi)
                <button type="button" class="nav-item {{ $isMuhasebeStok ? 'is-active' : '' }}" x-on:click="muhasebeStokOpen = !muhasebeStokOpen" :aria-expanded="muhasebeStokOpen">
                    <span>Stok</span><svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                </button>
                <div x-show="muhasebeStokOpen" x-collapse class="nav-group">
                    @if($stokListeMenusu)
                        <a href="{{ StokKartiKaynagi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/stok/stok-listesi*') ? 'is-active' : '' }}"><span>Stok Listesi</span></a>
                        <a href="{{ StokHareketleriSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/stok/stok-hareketleri') ? 'is-active' : '' }}"><span>Stok Hareketleri</span></a>
                        <a href="{{ KritikStoklarSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/stok/kritik-stoklar') ? 'is-active' : '' }}"><span>Kritik Stoklar</span></a>
                        <a href="{{ StokKategoriKaynagi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/stok/stok-kategorileri') ? 'is-active' : '' }}"><span>Kategoriler</span></a>
                    @endif
                    @if($muhasebeYetki(MuhasebeYetkiSablonlari::STOK_OLUSTUR))
                        <a href="{{ StokKartiKaynagi::getUrl('create') }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/stok/stok-listesi/create') ? 'is-active' : '' }}"><span>Stok Ekle</span></a>
                    @endif
                </div>
            @endif

            @if($faturaMenusuHerhangi)
                <button type="button" class="nav-item {{ ($isFatura || $isMuhasebeEntegrasyon) ? 'is-active' : '' }}" x-on:click="muhasebeFaturalarOpen = !muhasebeFaturalarOpen" :aria-expanded="muhasebeFaturalarOpen">
                    <span>Faturalar</span><svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                </button>
                <div x-show="muhasebeFaturalarOpen" x-collapse class="nav-group">
                    <a href="{{ TumFaturalarSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/faturalar/tum-faturalar') ? 'is-active' : '' }}"><span>Tüm faturalar</span></a>
                    <a href="{{ GelenFatura::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/faturalar/gelen-faturalar') ? 'is-active' : '' }}"><span>Gelen faturalar</span></a>
                    <a href="{{ GidenFatura::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/faturalar/giden-faturalar') ? 'is-active' : '' }}"><span>Giden faturalar</span></a>
                    <a href="{{ BekleyenFatura::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/faturalar/bekleyen-faturalar') ? 'is-active' : '' }}"><span>Bekleyen faturalar</span></a>
                    <a href="{{ IptalFatura::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/faturalar/iptal-faturalar') ? 'is-active' : '' }}"><span>İptal faturalar</span></a>
                    <a href="{{ GidenIadeFaturasiSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/faturalar/giden-iade-faturalari') ? 'is-active' : '' }}"><span>Giden iade faturaları</span></a>
                    <a href="{{ GelenIadeFaturasiSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/faturalar/gelen-iade-faturalari') ? 'is-active' : '' }}"><span>Gelen iade faturaları</span></a>
                    <a href="{{ ProformaFaturaSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/faturalar/proforma-fatura') ? 'is-active' : '' }}"><span>Proforma</span></a>
                    <a href="{{ GiderFaturasiSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/faturalar/gider-faturasi') ? 'is-active' : '' }}"><span>Gider faturası</span></a>
                    @if($muhasebeYetki(\App\Support\MuhasebeYetkiSablonlari::FATURA_OLUSTUR))
                        <a href="{{ FaturaKaynagi::getUrl('create') }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/fatura-kaynagis/create') ? 'is-active' : '' }}"><span>Yeni fatura</span></a>
                    @endif
                    @if($muhasebeYetki(\App\Support\MuhasebeYetkiSablonlari::FATURA_GORUNTULE))
                        <a href="{{ NetteFaturaEntegrasyonSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/entegrasyonlar/nette-fatura') ? 'is-active' : '' }}"><span>NetteFatura Entegrasyonu</span></a>
                    @endif
                </div>
            @endif

            @if($muhasebeYetki(MuhasebeYetkiSablonlari::FINANS_GORUNTULE) || $posMenusuHerhangi)
                <button type="button" class="nav-item {{ $isFinans ? 'is-active' : '' }}" x-on:click="finansOpen = !finansOpen" :aria-expanded="finansOpen">
                    <span>Finans</span><svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                </button>
                <div x-show="finansOpen" x-collapse class="nav-group">
                    @if($muhasebeYetki(MuhasebeYetkiSablonlari::FINANS_GORUNTULE))
                        <a href="{{ FinansDashboardSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/finans/finans-panel') ? 'is-active' : '' }}"><span>Finans paneli</span></a>
                        <a href="{{ FinansHareketleriListesiSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/finans/finans-hareketleri') ? 'is-active' : '' }}"><span>Finans hareketleri</span></a>
                        <a href="{{ KasaHesabiKaynagi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/finans/kasalar') || request()->is($adminPrefix.'/muhasebe/finans/kasa-hesaplari*') ? 'is-active' : '' }}"><span>Kasalar</span></a>
                        <a href="{{ BankaHesabiKaynagi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/finans/bankalar') || request()->is($adminPrefix.'/muhasebe/finans/banka-hesaplari*') ? 'is-active' : '' }}"><span>Bankalar</span></a>
                    @endif
                    @if($posListeMenusu)
<a href="{{ PosHesabiKaynagi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/finans/poslar*') ? 'is-active' : '' }}"><span>POS'lar</span></a>
                    @endif
                    @if($muhasebeYetki(MuhasebeYetkiSablonlari::FINANS_GORUNTULE))
                        <a href="{{ VadeTakipSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/finans/vade-takibi') ? 'is-active' : '' }}"><span>Veresiye / Taksit Takibi</span></a>
                        <a href="{{ CekYonetimiSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/finans/cek') ? 'is-active' : '' }}"><span>Çek</span></a>
                        <a href="{{ SenetYonetimiSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/finans/senet') ? 'is-active' : '' }}"><span>Senet</span></a>
                    @endif
                </div>
            @endif

            @if($muhasebeYetki(MuhasebeYetkiSablonlari::RAPOR_GORUNTULE))
                <button type="button" class="nav-item {{ $isMuhasebeRapor ? 'is-active' : '' }}" x-on:click="muhasebeRaporOpen = !muhasebeRaporOpen" :aria-expanded="muhasebeRaporOpen">
                    <span>Raporlar</span><svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                </button>
                <div x-show="muhasebeRaporOpen" x-collapse class="nav-group">
                    <a href="{{ GelirGiderRaporuSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/raporlar/gelir-gider') ? 'is-active' : '' }}"><span>Gelir / gider</span></a>
                </div>
            @endif

            @if($muhasebeYetki(MuhasebeYetkiSablonlari::TANIM_GORUNTULE))
                <button type="button" class="nav-item {{ $isMuhasebeTanim ? 'is-active' : '' }}" x-on:click="muhasebeTanimOpen = !muhasebeTanimOpen" :aria-expanded="muhasebeTanimOpen">
                    <span>Tanımlar</span><svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                </button>
                <div x-show="muhasebeTanimOpen" x-collapse class="nav-group">
                    <a href="{{ CariGrubuTanimKaynagi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/tanimlar/cari-gruplari*') ? 'is-active' : '' }}"><span>Cari grupları</span></a>
                    <a href="{{ BirimTanimKaynagi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/tanimlar/birimler*') ? 'is-active' : '' }}"><span>Birimler</span></a>
                    <a href="{{ VergiOraniTanimKaynagi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/tanimlar/vergi-oranlari*') ? 'is-active' : '' }}"><span>Vergi oranları</span></a>
                    <a href="{{ OdemeYontemiTanimKaynagi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/tanimlar/odeme-yontemleri*') ? 'is-active' : '' }}"><span>Ödeme yöntemleri</span></a>
                    <a href="{{ ParaBirimiTanimKaynagi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/tanimlar/para-birimleri*') ? 'is-active' : '' }}"><span>Para birimleri</span></a>
                    <a href="{{ DovizKuruTanimKaynagi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/tanimlar/doviz-kurlari*') ? 'is-active' : '' }}"><span>Döviz kurları</span></a>
                    <a href="{{ StokTuruTanimlariSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/tanimlar/stok-turleri') ? 'is-active' : '' }}"><span>Stok türleri</span></a>
                    <a href="{{ MuhasebeMarkaUreticiTanimKaynagi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/tanimlar/marka-ureticileri*') ? 'is-active' : '' }}"><span>Marka Üreticileri</span></a>
                    <a href="{{ MuhasebeMarkaTanimKaynagi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/tanimlar/markalar*') ? 'is-active' : '' }}"><span>Ürün Markaları</span></a>
                    <a href="{{ MuhasebeStokModeliTanimKaynagi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/tanimlar/stok-modelleri*') ? 'is-active' : '' }}"><span>Ürün Modelleri</span></a>
                    <a href="{{ MuhasebeTasarimTanimKaynagi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/tanimlar/tasarimlar*') ? 'is-active' : '' }}"><span>Tasarımlar</span></a>
                    <a href="{{ MuhasebeMalzemeTuruTanimKaynagi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/tanimlar/malzeme-turleri*') ? 'is-active' : '' }}"><span>Malzeme türleri</span></a>
                    <a href="{{ MuhasebeLogoTuruTanimKaynagi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/tanimlar/logo-turleri*') ? 'is-active' : '' }}"><span>Logo türleri</span></a>
                    <a href="{{ MuhasebeVaryantTanimKaynagi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/tanimlar/varyantlar*') ? 'is-active' : '' }}"><span>Varyantlar</span></a>
                </div>
            @endif
        </div>
        @endif

        @if($pasifDepo = $pasifModul('depo'))
        <div class="section-gap" aria-hidden="true"></div>
        <a href="{{ \App\Filament\Pages\ModulAktifDegilSayfasi::getUrl(['modulKodu' => $pasifDepo->kod]) }}" class="nav-item nav-item--locked" title="{{ $pasifDepo->ad }} için erişim kapalı">
            <span class="nav-item-start"><x-filament::icon :icon="$pasifModulIkonu('depo')" class="nav-item-icon text-amber-500" /><span>{{ $pasifDepo->ad }}</span></span>
            <x-filament::icon icon="heroicon-m-lock-closed" class="nav-item-lock text-amber-500" aria-label="Erişim kapalı" />
        </a>
        @elseif($depoMenusuHerhangi)
        <div class="section-gap" aria-hidden="true"></div>

        {{-- Depo Yönetimi --}}
        <button
            type="button"
            class="nav-item {{ $isDepoYonetimi ? 'is-active' : '' }}"
            x-on:click="depoYonetimiOpen = !depoYonetimiOpen"
            :aria-expanded="depoYonetimiOpen"
        >
            <span class="nav-item-start">
                <x-filament::icon icon="heroicon-o-building-storefront" class="nav-item-icon" />
                <span>Depo Yönetimi</span>
            </span>
            <svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
        </button>
        <div x-show="depoYonetimiOpen" x-collapse class="nav-group">
            @if($muhasebeYetki(MuhasebeYetkiSablonlari::DEPO_GORUNTULE))
                <a x-on:click="depoYonetimiLinkClick()" href="{{ DepoTanimKaynagi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/tanimlar/depolar*') ? 'is-active' : '' }}"><span>Depolar</span></a>
                <a x-on:click="depoYonetimiLinkClick()" href="{{ StokDepoListesiSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/stok/depo-stoklari') ? 'is-active' : '' }}"><span>Depo Stokları</span></a>
                <a x-on:click="depoYonetimiLinkClick()" href="{{ StokDepoTransferGecmisiSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/stok/depo-transfer-gecmisi') ? 'is-active' : '' }}"><span>Transfer Geçmişi</span></a>
                <a x-on:click="depoYonetimiLinkClick()" href="{{ StokDepoSayimGecmisiSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/stok/depo-sayim-gecmisi') ? 'is-active' : '' }}"><span>Sayım Geçmişi</span></a>
            @endif
            @if($muhasebeYetki(MuhasebeYetkiSablonlari::DEPO_GUNCELLE))
                <a x-on:click="depoYonetimiLinkClick()" href="{{ StokDepoTransferSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/stok/depolar-arasi-transfer') ? 'is-active' : '' }}"><span>Depolar Arası Transfer</span></a>
                <a x-on:click="depoYonetimiLinkClick()" href="{{ StokDepoSayimSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/stok/depo-stok-sayimi') ? 'is-active' : '' }}"><span>Depo Sayımı</span></a>
            @endif
            @if($muhasebeYetki(MuhasebeYetkiSablonlari::DEPO_OLUSTUR))
                <a x-on:click="depoYonetimiLinkClick()" href="{{ DepoTanimKaynagi::getUrl('create') }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/tanimlar/depolar/create') ? 'is-active' : '' }}"><span>Depo Ekle</span></a>
            @endif
        </div>
        @endif

        @if($pasifUrun = $pasifModul('urunler'))
        <div class="section-gap" aria-hidden="true"></div>
        <a href="{{ \App\Filament\Pages\ModulAktifDegilSayfasi::getUrl(['modulKodu' => $pasifUrun->kod]) }}" class="nav-item nav-item--locked" title="{{ $pasifUrun->ad }} için erişim kapalı">
            <span class="nav-item-start"><x-filament::icon :icon="$pasifModulIkonu('urunler')" class="nav-item-icon text-amber-500" /><span>Ürün Yönetimi</span></span>
            <x-filament::icon icon="heroicon-m-lock-closed" class="nav-item-lock text-amber-500" aria-label="Erişim kapalı" />
        </a>
        @elseif($urunYonetimiGorunur)
        <div class="section-gap" aria-hidden="true"></div>

        {{-- Ürün Yönetimi --}}
        <button
            type="button"
            class="nav-item {{ $isUrunYonetimi ? 'is-active' : '' }}"
            x-on:click="urunYonetimiOpen = !urunYonetimiOpen"
            :aria-expanded="urunYonetimiOpen"
        >
            <span class="nav-item-start">
                <x-filament::icon icon="heroicon-o-cube" class="nav-item-icon" />
                <span>Ürün Yönetimi</span>
            </span>
            <svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
        </button>
        <div x-show="urunYonetimiOpen" x-collapse class="nav-group">
            @if($can('web', 'urun.goruntule'))
                <a x-on:click="urunYonetimiLinkClick()" href="{{ \App\Filament\Clusters\Web\Resources\UrunKaynagi::getUrl('index') }}" class="nav-item {{ request()->is($adminPrefix.'/web/urunler/urun-listesi*') ? 'is-active' : '' }}"><span>Ürünler</span></a>
            @endif
            @if($can('web', 'urun_kategori.goruntule'))
                <a x-on:click="urunYonetimiLinkClick()" href="{{ \App\Filament\Clusters\Web\Resources\UrunKategoriKaynagi::getUrl('index') }}" class="nav-item {{ request()->is($adminPrefix.'/web/urunler/urun-kategorileri*') ? 'is-active' : '' }}"><span>Kategoriler</span></a>
            @endif
            @if($eTicaretVaryasyonGorunur)
                <a x-on:click="urunYonetimiLinkClick()" href="{{ \App\Filament\Clusters\ETicaret\Pages\VaryasyonYonetimiSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/e-ticaret/varyasyon-yonetimi') ? 'is-active' : '' }}"><span>Varyasyonlar</span></a>
            @endif
        </div>
        @endif

        @if($pasifMasraf = $pasifModul('masraf_takip'))
        <div class="section-gap" aria-hidden="true"></div>
        <a href="{{ \App\Filament\Pages\ModulAktifDegilSayfasi::getUrl(['modulKodu' => $pasifMasraf->kod]) }}" class="nav-item nav-item--locked" title="{{ $pasifMasraf->ad }} için erişim kapalı">
            <span class="nav-item-start"><x-filament::icon :icon="$pasifModulIkonu('masraf_takip')" class="nav-item-icon text-amber-500" /><span>{{ $pasifMasraf->ad }}</span></span>
            <x-filament::icon icon="heroicon-m-lock-closed" class="nav-item-lock text-amber-500" aria-label="Erişim kapalı" />
        </a>
        @elseif($masrafTakipBolumuGorunur)
        <div class="section-gap" aria-hidden="true"></div>

        {{-- Masraf Takibi --}}
        <button
            type="button"
            class="nav-item {{ $isMasrafTakip ? 'is-active' : '' }}"
            x-on:click="masrafTakipOpen = !masrafTakipOpen"
            :aria-expanded="masrafTakipOpen"
        >
            <span class="nav-item-start">
                <x-filament::icon icon="heroicon-o-receipt-percent" class="nav-item-icon" />
                <span>Masraf Takibi</span>
            </span>
            <svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
        </button>
        <div x-show="masrafTakipOpen" x-collapse class="nav-group">
            <a href="{{ MasrafTakibiSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/masraf-takip/masraflar*') ? 'is-active' : '' }}"><span>Masraflar</span></a>
            <a href="{{ MasrafRaporlariSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/masraf-takip/raporlar*') ? 'is-active' : '' }}"><span>Raporlar</span></a>
            <a href="{{ MasrafKategorileriSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/masraf-takip/tanimlar*') ? 'is-active' : '' }}"><span>Masraf Tanımları</span></a>
            <a href="{{ DuzenliFaturaTanimlariSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/masraf-takip/tanimlar/duzenli-faturalar*') ? 'is-active' : '' }}"><span>Düzenli Faturalar</span></a>
            <a href="{{ AraclarSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/masraf-takip/tanimlar/araclar*') ? 'is-active' : '' }}"><span>Araçlar</span></a>
            <a href="{{ MasrafButceleriSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/masraf-takip/tanimlar/butceler*') ? 'is-active' : '' }}"><span>Masraf Bütçeleri</span></a>
        </div>
        @endif

        @if($pasifProje = $pasifModul('proje_yonetimi'))
        <div class="section-gap" aria-hidden="true"></div>
        <a href="{{ \App\Filament\Pages\ModulAktifDegilSayfasi::getUrl(['modulKodu' => $pasifProje->kod]) }}" class="nav-item nav-item--locked" title="{{ $pasifProje->ad }} için erişim kapalı">
            <span class="nav-item-start"><x-filament::icon :icon="$pasifModulIkonu('proje_yonetimi')" class="nav-item-icon text-amber-500" /><span>{{ $pasifProje->ad }}</span></span>
            <x-filament::icon icon="heroicon-m-lock-closed" class="nav-item-lock text-amber-500" aria-label="Erişim kapalı" />
        </a>
        @elseif($projeYonetimiBolumuGorunur)
        <div class="section-gap" aria-hidden="true"></div>

        <button
            type="button"
            class="nav-item {{ request()->is($adminPrefix.'/proje-yonetimi*') ? 'is-active' : '' }}"
            x-on:click="projeYonetimiOpen = !projeYonetimiOpen"
            :aria-expanded="projeYonetimiOpen"
        >
            <span class="nav-item-start">
                <x-filament::icon icon="heroicon-o-building-office-2" class="nav-item-icon" />
                <span>Proje Yönetimi</span>
            </span>
            <svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
        </button>
        <div x-show="projeYonetimiOpen" x-collapse class="nav-group">
            <a href="{{ IsletmeProjeleriSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/proje-yonetimi/projeler') ? 'is-active' : '' }}"><span>Projeler</span></a>
            <a href="{{ ProjeRaporlariSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/proje-yonetimi/raporlar') ? 'is-active' : '' }}"><span>Raporlar</span></a>
        </div>
        @endif

        @if($pasifTeklif = $pasifModul('teklif_yonetimi'))
        <div class="section-gap" aria-hidden="true"></div>
        <a href="{{ \App\Filament\Pages\ModulAktifDegilSayfasi::getUrl(['modulKodu' => $pasifTeklif->kod]) }}" class="nav-item nav-item--locked" title="{{ $pasifTeklif->ad }} için erişim kapalı">
            <span class="nav-item-start"><x-filament::icon :icon="$pasifModulIkonu('teklif_yonetimi')" class="nav-item-icon text-amber-500" /><span>{{ $pasifTeklif->ad }}</span></span>
            <x-filament::icon icon="heroicon-m-lock-closed" class="nav-item-lock text-amber-500" aria-label="Erişim kapalı" />
        </a>
        @elseif($teklifYonetimiMenusuHerhangi)
        <div class="section-gap" aria-hidden="true"></div>

        <button type="button" class="nav-item {{ $isTeklifYonetimi ? 'is-active' : '' }}" x-on:click="teklifYonetimiOpen = !teklifYonetimiOpen" :aria-expanded="teklifYonetimiOpen">
            <span class="nav-item-start">
                <x-filament::icon icon="heroicon-o-document-currency-dollar" class="nav-item-icon" />
                <span>Teklif Yönetimi</span>
            </span>
            <svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
        </button>
        <div x-show="teklifYonetimiOpen" x-collapse class="nav-group">
            @if($teklifListeMenusu)
                <a href="{{ TeklifKaynagi::getUrl('index') }}" class="nav-item {{ request()->is($adminPrefix.'/teklif-yonetimi/teklifler*') ? 'is-active' : '' }}"><span>Teklif listesi</span></a>
            @endif
            @if($can('teklif_yonetimi', TeklifYetkiSablonlari::OLUSTUR))
                <a href="{{ TeklifKaynagi::getUrl('create') }}" class="nav-item {{ request()->is($adminPrefix.'/teklif-yonetimi/teklifler/create') ? 'is-active' : '' }}"><span>Yeni teklif</span></a>
            @endif
            @if($teklifListeMenusu)
                <a href="{{ TeklifSablonKaynagi::getUrl('index') }}" class="nav-item {{ request()->is($adminPrefix.'/teklif-yonetimi/sablonlar*') ? 'is-active' : '' }}"><span>Şablonlar</span></a>
            @endif
        </div>
        @endif

        @if($pasifPersonel = $pasifModul('personel_takip'))
        <div class="section-gap" aria-hidden="true"></div>
        <a href="{{ \App\Filament\Pages\ModulAktifDegilSayfasi::getUrl(['modulKodu' => $pasifPersonel->kod]) }}" class="nav-item nav-item--locked" title="{{ $pasifPersonel->ad }} için erişim kapalı">
            <span class="nav-item-start"><x-filament::icon :icon="$pasifModulIkonu('personel_takip')" class="nav-item-icon text-amber-500" /><span>{{ $pasifPersonel->ad }}</span></span>
            <x-filament::icon icon="heroicon-m-lock-closed" class="nav-item-lock text-amber-500" aria-label="Erişim kapalı" />
        </a>
        @elseif($personelBolumuGorunur)
        <div class="section-gap" aria-hidden="true"></div>

        {{-- Personel Takip --}}
        <button type="button" class="nav-item {{ $isPersonelTakip ? 'is-active' : '' }}" x-on:click="personelTakipOpen = !personelTakipOpen" :aria-expanded="personelTakipOpen">
            <span class="nav-item-start">
                <x-filament::icon icon="heroicon-o-user-group" class="nav-item-icon" />
                <span>Personel Takip</span>
            </span>
            <svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
        </button>
        <div x-show="personelTakipOpen" x-collapse class="nav-group">
            @if($personelListeGorunur || $personelRaporGorunur)
                <a href="{{ PersonelTakipOzetSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/personel-takip/ozet') ? 'is-active' : '' }}"><span>Özet</span></a>
            @endif
            @if($personelListeGorunur)
                <a href="{{ PersonelKaynagi::getUrl('index') }}" class="nav-item {{ request()->is($adminPrefix.'/personel-takip/personeller*') ? 'is-active' : '' }}"><span>Personeller</span></a>
            @endif
            @if($personelYetki(PersonelTakipYetkiSablonlari::OLUSTUR))
                <a href="{{ PersonelKaynagi::getUrl('create') }}" class="nav-item {{ request()->is($adminPrefix.'/personel-takip/personeller/create') ? 'is-active' : '' }}"><span>Yeni personel</span></a>
            @endif
            @if($personelVardiyaGorunur)
                <a href="{{ PersonelVardiyaKaynagi::getUrl('index') }}" class="nav-item {{ request()->is($adminPrefix.'/personel-takip/vardiyalar*') ? 'is-active' : '' }}"><span>Vardiya Planı</span></a>
            @endif
            @if($personelGirisCikisGorunur)
                <a href="{{ PersonelGirisCikisKaynagi::getUrl('index') }}" class="nav-item {{ request()->is($adminPrefix.'/personel-takip/giris-cikis*') ? 'is-active' : '' }}"><span>Giriş Çıkış</span></a>
                <a href="{{ PersonelPinTerminalSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/personel-takip/terminal/pin-giris-cikis') ? 'is-active' : '' }}"><span>PIN Terminali</span></a>
            @endif
            @if($personelIzinGorunur)
                <a href="{{ PersonelIzinKaynagi::getUrl('index') }}" class="nav-item {{ request()->is($adminPrefix.'/personel-takip/izinler*') ? 'is-active' : '' }}"><span>İzinler</span></a>
            @endif
            @if($personelAvansGorunur)
                <a href="{{ PersonelAvansKaynagi::getUrl('index') }}" class="nav-item {{ request()->is($adminPrefix.'/personel-takip/avanslar*') ? 'is-active' : '' }}"><span>Avanslar</span></a>
            @endif
            @if($personelMaasGorunur)
                <a href="{{ PersonelMaasDonemiKaynagi::getUrl('index') }}" class="nav-item {{ request()->is($adminPrefix.'/personel-takip/maas-donemleri*') ? 'is-active' : '' }}"><span>Maaş / Hakediş</span></a>
            @endif
            @if($personelRaporGorunur)
                <a href="{{ PersonelRaporlariSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/personel-takip/raporlar/personel-ozeti') ? 'is-active' : '' }}"><span>Raporlar</span></a>
            @endif
            @if($personelTanimGorunur)
                <a href="{{ PersonelAyarlariSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/personel-takip/ayarlar') ? 'is-active' : '' }}"><span>Ayarlar</span></a>
            @endif
            @if($personelTanimGorunur)
                <button type="button" class="nav-item {{ $isPersonelTanimlar ? 'is-active' : '' }}" x-on:click="personelTanimlarOpen = !personelTanimlarOpen" :aria-expanded="personelTanimlarOpen">
                    <span>Tanımlar</span><svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                </button>
                <div x-show="personelTanimlarOpen" x-collapse class="nav-group">
                    <a href="{{ SubeKaynagi::getUrl('index') }}" class="nav-item {{ request()->is($adminPrefix.'/personel-takip/tanimlar/subeler*') ? 'is-active' : '' }}"><span>Şubeler</span></a>
                    <a href="{{ PersonelDepartmanKaynagi::getUrl('index') }}" class="nav-item {{ request()->is($adminPrefix.'/personel-takip/tanimlar/departmanlar*') ? 'is-active' : '' }}"><span>Departmanlar</span></a>
                    <a href="{{ PersonelGorevKaynagi::getUrl('index') }}" class="nav-item {{ request()->is($adminPrefix.'/personel-takip/tanimlar/gorevler*') ? 'is-active' : '' }}"><span>Görevler</span></a>
                    <a href="{{ PersonelVardiyaSablonuKaynagi::getUrl('index') }}" class="nav-item {{ request()->is($adminPrefix.'/personel-takip/tanimlar/vardiya-sablonlari*') ? 'is-active' : '' }}"><span>Vardiya şablonları</span></a>
                </div>
            @endif
        </div>
        @endif

        @if($pasifRestoran = $pasifModul('restoran'))
        <div class="section-gap" aria-hidden="true"></div>
        <a href="{{ \App\Filament\Pages\ModulAktifDegilSayfasi::getUrl(['modulKodu' => $pasifRestoran->kod]) }}" class="nav-item nav-item--locked" title="{{ $pasifRestoran->ad }} için erişim kapalı">
            <span class="nav-item-start"><x-filament::icon :icon="$pasifModulIkonu('restoran')" class="nav-item-icon text-amber-500" /><span>{{ $pasifRestoran->ad }}</span></span>
            <x-filament::icon icon="heroicon-m-lock-closed" class="nav-item-lock text-amber-500" aria-label="Erişim kapalı" />
        </a>
        @elseif($restoranBolumuGorunur)
        <div class="section-gap" aria-hidden="true"></div>

        {{-- Restoran --}}
        <button type="button" class="nav-item {{ $isRestoran ? 'is-active' : '' }}" x-on:click="restoranOpen = !restoranOpen" :aria-expanded="restoranOpen">
            <span class="nav-item-start">
                <x-filament::icon icon="heroicon-o-building-storefront" class="nav-item-icon" />
                <span>Restoran</span>
            </span>
            <svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
        </button>
        <div x-show="restoranOpen" x-collapse class="nav-group">
            @if($restoranMasaGorunur)
                <a href="{{ RestoranMasaEkraniSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/restoran/masa-ekrani') ? 'is-active' : '' }}"><span>Masa Ekranı</span></a>
                <a href="{{ RestoranMasaKaynagi::getUrl('index') }}" class="nav-item {{ request()->is($adminPrefix.'/restoran/masalar*') ? 'is-active' : '' }}"><span>Masalar</span></a>
            @endif
            @if($restoranAdisyonGorunur)
                <a href="{{ RestoranAdisyonKaynagi::getUrl('index') }}" class="nav-item {{ request()->is($adminPrefix.'/restoran/adisyonlar*') ? 'is-active' : '' }}"><span>Adisyonlar</span></a>
                <a href="{{ RestoranAdisyonKalemiKaynagi::getUrl('index') }}" class="nav-item {{ request()->is($adminPrefix.'/restoran/adisyon-kalemleri*') ? 'is-active' : '' }}"><span>Adisyon Kalemleri</span></a>
            @endif
            @if($restoranMutfakGorunur)
                <a href="{{ RestoranMutfakEkraniSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/restoran/mutfak') ? 'is-active' : '' }}"><span>Mutfak</span></a>
            @endif
            @if($restoranPaketServisGorunur)
                <a href="{{ RestoranPaketServisSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/restoran/paket-servis') ? 'is-active' : '' }}"><span>Paket Servis</span></a>
            @endif
            @if($restoranRaporGorunur)
                <a href="{{ RestoranRaporlariSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/restoran/raporlar/genel') ? 'is-active' : '' }}"><span>Raporlar</span></a>
            @endif
            @if($restoranQrMenuGorunur)
                <button type="button" class="nav-item {{ $isRestoranQrMenu ? 'is-active' : '' }}" x-on:click="restoranQrMenuOpen = !restoranQrMenuOpen" :aria-expanded="restoranQrMenuOpen">
                    <span>QR Menü</span><svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                </button>
                <div x-show="restoranQrMenuOpen" x-collapse class="nav-group">
                    <a href="{{ RestoranMenuKategoriKaynagi::getUrl('index') }}" class="nav-item {{ request()->is($adminPrefix.'/restoran/qr-menu/kategoriler*') ? 'is-active' : '' }}"><span>Kategoriler</span></a>
                    <a href="{{ RestoranMenuUrunKaynagi::getUrl('index') }}" class="nav-item {{ request()->is($adminPrefix.'/restoran/qr-menu/urunler*') ? 'is-active' : '' }}"><span>Ürünler</span></a>
                </div>
            @endif
            @if($restoranMasaGorunur)
                <button type="button" class="nav-item {{ $isRestoranTanimlar ? 'is-active' : '' }}" x-on:click="restoranTanimlarOpen = !restoranTanimlarOpen" :aria-expanded="restoranTanimlarOpen">
                    <span>Tanımlar</span><svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                </button>
                <div x-show="restoranTanimlarOpen" x-collapse class="nav-group">
                    <a href="{{ RestoranSalonKaynagi::getUrl('index') }}" class="nav-item {{ request()->is($adminPrefix.'/restoran/tanimlar/salonlar*') ? 'is-active' : '' }}"><span>Salonlar</span></a>
                </div>
            @endif
        </div>
        @endif

        @if($pasifBarkod = $pasifModul('barkodlu_satis'))
        <div class="section-gap" aria-hidden="true"></div>
        <a href="{{ \App\Filament\Pages\ModulAktifDegilSayfasi::getUrl(['modulKodu' => $pasifBarkod->kod]) }}" class="nav-item nav-item--locked" title="{{ $pasifBarkod->ad }} için erişim kapalı">
            <span class="nav-item-start"><x-filament::icon :icon="$pasifModulIkonu('barkodlu_satis')" class="nav-item-icon text-amber-500" /><span>{{ $pasifBarkod->ad }}</span></span>
            <x-filament::icon icon="heroicon-m-lock-closed" class="nav-item-lock text-amber-500" aria-label="Erişim kapalı" />
        </a>
        @elseif($barkodluSatisMenusuHerhangi)
        <div class="section-gap" aria-hidden="true"></div>

        <button type="button" class="nav-item {{ $isBarkodluSatis ? 'is-active' : '' }}" x-on:click="barkodluSatisOpen = !barkodluSatisOpen" :aria-expanded="barkodluSatisOpen">
            <span class="nav-item-start">
                <x-filament::icon icon="heroicon-o-qr-code" class="nav-item-icon" />
                <span>Barkodlu Satış</span>
            </span>
            <svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
        </button>
        <div x-show="barkodluSatisOpen" x-collapse class="nav-group">
            @if($barkodluSatisYetki(MuhasebeYetkiSablonlari::BARKODLU_SATIS_GORUNTULE) || $barkodluSatisYetki(MuhasebeYetkiSablonlari::BARKODLU_SATIS_OLUSTUR) || $barkodluSatisYetki(MuhasebeYetkiSablonlari::BARKODLU_SATIS_GUNCELLE))
                <a href="{{ HizliSatisSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/satis/hizli-satis') ? 'is-active' : '' }}"><span>Hızlı Satış</span></a>
                <a href="{{ BarkodluSatisSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/satis/barkodlu-satis') ? 'is-active' : '' }}"><span>Barkodlu Satis Ekrani</span></a>
            @endif
            @if($barkodluSatisYetki(MuhasebeYetkiSablonlari::BARKODLU_SATIS_GORUNTULE) || $barkodluSatisYetki(MuhasebeYetkiSablonlari::BARKODLU_SATIS_GUNCELLE) || $barkodluSatisYetki(MuhasebeYetkiSablonlari::BARKODLU_SATIS_IPTAL) || $barkodluSatisYetki(MuhasebeYetkiSablonlari::BARKODLU_SATIS_IADE))
                <a href="{{ BarkodluSatisGecmisiSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/satis/barkodlu-satis-gecmisi') ? 'is-active' : '' }}"><span>Satis Gecmisi</span></a>
            @endif
            @if($barkodluSatisYetki(MuhasebeYetkiSablonlari::BARKODLU_SATIS_GORUNTULE) || $barkodluSatisYetki(MuhasebeYetkiSablonlari::BARKODLU_SATIS_IADE))
                <a href="{{ BarkodluSatisIadeGecmisiSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/satis/barkodlu-satis-iade-gecmisi') ? 'is-active' : '' }}"><span>Iade Gecmisi</span></a>
            @endif
            @if($barkodluSatisYetki(MuhasebeYetkiSablonlari::BARKODLU_SATIS_GORUNTULE) || $barkodluSatisYetki(MuhasebeYetkiSablonlari::BARKODLU_SATIS_GUNCELLE))
                <a href="{{ BarkodluSatisMuhasebeMutabakatSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/satis/barkodlu-satis-muhasebe-mutabakat') ? 'is-active' : '' }}"><span>Muhasebe Mutabakat</span></a>
            @endif
            @if($barkodluSatisYetki(MuhasebeYetkiSablonlari::BARKODLU_SATIS_ETIKET_YAZDIR) || $barkodluSatisYetki(MuhasebeYetkiSablonlari::BARKODLU_SATIS_GUNCELLE))
                <a href="{{ BarkodEtiketYazdirmaSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/satis/barkod-etiket-yazdirma') ? 'is-active' : '' }}"><span>Barkod Etiket Yazdirma</span></a>
                <a href="{{ BarkodluSatisBarkodListesiSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/satis/barkod-esleme-listesi') ? 'is-active' : '' }}"><span>Barkod Eslestirme Listesi</span></a>
            @endif
            @if($barkodluSatisYetki(MuhasebeYetkiSablonlari::BARKODLU_SATIS_AYAR_GUNCELLE) || $barkodluSatisYetki(MuhasebeYetkiSablonlari::BARKODLU_SATIS_AYAR_GORUNTULE))
                <button type="button" class="nav-item {{ $isBarkodluSatisAyar ? 'is-active' : '' }}" x-on:click="barkodluSatisAyarOpen = !barkodluSatisAyarOpen" :aria-expanded="barkodluSatisAyarOpen">
                    <span>Ayarlar</span><svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                </button>
                <div x-show="barkodluSatisAyarOpen" x-collapse class="nav-group">
                    <a href="{{ BarkodluSatisAyarlarSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/satis/barkodlu-satis-ayarlar') ? 'is-active' : '' }}"><span>Barkodlu Satis Ayarlari</span></a>
                    <a href="{{ SatisFisDuzenleSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/muhasebe/satis/barkodlu-satis-fis-sablonlari') ? 'is-active' : '' }}"><span>Satis Fisi Duzenle</span></a>
                </div>
            @endif
        </div>
        @endif

        @if($pasifTeknik = $pasifModul('teknik_servis'))
        <div class="section-gap" aria-hidden="true"></div>
        <a href="{{ \App\Filament\Pages\ModulAktifDegilSayfasi::getUrl(['modulKodu' => $pasifTeknik->kod]) }}" class="nav-item nav-item--locked" title="{{ $pasifTeknik->ad }} için erişim kapalı">
            <span class="nav-item-start"><x-filament::icon :icon="$pasifModulIkonu('teknik_servis')" class="nav-item-icon text-amber-500" /><span>{{ $pasifTeknik->ad }}</span></span>
            <x-filament::icon icon="heroicon-m-lock-closed" class="nav-item-lock text-amber-500" aria-label="Erişim kapalı" />
        </a>
        @elseif($bolum('teknik_servis'))
        <div class="section-gap" aria-hidden="true"></div>

        {{-- Teknik Servis --}}
        <button type="button" class="nav-item {{ $isTeknikServis ? 'is-active' : '' }}" x-on:click="teknikServisOpen = !teknikServisOpen" :aria-expanded="teknikServisOpen">
            <span class="nav-item-start">
                <x-filament::icon icon="heroicon-o-wrench-screwdriver" class="nav-item-icon" />
                <span>Teknik Servis</span>
            </span>
            <svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
        </button>
        <div x-show="teknikServisOpen" x-collapse class="nav-group">
            <a href="{{ TeknikServisDashboardSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/teknik-servis/ozet') ? 'is-active' : '' }}"><span>Teknik Servis Dashboard</span></a>

            <button type="button" class="nav-item {{ $isTsKayitOlustur ? 'is-active' : '' }}" x-on:click="tsKayitCreateOpen = !tsKayitCreateOpen" :aria-expanded="tsKayitCreateOpen">
                <span>Yeni Servis Kaydı</span><svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </button>
            <div x-show="tsKayitCreateOpen" x-collapse class="nav-group">
                <a href="{{ TeknikServisKaydiKaynagi::getUrl('create_arizali') }}" class="nav-item {{ request()->is($adminPrefix.'/teknik-servis/servis-kayitlari/olustur/arizali-cihaz') ? 'is-active' : '' }}"><span>Arızalı Cihaz Kaydı</span></a>
                <a href="{{ TeknikServisKaydiKaynagi::getUrl('create_dis_servis') }}" class="nav-item {{ request()->is($adminPrefix.'/teknik-servis/servis-kayitlari/olustur/dis-servis') ? 'is-active' : '' }}"><span>Dış Servis Kaydı</span></a>
                <a href="{{ TeknikServisKaydiKaynagi::getUrl('create_bakim') }}" class="nav-item {{ request()->is($adminPrefix.'/teknik-servis/servis-kayitlari/olustur/bakim') ? 'is-active' : '' }}"><span>Bakım Kaydı</span></a>
            </div>

            <button type="button" class="nav-item {{ $isTsKayitListe || $isTsKayitIndexOrKayit ? 'is-active' : '' }}" x-on:click="tsKayitListeOpen = !tsKayitListeOpen" :aria-expanded="tsKayitListeOpen">
                <span>Servis Kayıtları</span><svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </button>
            <div x-show="tsKayitListeOpen" x-collapse class="nav-group">
                <a href="{{ TeknikServisKaydiKaynagi::getUrl('index') }}" class="nav-item {{ $isTsKayitIndexOrKayit ? 'is-active' : '' }}"><span>Tüm Kayıtlar</span></a>
                <a href="{{ TeknikServisKaydiKaynagi::getUrl('acik') }}" class="nav-item {{ request()->is($adminPrefix.'/teknik-servis/servis-kayitlari/liste/acik') ? 'is-active' : '' }}"><span>Açık Servisler</span></a>
                <a href="{{ TeknikServisKaydiKaynagi::getUrl('tezgahta') }}" class="nav-item {{ request()->is($adminPrefix.'/teknik-servis/servis-kayitlari/liste/tezgahta') ? 'is-active' : '' }}"><span>Tezgahtakiler</span></a>
                <a href="{{ TeknikServisKaydiKaynagi::getUrl('parca_bekleyen') }}" class="nav-item {{ request()->is($adminPrefix.'/teknik-servis/servis-kayitlari/liste/parca-bekleyen') ? 'is-active' : '' }}"><span>Parça Bekleyen Servisler</span></a>
                <a href="{{ TeknikServisKaydiKaynagi::getUrl('garantiye_gonderilen') }}" class="nav-item {{ request()->is($adminPrefix.'/teknik-servis/servis-kayitlari/liste/garantiye-gonderilen') ? 'is-active' : '' }}"><span>Garantiye Gönderilen</span></a>
                <a href="{{ TeknikServisKaydiKaynagi::getUrl('fiyat_verilen') }}" class="nav-item {{ request()->is($adminPrefix.'/teknik-servis/servis-kayitlari/liste/fiyat-verilen') ? 'is-active' : '' }}"><span>Fiyat Verilen Servisler</span></a>
                <a href="{{ TeknikServisKaydiKaynagi::getUrl('teslim_bekleyen') }}" class="nav-item {{ request()->is($adminPrefix.'/teknik-servis/servis-kayitlari/liste/teslim-bekleyen') ? 'is-active' : '' }}"><span>Teslim Bekleyen Servisler</span></a>
                <a href="{{ TeknikServisKaydiKaynagi::getUrl('tamamlanan_dis_servis') }}" class="nav-item {{ request()->is($adminPrefix.'/teknik-servis/servis-kayitlari/liste/tamamlanan-dis-servis') ? 'is-active' : '' }}"><span>Tamamlanan Dış Servisler</span></a>
                <a href="{{ TeknikServisKaydiKaynagi::getUrl('teslim_edilen') }}" class="nav-item {{ request()->is($adminPrefix.'/teknik-servis/servis-kayitlari/liste/teslim-edilen') ? 'is-active' : '' }}"><span>Teslim Edilen Servisler</span></a>
                <a href="{{ TeknikServisKaydiKaynagi::getUrl('iptal') }}" class="nav-item {{ request()->is($adminPrefix.'/teknik-servis/servis-kayitlari/liste/iptal') ? 'is-active' : '' }}"><span>İptal Edilenler</span></a>
                <a href="{{ TeknikServisKaydiKaynagi::getUrl('iade') }}" class="nav-item {{ request()->is($adminPrefix.'/teknik-servis/servis-kayitlari/liste/iade') ? 'is-active' : '' }}"><span>İade Edilenler</span></a>
            </div>

            <a href="{{ TeknikServisKayitliCihaziKaynagi::getUrl('index') }}" class="nav-item {{ str_starts_with($currentPath, $adminPrefix.'/teknik-servis/kayitli-cihazlar') ? 'is-active' : '' }}"><span>Kayıtlı Cihazlar</span></a>

            <button type="button" class="nav-item {{ $isTsGarantiHatirlatma ? 'is-active' : '' }}" x-on:click="tsGarantiOpen = !tsGarantiOpen" :aria-expanded="tsGarantiOpen">
                <span>Garanti &amp; Hatırlatmalar</span><svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </button>
            <div x-show="tsGarantiOpen" x-collapse class="nav-group">
                <a href="{{ GarantiliCihazlarSayfasi::getUrl() }}" class="nav-item {{ str_starts_with($currentPath, $adminPrefix.'/teknik-servis/operasyon/garantili-cihazlar') ? 'is-active' : '' }}"><span>Garantili Cihazlar</span></a>
                <a href="{{ BakimHatirlatmalariSayfasi::getUrl() }}" class="nav-item {{ str_starts_with($currentPath, $adminPrefix.'/teknik-servis/operasyon/bakim-hatirlatmalari') ? 'is-active' : '' }}"><span>Bakım Hatırlatmaları</span></a>
            </div>

            <button type="button" class="nav-item {{ $isTsRaporlar ? 'is-active' : '' }}" x-on:click="tsRaporOpen = !tsRaporOpen" :aria-expanded="tsRaporOpen">
                <span>Raporlar</span><svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </button>
            <div x-show="tsRaporOpen" x-collapse class="nav-group">
                <a href="{{ TeknikServisKarlilikRaporuSayfasi::getUrl() }}" class="nav-item {{ str_starts_with($currentPath, $adminPrefix.'/teknik-servis/raporlar/karlilik') ? 'is-active' : '' }}"><span>Servis Karlılık</span></a>
                <a href="{{ TeknikServisPersonelPerformansRaporuSayfasi::getUrl() }}" class="nav-item {{ str_starts_with($currentPath, $adminPrefix.'/teknik-servis/raporlar/personel-performans') ? 'is-active' : '' }}"><span>Personel Performans</span></a>
                <a href="{{ TeknikServisDurumBazliRaporuSayfasi::getUrl() }}" class="nav-item {{ str_starts_with($currentPath, $adminPrefix.'/teknik-servis/raporlar/durum-bazli') ? 'is-active' : '' }}"><span>Durum Bazlı</span></a>
                <a href="{{ TeknikServisGarantiBakimRaporuSayfasi::getUrl() }}" class="nav-item {{ str_starts_with($currentPath, $adminPrefix.'/teknik-servis/raporlar/garanti-bakim') ? 'is-active' : '' }}"><span>Garanti / Bakım</span></a>
                <a href="{{ TeknikServisTahsilatServisRaporuSayfasi::getUrl() }}" class="nav-item {{ str_starts_with($currentPath, $adminPrefix.'/teknik-servis/raporlar/tahsilat-servis') ? 'is-active' : '' }}"><span>Tahsilat / Servis</span></a>
            </div>

            <button type="button" class="nav-item {{ $isTsAyarlarGrubu ? 'is-active' : '' }}" x-on:click="tsAyarlarOpen = !tsAyarlarOpen" :aria-expanded="tsAyarlarOpen">
                <span>Ayarlar</span><svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </button>
            <div x-show="tsAyarlarOpen" x-collapse class="nav-group">
                <a href="{{ TeknikServisDurumuTanimKaynagi::getUrl() }}" class="nav-item {{ str_starts_with($currentPath, $adminPrefix.'/teknik-servis/tanimlar/servis-durumlari') ? 'is-active' : '' }}"><span>Servis Durumları</span></a>
                <a href="{{ TeknikServisCihazKaynagi::getUrl() }}" class="nav-item {{ str_starts_with($currentPath, $adminPrefix.'/teknik-servis/tanimlar/cihazlar') ? 'is-active' : '' }}"><span>Cihazlar</span></a>
                <a href="{{ TeknikServisMarkaKaynagi::getUrl() }}" class="nav-item {{ str_starts_with($currentPath, $adminPrefix.'/teknik-servis/tanimlar/markalar') ? 'is-active' : '' }}"><span>Markalar</span></a>
                <a href="{{ TeknikServisAksesuarKaynagi::getUrl() }}" class="nav-item {{ str_starts_with($currentPath, $adminPrefix.'/teknik-servis/tanimlar/aksesuarlar') ? 'is-active' : '' }}"><span>Aksesuarlar</span></a>
                <a href="{{ TeknikServisArizaKaynagi::getUrl() }}" class="nav-item {{ str_starts_with($currentPath, $adminPrefix.'/teknik-servis/tanimlar/arizalar') ? 'is-active' : '' }}"><span>Arızalar</span></a>
                <a href="{{ TeknikServisGenelAyarlarSayfasi::getUrl() }}" class="nav-item {{ $isTsGenelAyarlarSayfa ? 'is-active' : '' }}"><span>Genel Ayarlar</span></a>
                <a href="{{ TelegramSayfasi::getUrl() }}" class="nav-item {{ $isTsTelegramSayfa ? 'is-active' : '' }}"><span>Telegram</span></a>

                <button type="button" class="nav-item {{ $isTsSablonlar ? 'is-active' : '' }}" x-on:click="tsSablonlarOpen = !tsSablonlarOpen" :aria-expanded="tsSablonlarOpen">
                    <span>Şablonlar</span><svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                </button>
                <div x-show="tsSablonlarOpen" x-collapse class="nav-group">
                    <a href="{{ ServisTalepFormuSablonuSayfasi::getUrl() }}" class="nav-item {{ str_starts_with($currentPath, $adminPrefix.'/teknik-servis/sablonlar/talep-formu') ? 'is-active' : '' }}"><span>Servis Talep Formu</span></a>
                    <a href="{{ ServisFormuSablonuSayfasi::getUrl() }}" class="nav-item {{ str_starts_with($currentPath, $adminPrefix.'/teknik-servis/sablonlar/servis-formu') ? 'is-active' : '' }}"><span>Teknik Servis Formu</span></a>
                    <a href="{{ ServisKabulFormuSablonuSayfasi::getUrl() }}" class="nav-item {{ str_starts_with($currentPath, $adminPrefix.'/teknik-servis/sablonlar/kabul-formu') ? 'is-active' : '' }}"><span>Servis Kabul Formu</span></a>
                    <a href="{{ ServisFisiSablonuSayfasi::getUrl() }}" class="nav-item {{ str_starts_with($currentPath, $adminPrefix.'/teknik-servis/sablonlar/servis-fisi') ? 'is-active' : '' }}"><span>Servis Fişi</span></a>
                    <a href="{{ TeslimEdildiBelgesiSablonuSayfasi::getUrl() }}" class="nav-item {{ str_starts_with($currentPath, $adminPrefix.'/teknik-servis/sablonlar/teslim-belgesi') ? 'is-active' : '' }}"><span>Teslim Edildi Belgesi</span></a>
                    <a href="{{ WhatsappSablonlariSayfasi::getUrl() }}" class="nav-item {{ str_starts_with($currentPath, $adminPrefix.'/teknik-servis/sablonlar/whatsapp-sablonlari') ? 'is-active' : '' }}"><span>Whatsapp Şablonları</span></a>
                </div>

                <a href="{{ TeknikServisIslemLoglariSayfasi::getUrl() }}" class="nav-item {{ str_starts_with($currentPath, $adminPrefix.'/teknik-servis/operasyon/islem-loglari') ? 'is-active' : '' }}"><span>İşlem Logları</span></a>
                <a href="{{ TeknikServisMesajGecmisiSayfasi::getUrl() }}" class="nav-item {{ str_starts_with($currentPath, $adminPrefix.'/teknik-servis/operasyon/mesaj-gecmisi') ? 'is-active' : '' }}"><span>Mesaj Geçmişi</span></a>
            </div>
        </div>
        @endif

        @if($pasifETicaret = $pasifModul('e_ticaret'))
        <div class="section-gap" aria-hidden="true"></div>
        <a href="{{ \App\Filament\Pages\ModulAktifDegilSayfasi::getUrl(['modulKodu' => $pasifETicaret->kod]) }}" class="nav-item nav-item--locked" title="{{ $pasifETicaret->ad }} için erişim kapalı">
            <span class="nav-item-start"><x-filament::icon :icon="$pasifModulIkonu('e_ticaret')" class="nav-item-icon text-amber-500" /><span>{{ $pasifETicaret->ad }}</span></span>
            <x-filament::icon icon="heroicon-m-lock-closed" class="nav-item-lock text-amber-500" aria-label="Erişim kapalı" />
        </a>
        @elseif($eTicaretBolumuGorunur)
        <div class="section-gap" aria-hidden="true"></div>

        {{-- E-Ticaret --}}
        <button type="button" class="nav-item {{ $isETicaret ? 'is-active' : '' }}" x-on:click="eTicaretOpen = !eTicaretOpen" :aria-expanded="eTicaretOpen">
            <span class="nav-item-start">
                <x-filament::icon icon="heroicon-o-shopping-cart" class="nav-item-icon" />
                <span>E-Ticaret</span>
            </span>
            <svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
        </button>
        <div x-show="eTicaretOpen" x-collapse class="nav-group">
            @if($eTicaretSiparisGorunur)
                <a href="{{ \App\Filament\Resources\SiparisKaynagi::getUrl('index') }}" class="nav-item {{ $isETicaretSiparis ? 'is-active' : '' }}"><span>Sipariş Yönetimi</span></a>
            @endif

            @if($eTicaretMesajGorunur || $eTicaretMusteriMesajGorunur || $eTicaretUrunMesajGorunur)
                <button type="button" class="nav-item {{ $isETicaretMesaj ? 'is-active' : '' }}" x-on:click="eTicaretMesajOpen = !eTicaretMesajOpen" :aria-expanded="eTicaretMesajOpen">
                    <span>Mesaj Yönetimi</span><svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                </button>
                <div x-show="eTicaretMesajOpen" x-collapse class="nav-group">
                    @if($eTicaretMusteriMesajGorunur)
                        <a href="{{ \App\Filament\Clusters\ETicaret\Pages\MusteriMesajlariSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/e-ticaret/mesaj-yonetimi/musteri-mesajlari') ? 'is-active' : '' }}"><span>Müşteri Mesajları</span></a>
                    @endif
                    @if($eTicaretUrunMesajGorunur)
                        <a href="{{ \App\Filament\Clusters\ETicaret\Pages\UrunMesajlariSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/e-ticaret/mesaj-yonetimi/urun-mesajlari') ? 'is-active' : '' }}"><span>Ürün Mesajları</span></a>
                    @endif
                </div>
            @endif

            @if($eTicaretBildirimGorunur)
                <button type="button" class="nav-item {{ $isETicaretBildirim ? 'is-active' : '' }}" x-on:click="eTicaretBildirimOpen = !eTicaretBildirimOpen" :aria-expanded="eTicaretBildirimOpen">
                    <span>Bildirim Yönetimi</span><svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                </button>
                <div x-show="eTicaretBildirimOpen" x-collapse class="nav-group">
                    <a href="{{ \App\Filament\Clusters\ETicaret\Pages\BildirimSablonlariSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/e-ticaret/bildirim-yonetimi/sablonlar') ? 'is-active' : '' }}"><span>Şablonlar</span></a>
                    <a href="{{ \App\Filament\Clusters\ETicaret\Pages\BildirimLoglariSayfasi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/e-ticaret/bildirim-yonetimi/loglar') ? 'is-active' : '' }}"><span>Loglar</span></a>
                </div>
            @endif

            @if($eTicaretKampanyaGorunur)
                <a href="{{ \App\Filament\Clusters\ETicaret\Pages\KampanyaYonetimiSayfasi::getUrl() }}" class="nav-item {{ $isETicaretKampanya ? 'is-active' : '' }}"><span>Kampanya Yönetimi</span></a>
            @endif
            @if($eTicaretPazaryeriGorunur)
                <a href="{{ \App\Filament\Clusters\ETicaret\Pages\PazaryeriEntegrasyonuSayfasi::getUrl() }}" class="nav-item {{ $isETicaretPazaryeri ? 'is-active' : '' }}"><span>Pazaryeri Entegrasyonu</span></a>
            @endif
            @if($eTicaretKargoGorunur)
                <a href="{{ \App\Filament\Clusters\ETicaret\Pages\KargoYonetimiSayfasi::getUrl() }}" class="nav-item {{ $isETicaretKargo ? 'is-active' : '' }}"><span>Kargo Yönetimi</span></a>
            @endif
            @if($eTicaretOdemeGorunur)
                <a href="{{ \App\Filament\Clusters\ETicaret\Pages\OdemeYonetimiSayfasi::getUrl() }}" class="nav-item {{ $isETicaretOdeme ? 'is-active' : '' }}"><span>Ödeme Yönetimi</span></a>
            @endif
        </div>
        @endif

        @if($pasifWeb = $pasifModul('web'))
        <div class="section-gap" aria-hidden="true"></div>
        <a href="{{ \App\Filament\Pages\ModulAktifDegilSayfasi::getUrl(['modulKodu' => $pasifWeb->kod]) }}" class="nav-item nav-item--locked" title="{{ $pasifWeb->ad }} için erişim kapalı">
            <span class="nav-item-start"><x-filament::icon :icon="$pasifModulIkonu('web')" class="nav-item-icon text-amber-500" /><span>{{ $pasifWeb->ad }}</span></span>
            <x-filament::icon icon="heroicon-m-lock-closed" class="nav-item-lock text-amber-500" aria-label="Erişim kapalı" />
        </a>
        @elseif($webBolumuGorunur)
        <div class="section-gap" aria-hidden="true"></div>

        {{-- Web --}}
        <button type="button" class="nav-item {{ $isWeb ? 'is-active' : '' }}" x-on:click="webOpen = !webOpen" :aria-expanded="webOpen">
            <span class="nav-item-start">
                <x-filament::icon icon="heroicon-o-globe-alt" class="nav-item-icon" />
                <span>Web</span>
            </span>
            <svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
        </button>
        <div x-show="webOpen" x-collapse class="nav-group">
            <button type="button" class="nav-item {{ $isSayfalar ? 'is-active' : '' }}" x-on:click="sayfalarOpen = !sayfalarOpen" :aria-expanded="sayfalarOpen">
                <span>Sayfalar</span><svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </button>
            <div x-show="sayfalarOpen" x-collapse class="nav-group">
                <a href="{{ BilgiSayfalari::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/web/sayfalar/bilgi-sayfalari') ? 'is-active' : '' }}"><span>Bilgi Sayfaları</span></a>
                <a href="{{ Iletisim::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/web/sayfalar/iletisim') ? 'is-active' : '' }}"><span>İletişim</span></a>
                <a href="{{ Hakkimizda::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/web/sayfalar/hakkimizda') ? 'is-active' : '' }}"><span>Hakkımızda</span></a>
            </div>
            <button type="button" class="nav-item {{ $isWebModuller ? 'is-active' : '' }}" x-on:click="webModullerOpen = !webModullerOpen" :aria-expanded="webModullerOpen">
                <span>Modüller</span><svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </button>
            <div x-show="webModullerOpen" x-collapse class="nav-group">
                <a href="{{ ModulNedenBiz::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/web/web-moduller/ucuncu-grup/neden-biz') ? 'is-active' : '' }}"><span>Neden Biz</span></a>
                <a href="{{ ModulNelerYapiyoruz::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/web/web-moduller/ucuncu-grup/neler-yapiyoruz') ? 'is-active' : '' }}"><span>Neler Yapıyoruz</span></a>
                <a href="{{ ModulReferanslar::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/web/web-moduller/ucuncu-grup/referanslar') ? 'is-active' : '' }}"><span>Referanslar</span></a>
                <a href="{{ ModulRakamlarlaBiz::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/web/web-moduller/ucuncu-grup/rakamlarla-biz') ? 'is-active' : '' }}"><span>Rakamlarla Biz</span></a>
                <a href="{{ ModulTeknikDestek::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/web/web-moduller/ucuncu-grup/teknik-destek') ? 'is-active' : '' }}"><span>Teknik Destek</span></a>
                <a href="{{ ModulMusteriYorumlari::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/web/web-moduller/ucuncu-grup/musteri-yorumlari') ? 'is-active' : '' }}"><span>Müşteri Yorumları</span></a>
                <a href="{{ ModulFaqs::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/web/web-moduller/ucuncu-grup/faqs') ? 'is-active' : '' }}"><span>SSS</span></a>
            </div>
            <button type="button" class="nav-item {{ $isServisler ? 'is-active' : '' }}" x-on:click="servislerOpen = !servislerOpen" :aria-expanded="servislerOpen">
                <span>Servisler</span><svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </button>
            <div x-show="servislerOpen" x-collapse class="nav-group">
                <a href="{{ WebServisListesi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/web/servisler/web-servis-listesi') ? 'is-active' : '' }}"><span>Servis Listesi</span></a>
                <a href="{{ WebServisKategori::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/web/servisler/web-servis-kategori') ? 'is-active' : '' }}"><span>Servis Kategori</span></a>
                <a href="{{ WebServisAyarlar::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/web/servisler/ayarlar') ? 'is-active' : '' }}"><span>Ayarlar</span></a>
            </div>
            <button type="button" class="nav-item {{ $isProjeler ? 'is-active' : '' }}" x-on:click="projelerOpen = !projelerOpen" :aria-expanded="projelerOpen">
                <span>Projeler</span><svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </button>
            <div x-show="projelerOpen" x-collapse class="nav-group">
                <a href="{{ WebProje::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/web/projeler/liste') ? 'is-active' : '' }}"><span>Projeler</span></a>
                <a href="{{ WebProjeKategori::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/web/projeler/kategoriler') ? 'is-active' : '' }}"><span>Proje Kategorileri</span></a>
                <a href="{{ WebProjeAyarlar::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/web/projeler/ayarlar') ? 'is-active' : '' }}"><span>Ayarlar</span></a>
            </div>
            <button type="button" class="nav-item {{ $isBloglar ? 'is-active' : '' }}" x-on:click="bloglarOpen = !bloglarOpen" :aria-expanded="bloglarOpen">
                <span>Bloglar</span><svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </button>
            <div x-show="bloglarOpen" x-collapse class="nav-group">
                <a href="{{ BlogListesi::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/web/blog-listesi') ? 'is-active' : '' }}"><span>Blog Listesi</span></a>
                <a href="{{ BlogKategori::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/web/blog-kategori') ? 'is-active' : '' }}"><span>Blog Kategorileri</span></a>
                <a href="{{ BlogAyarlar::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/web/bloglar/ayarlar') ? 'is-active' : '' }}"><span>Ayarlar</span></a>
            </div>
            <button type="button" class="nav-item {{ $isAbonelikSistemi ? 'is-active' : '' }}" x-on:click="abonelikSistemiOpen = !abonelikSistemiOpen" :aria-expanded="abonelikSistemiOpen">
                <span>Abone Yönetimi</span><svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </button>
            <div x-show="abonelikSistemiOpen" x-collapse class="nav-group">
                <a href="{{ WebAboneler::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/web/web-ayarlar/aboneler') ? 'is-active' : '' }}"><span>Aboneler</span></a>
                <a href="{{ WebMailGonderim::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/web/web-ayarlar/mail-gonderim') ? 'is-active' : '' }}"><span>Mail Gönderim</span></a>
                <a href="{{ WebMailSablonlari::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/web/web-ayarlar/mail-sablonlari') ? 'is-active' : '' }}"><span>Mail Şablonları</span></a>
            </div>
            <button type="button" class="nav-item {{ $isWebAyarlar ? 'is-active' : '' }}" x-on:click="webAyarlarOpen = !webAyarlarOpen" :aria-expanded="webAyarlarOpen">
                <span>Site Ayarları</span><svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </button>
            <div x-show="webAyarlarOpen" x-collapse class="nav-group">
                <a href="{{ WebGenelAyarlar::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/web/web-ayarlar/web-genel-ayarlar') ? 'is-active' : '' }}"><span>Genel Ayarlar</span></a>
                <a href="{{ WebApiAyarlar::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/web/web-ayarlar/web-api-ayarlar') ? 'is-active' : '' }}"><span>Api Ayarları</span></a>
                <a href="{{ WebMailAyarlar::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/web/web-ayarlar/web-mail-ayarlar') ? 'is-active' : '' }}"><span>Mail Ayarları</span></a>
                <button type="button" class="nav-item {{ $isWebMenuAyarlari ? 'is-active' : '' }}" x-on:click="webMenuAyarlariOpen = !webMenuAyarlariOpen" :aria-expanded="webMenuAyarlariOpen">
                    <span>Menü Ayarları</span><svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                </button>
                <div x-show="webMenuAyarlariOpen" x-collapse class="nav-group">
                    <a href="{{ ModulMenu::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/web/web-ayarlar/menu-ayarlari/menu-duzenleme') ? 'is-active' : '' }}"><span>Ana Menü</span></a>
                    <a href="{{ UtilityMenuAyarlari::getUrl() }}" class="nav-item {{ request()->is($adminPrefix.'/web/web-ayarlar/menu-ayarlari/utility-menu') ? 'is-active' : '' }}"><span>Utility Menü</span></a>
                </div>
            </div>
        </div>
        @endif

        @if($bolum('ayarlar'))
        <div class="section-gap" aria-hidden="true"></div>

        {{-- Ayarlar --}}
        <button type="button" class="nav-item {{ $isAyarlar ? 'is-active' : '' }}" x-on:click="ayarlarOpen = !ayarlarOpen" :aria-expanded="ayarlarOpen">
            <span class="nav-item-start">
                <x-filament::icon icon="heroicon-o-cog-6-tooth" class="nav-item-icon" />
                <span>Ayarlar</span>
            </span>
            <svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
        </button>
        <div x-show="ayarlarOpen" x-collapse class="nav-group">
            <a href="{{ MesajMerkeziSayfasi::getUrl() }}" class="nav-item {{ $isMesajMerkezi ? 'is-active' : '' }}"><span>Mesaj Merkezi</span></a>
            <button type="button" class="nav-item {{ $isKullaniciAyarlari ? 'is-active' : '' }}" x-on:click="kullaniciAyarlariOpen = !kullaniciAyarlariOpen" :aria-expanded="kullaniciAyarlariOpen">
                <span>Kullanıcı Ayarları</span><svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </button>
            <div x-show="kullaniciAyarlariOpen" x-collapse class="nav-group">
                @if($firmaIciKullanicilarGorunur)
                    <a href="{{ FirmaIciKullaniciKaynagi::getUrl('index') }}" class="nav-item {{ $isFirmaIciKullanicilar ? 'is-active' : '' }}"><span>Firma kullanıcıları</span></a>
                    <a href="{{ FirmaKullaniciGrubuKaynagi::getUrl('index') }}" class="nav-item {{ $isFirmaKullaniciGruplari ? 'is-active' : '' }}"><span>Firma kullanıcı grupları</span></a>
                @endif
            </div>
            @if($firmaAyarlariGorunur)
                <a href="{{ FirmaAyarlariSayfasi::getUrl() }}" class="nav-item {{ $isFirmaAyarlariSayfasi ? 'is-active' : '' }}"><span>Firma ayarları</span></a>
            @endif
        </div>
        @endif
    </nav>
</div>

