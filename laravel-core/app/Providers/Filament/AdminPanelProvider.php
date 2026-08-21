<?php

namespace App\Providers\Filament;

use App\Filament\Clusters\Muhasebe\Resources\CariKartiKaynagi;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi;
use App\Filament\Clusters\Muhasebe\Resources\BankaHesabiKaynagi;
use App\Filament\Clusters\Muhasebe\Resources\KasaHesabiKaynagi;
use App\Filament\Clusters\Muhasebe\Resources\ParaBirimiTanimKaynagi;
use App\Filament\Clusters\Muhasebe\Resources\PosHesabiKaynagi;
use App\Filament\Clusters\Muhasebe\Resources\StokKartiKaynagi;
use App\Filament\Clusters\Muhasebe\Resources\StokKategoriKaynagi;
use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\DepoTanimKaynagi;
use App\Filament\Clusters\Muhasebe\Pages\BarkodEtiketYazdirmaSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\BarkodluSatisGecmisiSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\BarkodluSatisAyarlarSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\BarkodluSatisIadeFisiSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\BarkodluSatisIadeGecmisiSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\SatisFisDuzenleSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\BarkodluSatisSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\HizliSatisSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\NetteFaturaEntegrasyonSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\StokDepoTransferSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\StokDepoListesiSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\StokDepoTransferGecmisiSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\StokDepoSayimSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\StokDepoSayimGecmisiSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\StokSerileriSayfasi;
use App\Filament\Clusters\Muhasebe\Widgets\DepoHareketleriWidget;
use App\Filament\Clusters\MasrafTakip\Pages\MasrafTakibiSayfasi;
use App\Filament\Clusters\MasrafTakip\Pages\MasrafKategorileriSayfasi;
use App\Filament\Clusters\MasrafTakip\Pages\MasrafRaporlariSayfasi;
use App\Filament\Clusters\MasrafTakip\Pages\AraclarSayfasi;
use App\Filament\Clusters\MasrafTakip\Pages\DuzenliFaturaTanimlariSayfasi;
use App\Filament\Clusters\ProjeYonetimi\Pages\IsletmeProjeleriSayfasi;
use App\Filament\Clusters\ProjeYonetimi\Pages\ProjeRaporlariSayfasi;
use App\Filament\Clusters\ProjeYonetimi\Pages\ProjeHareketDetaySayfasi;
use App\Filament\Clusters\MasrafTakip\Pages\MasrafButceleriSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\VadeTakipSayfasi;
use App\Filament\Clusters\TeklifYonetimi\Resources\TeklifKaynagi;
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
use App\Filament\Clusters\ETicaret\Pages\KampanyaYonetimiSayfasi;
use App\Filament\Clusters\ETicaret\Pages\KargoYonetimiSayfasi;
use App\Filament\Clusters\ETicaret\Pages\BildirimLoglariSayfasi;
use App\Filament\Clusters\ETicaret\Pages\BildirimSablonlariSayfasi;
use App\Filament\Clusters\ETicaret\Pages\MusteriMesajlariSayfasi;
use App\Filament\Clusters\ETicaret\Pages\OdemeYonetimiSayfasi;
use App\Filament\Clusters\ETicaret\Pages\PazaryeriEntegrasyonuSayfasi;
use App\Filament\Clusters\ETicaret\Pages\UrunMesajlariSayfasi;
use App\Filament\Clusters\ETicaret\Pages\VaryasyonYonetimiSayfasi;
use App\Filament\Clusters\TeknikServis\Pages\BakimHatirlatmalariSayfasi;
use App\Filament\Clusters\TeknikServis\Pages\GarantiliCihazlarSayfasi;
use App\Filament\Clusters\TeknikServis\Pages\ServisFormu;
use App\Filament\Clusters\TeknikServis\Pages\ServisFisi;
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
use App\Filament\Clusters\TeknikServis\Pages\TeslimEdildiBelgesiSablonuSayfasi;
use App\Filament\Clusters\TeknikServis\Pages\WhatsappSablonlariSayfasi;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisAksesuarKaynagi;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisArizaKaynagi;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisCihazKaynagi;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisDurumuTanimKaynagi;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKayitliCihaziKaynagi;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisMarkaKaynagi;
use App\Filament\Clusters\Ayarlar\Pages\MesajMerkeziSayfasi;
use App\Filament\Pages\Auth\ProfilDuzenle;
use App\Filament\AvatarProviders\LocalAvatarProvider;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\FirmaAyarlariSayfasi;
use App\Filament\Pages\ModulAktifDegilSayfasi;
use App\Services\AdminLogoServisi;
use App\Filament\Pages\SistemBakimModuSayfasi;
use App\Filament\Pages\SistemYonetimiAyarlariSayfasi;
use App\Filament\Clusters\Sekreter\Pages\AjandaSayfasi;
use App\Filament\Clusters\Sekreter\Pages\GenelBakisSayfasi;
use App\Filament\Clusters\Sekreter\Resources\GorevKaynagi;
use App\Filament\Clusters\Sekreter\Resources\NotKaynagi;
use App\Filament\Clusters\Sekreter\Resources\RandevuKaynagi;
use App\Filament\Resources\DenetimKayidiKaynagi;
use App\Filament\Resources\FirmaKullaniciGrubuKaynagi;
use App\Filament\Resources\FirmaIciKullaniciKaynagi;
use App\Filament\Resources\FirmaYonetimKaynagi;
use App\Filament\Resources\ModulYonetimKaynagi;
use App\Filament\Resources\PlanYonetimKaynagi;
use App\Filament\Resources\RolYonetimKaynagi;
use App\Filament\Resources\SiparisKaynagi;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\YetkiYonetimKaynagi;
use App\Filament\Widgets\KiraciOzetWidget;
use App\Filament\Widgets\SistemYonetimiOzetWidget;
use App\Http\Middleware\FilamentAuthenticate;
use App\Http\Middleware\FilamentTenantContextMiddleware;
use App\Http\Middleware\AdminPerformanceProbeMiddleware;
use App\Http\Middleware\GzipResponseMiddleware;
use App\Models\Setting;
use App\Models\FirmaModulu;
use App\Models\Modul;
use App\Services\TenantContextService;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\MenuItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ForceDeleteAction;
use Filament\Tables\Actions\RestoreAction;
use Filament\Tables\Actions\ViewAction;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public static function adminPath(): string
    {
        try {
            $path = Setting::get('admin_path', 'admin');

            return is_string($path) && preg_match('/^[a-z0-9_-]+$/i', $path) ? trim($path) : 'admin';
        } catch (\Throwable $e) {
            return 'admin';
        }
    }

    public static function adminUrl(): string
    {
        $appUrl = rtrim((string) config('app.url', url('/')), '/');
        $adminPath = trim(self::adminPath(), '/');

        return $appUrl.'/'.$adminPath;
    }

    private static function customSidebarHtml(): string
    {
        static $requestCache = [];

        $kullanici = Auth::user();
        if (! $kullanici) {
            return view('filament.components.custom-sidebar')->render();
        }

        $tenantContext = app(TenantContextService::class);
        $superAdminMi = (bool) ($kullanici->super_admin_mi ?? false) || (bool) ($kullanici->is_admin ?? false);
        $scope = self::customSidebarCacheScope();
        // Modül durumu değiştiğinde pasif modül tanıtımları ve aktif menüler
        // eski sidebar önbelleğinden servis edilmemelidir.
        $modulDurumSurumu = implode(':', [
            (string) FirmaModulu::withoutGlobalScopes()->where('firma_id', $tenantContext->aktifFirmaId())->max('updated_at'),
            (string) Modul::query()->max('updated_at'),
        ]);
        $cacheKey = implode(':', [
            // Yönetici ve firma panelleri aynı menü şablonunu kullanır. Menü
            // görünümü değiştiğinde eski HTML önbelleği servis edilmemesi için
            // sürümü artırıyoruz.
            'filament-custom-sidebar-v19',
            (int) $kullanici->id,
            (int) ($tenantContext->aktifFirmaId() ?? 0),
            (int) ($tenantContext->aktifRolId() ?? 0),
            (int) $superAdminMi,
            $modulDurumSurumu,
            $scope,
        ]);

        if (isset($requestCache[$cacheKey])) {
            return $requestCache[$cacheKey];
        }

        try {
            return $requestCache[$cacheKey] = Cache::remember(
                $cacheKey,
                now()->addSeconds(self::customSidebarCacheSeconds($scope)),
                fn (): string => view(self::customSidebarViewName())->render(),
            );
        } catch (\Throwable $e) {
            return $requestCache[$cacheKey] = view(self::customSidebarViewName())->render();
        }
    }

    private static function customSidebarViewName(): string
    {
        return 'filament.components.custom-sidebar';
    }

    public static function hizliRecordSayfasiMi(): bool
    {
        if (request()->boolean('detay') || request()->boolean('gorsel_detay')) {
            return false;
        }

        if (filled(request()->route('record'))) {
            return true;
        }

        $adminPrefix = trim(self::adminPath(), '/');
        $path = trim(request()->path(), '/');

        return str_starts_with($path, $adminPrefix.'/teknik-servis/servis-kayitlari/olustur/');
    }

    private static function customSidebarCacheSeconds(string $scope): int
    {
        // Sidebar HTML'i firma/kullanici/rol kapsaminda cache'lendiği için her sayfa
        // açılışında yetki ağacını yeniden kurmamak adına 15 dakika tutulur.
        // Yetki ve modül değişikliklerinde ilgili cache temizleme akışı bunu zaten
        // geçersiz kılar; süre yalnızca kendiliğinden yenilemeyi sınırlar.
        if (str_starts_with($scope, 'muhasebe') || str_starts_with($scope, 'teknik-servis')) {
            return 900;
        }

        return 900;
    }

    private static function customSidebarCacheScope(): string
    {
        $adminPrefix = trim(self::adminPath(), '/');
        $path = trim(request()->path(), '/');

        $scopes = [
            'muhasebe/tanimlar/depolar' => 'depo-yonetimi',
            'muhasebe/stok/depo' => 'depo-yonetimi',
            'muhasebe/stok/depolar' => 'depo-yonetimi',
            'muhasebe/finans' => 'muhasebe.finans',
            'muhasebe/cari-yonetimi' => 'muhasebe.cari',
            'muhasebe/stok' => 'muhasebe.stok',
            'muhasebe/faturalar' => 'muhasebe.fatura',
            'muhasebe/fatura' => 'muhasebe.fatura',
            'muhasebe/satis' => 'muhasebe.satis',
            'muhasebe/raporlar' => 'muhasebe.rapor',
            'muhasebe/tanimlar' => 'muhasebe.tanim',
            'muhasebe' => 'muhasebe',
            'teknik-servis/servis-kayitlari/liste' => 'teknik-servis.liste',
            'teknik-servis/servis-kayitlari/olustur' => 'teknik-servis.olustur',
            'teknik-servis/servis-kayitlari' => 'teknik-servis.kayit',
            'teknik-servis/tanimlar' => 'teknik-servis.tanim',
            'teknik-servis/raporlar' => 'teknik-servis.rapor',
            'teknik-servis' => 'teknik-servis',
            'sekreter/ajanda' => 'sekreter.ajanda',
            'sekreter/gorevler' => 'sekreter.gorevler',
            'sekreter/notlar' => 'sekreter.notlar',
            'sekreter' => 'sekreter',
            'teklif-yonetimi' => 'teklif-yonetimi',
            'personel-takip/tanimlar' => 'personel-takip.tanim',
            'personel-takip/raporlar' => 'personel-takip.rapor',
            'personel-takip/terminal' => 'personel-takip.terminal',
            'personel-takip/personeller' => 'personel-takip.personeller',
            'personel-takip/giris-cikis' => 'personel-takip.giris-cikis',
            'personel-takip/vardiyalar' => 'personel-takip.vardiyalar',
            'personel-takip/izinler' => 'personel-takip.izinler',
            'personel-takip/avanslar' => 'personel-takip.avanslar',
            'personel-takip/maas-donemleri' => 'personel-takip.maas-donemleri',
            'personel-takip' => 'personel-takip',
            'e-ticaret/mesaj-yonetimi' => 'e-ticaret.mesaj',
            'e-ticaret/bildirim-yonetimi' => 'e-ticaret.bildirim',
            'e-ticaret/kampanya-yonetimi' => 'e-ticaret.kampanya',
            'e-ticaret/kargo-yonetimi' => 'e-ticaret.kargo',
            'e-ticaret/odeme-yonetimi' => 'e-ticaret.odeme',
            'e-ticaret/pazaryeri-entegrasyonu' => 'e-ticaret.pazaryeri',
            'e-ticaret/varyasyon-yonetimi' => 'e-ticaret.varyasyon',
            'e-ticaret' => 'e-ticaret',
            'siparisler' => 'e-ticaret.siparis',
            'products' => 'e-ticaret.products',
            'product-categories' => 'e-ticaret.product-categories',
            'web/web-moduller' => 'web.moduller',
            'web/web-ayarlar' => 'web.ayarlar',
            'web/bloglar' => 'web.blog',
            'web/projeler' => 'web.proje',
            'web/servisler' => 'web.servis',
            'web/sayfalar' => 'web.sayfa',
            'web/urunler' => 'web.urun',
            'web' => 'web',
            'ayarlar/kullanici-ayarlari' => 'ayarlar.kullanici',
            'ayarlar/mesaj-merkezi' => 'ayarlar.mesaj',
            'ayarlar' => 'ayarlar',
            'firma-ayarlari' => 'firma-ayarlari',
            'firma-ici-kullanicilar' => 'firma-ici-kullanicilar',
            'firma-kullanici-gruplari' => 'firma-kullanici-gruplari',
            'sistem-denetim-kayitlari' => 'sistem.denetim',
            'sistem-firmalar' => 'sistem.firmalar',
            'sistem-kullanicilar' => 'sistem.kullanicilar',
            'sistem-moduller' => 'sistem.moduller',
            'sistem-planlar' => 'sistem.planlar',
            'sistem-roller' => 'sistem.roller',
            'sistem-yetkiler' => 'sistem.yetkiler',
            'profil' => 'profil',
        ];

        foreach ($scopes as $prefix => $scope) {
            $fullPrefix = $adminPrefix.'/'.$prefix;
            if ($path === $fullPrefix || str_starts_with($path, $fullPrefix.'/')) {
                return $scope;
            }
        }

        return $path === $adminPrefix ? 'dashboard' : 'other:'.sha1($path);
    }

    public function panel(Panel $panel): Panel
    {
        $this->configureStandardTableActions();

        return $panel
            ->default()
            ->id('admin')
            ->path(self::adminPath())
            ->login(null)
            ->brandLogo(fn (): string => app(AdminLogoServisi::class)->url())
            ->brandLogoHeight('2.25rem')
            ->defaultAvatarProvider(LocalAvatarProvider::class)
            ->maxContentWidth(MaxWidth::Full)
            ->spa()
            ->spaUrlExceptions(fn (): array => [
                self::adminUrl().'/muhasebe/satis/barkodlu-satis-fisi*',
                self::adminUrl().'/muhasebe/satis/barkodlu-satis-iade-fisi*',
            ])
            ->navigation(fn (NavigationBuilder $builder): NavigationBuilder => $builder->items([]))
            ->profile(ProfilDuzenle::class, isSimple: false)
            ->homeUrl(function (): string {
                $kullanici = Auth::user();

                if (! $kullanici) {
                    return route('yonetici.login');
                }

                $superAdminMi = (bool) ($kullanici->super_admin_mi ?? false) || (bool) ($kullanici->is_admin ?? false);
                if ($superAdminMi) {
                    return self::adminUrl();
                }

                $aktifFirmaId = app(TenantContextService::class)->aktifFirmaId();

                return $aktifFirmaId ? self::adminUrl() : route('tenant.login');
            })
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverClusters(
                in: app_path('Filament/Clusters'),
                for: 'App\\Filament\\Clusters',
            )
            ->resources([
                CariKartiKaynagi::class,
                FaturaKaynagi::class,
                BankaHesabiKaynagi::class,
                ParaBirimiTanimKaynagi::class,
                KasaHesabiKaynagi::class,
                PosHesabiKaynagi::class,
                StokKartiKaynagi::class,
                StokKategoriKaynagi::class,
                DepoTanimKaynagi::class,
                TeklifKaynagi::class,
                PersonelKaynagi::class,
                PersonelDepartmanKaynagi::class,
                PersonelGorevKaynagi::class,
                SubeKaynagi::class,
                PersonelVardiyaSablonuKaynagi::class,
                PersonelVardiyaKaynagi::class,
                PersonelGirisCikisKaynagi::class,
                PersonelIzinKaynagi::class,
                PersonelAvansKaynagi::class,
                PersonelMaasDonemiKaynagi::class,
                SiparisKaynagi::class,
                ModulYonetimKaynagi::class,
                YetkiYonetimKaynagi::class,
                RolYonetimKaynagi::class,
                FirmaYonetimKaynagi::class,
                PlanYonetimKaynagi::class,
                DenetimKayidiKaynagi::class,
                FirmaKullaniciGrubuKaynagi::class,
                FirmaIciKullaniciKaynagi::class,
                UserResource::class,
                TeknikServisKaydiKaynagi::class,
                TeknikServisKayitliCihaziKaynagi::class,
                TeknikServisDurumuTanimKaynagi::class,
                TeknikServisCihazKaynagi::class,
                TeknikServisMarkaKaynagi::class,
                TeknikServisAksesuarKaynagi::class,
                TeknikServisArizaKaynagi::class,
                GorevKaynagi::class,
                NotKaynagi::class,
                RandevuKaynagi::class,
            ])
            ->pages([
                Dashboard::class,
                FirmaAyarlariSayfasi::class,
                ModulAktifDegilSayfasi::class,
                SistemBakimModuSayfasi::class,
                SistemYonetimiAyarlariSayfasi::class,
                MesajMerkeziSayfasi::class,
                TeknikServisDashboardSayfasi::class,
                TeknikServisGenelAyarlarSayfasi::class,
                ServisTalepFormuSablonuSayfasi::class,
                ServisFormuSablonuSayfasi::class,
                ServisFormu::class,
                ServisFisi::class,
                ServisKabulFormuSablonuSayfasi::class,
                ServisFisiSablonuSayfasi::class,
                TeslimEdildiBelgesiSablonuSayfasi::class,
                WhatsappSablonlariSayfasi::class,
                TeknikServisIslemLoglariSayfasi::class,
                TeknikServisMesajGecmisiSayfasi::class,
                GarantiliCihazlarSayfasi::class,
                BakimHatirlatmalariSayfasi::class,
                TeknikServisKarlilikRaporuSayfasi::class,
                TeknikServisPersonelPerformansRaporuSayfasi::class,
                TeknikServisDurumBazliRaporuSayfasi::class,
                TeknikServisGarantiBakimRaporuSayfasi::class,
                TeknikServisTahsilatServisRaporuSayfasi::class,
                PersonelTakipOzetSayfasi::class,
                PersonelPinTerminalSayfasi::class,
                PersonelRaporlariSayfasi::class,
                PersonelAyarlariSayfasi::class,
                BarkodluSatisSayfasi::class,
                HizliSatisSayfasi::class,
                BarkodEtiketYazdirmaSayfasi::class,
                BarkodluSatisGecmisiSayfasi::class,
                BarkodluSatisAyarlarSayfasi::class,
                SatisFisDuzenleSayfasi::class,
                BarkodluSatisIadeGecmisiSayfasi::class,
                BarkodluSatisIadeFisiSayfasi::class,
                VadeTakipSayfasi::class,
                NetteFaturaEntegrasyonSayfasi::class,
                StokDepoTransferSayfasi::class,
                StokDepoListesiSayfasi::class,
                StokDepoTransferGecmisiSayfasi::class,
                StokDepoSayimSayfasi::class,
                StokDepoSayimGecmisiSayfasi::class,
                StokSerileriSayfasi::class,
                MasrafTakibiSayfasi::class,
                MasrafKategorileriSayfasi::class,
                MasrafRaporlariSayfasi::class,
                AraclarSayfasi::class,
                DuzenliFaturaTanimlariSayfasi::class,
                IsletmeProjeleriSayfasi::class,
                ProjeRaporlariSayfasi::class,
                ProjeHareketDetaySayfasi::class,
                MasrafButceleriSayfasi::class,
                KampanyaYonetimiSayfasi::class,
                PazaryeriEntegrasyonuSayfasi::class,
                VaryasyonYonetimiSayfasi::class,
                KargoYonetimiSayfasi::class,
                OdemeYonetimiSayfasi::class,
                MusteriMesajlariSayfasi::class,
                UrunMesajlariSayfasi::class,
                BildirimSablonlariSayfasi::class,
                BildirimLoglariSayfasi::class,
                GenelBakisSayfasi::class,
                AjandaSayfasi::class,
            ])
            // Kiracı dashboard bu widget'ı getWidgets() ile kullanır; panelde kayıt olmazsa Livewire
            // `app.filament.widgets.kiraci-ozet-widget` bileşenini bulamaz (500 / livewire/update).
            ->widgets([
                KiraciOzetWidget::class,
                SistemYonetimiOzetWidget::class,
                DepoHareketleriWidget::class,
            ])
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_START,
                fn (): string => rescue(
                    fn (): string => self::customSidebarHtml(),
                    '',
                    report: true,
                ),
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn () => view('filament.components.admin-context-badge'),
            )
            ->renderHook(
                PanelsRenderHook::USER_MENU_PROFILE_AFTER,
                fn () => view('filament.components.admin-layout-switcher'),
            )
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn () => view('filament.components.panel-table-overrides'),
            )
            ->middleware([
                GzipResponseMiddleware::class,
                AdminPerformanceProbeMiddleware::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                FilamentTenantContextMiddleware::class,
                \App\Http\Middleware\SistemBakimModuMiddleware::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                FilamentAuthenticate::class,
            ]);
    }

    private function configureStandardTableActions(): void
    {
        foreach ([
            [CreateAction::class, 'Oluştur'],
            [EditAction::class, 'Düzenle'],
            [DeleteAction::class, 'Sil'],
            [ForceDeleteAction::class, 'Kalıcı sil'],
            [RestoreAction::class, 'Geri yükle'],
            [ViewAction::class, 'Görüntüle'],
        ] as [$actionClass, $tooltip]) {
            $actionClass::configureUsing(
                fn ($action) => $action
                    ->iconButton()
                    ->tooltip($tooltip),
                );
        }

        TableAction::configureUsing(function (TableAction $action): void {
            try {
                $name = mb_strtolower((string) ($action->getName() ?? ''), 'UTF-8');
            } catch (\Throwable) {
                $name = '';
            }

            // Servis muhasebe tablosundaki form açan işlemler metinli buton
            // olarak kalmalı; genel tablo standardı bunları ikon butonuna
            // çevirirse kullanıcı hangi işlemi açacağını göremez.
            if (in_array($name, ['masraf_ekle', 'gider_ekle', 'tahsilat_ekle'], true)) {
                $action->tooltip((string) $action->getLabel());

                return;
            }

            $details = match (true) {
                str_contains($name, 'tahsilat') => ['heroicon-o-arrow-down-left', 'Tahsilat'],
                str_contains($name, 'odeme') => ['heroicon-o-arrow-up-right', 'Ödeme'],
                str_contains($name, 'ekstre') => ['heroicon-o-document-text', 'Cari ekstresini görüntüle'],
                str_contains($name, 'cariler') => ['heroicon-o-arrow-left', 'Cari listesine dön'],
                str_contains($name, 'duzenle') || str_contains($name, 'duzelt') || str_contains($name, 'edit') => ['heroicon-o-pencil-square', 'Düzenle'],
                str_contains($name, 'sil') || str_contains($name, 'delete') => ['heroicon-o-trash', 'Sil'],
                str_contains($name, 'goruntule') || str_contains($name, 'detay') || str_contains($name, 'onizle') || str_contains($name, 'view') => ['heroicon-o-eye', 'Görüntüle'],
                str_contains($name, 'ekle') || str_contains($name, 'olustur') || str_contains($name, 'create') || str_contains($name, 'yeni') => ['heroicon-o-plus', 'Oluştur'],
                str_contains($name, 'onay') || str_contains($name, 'aktif') => ['heroicon-o-check-circle', 'Onayla'],
                str_contains($name, 'reddet') || str_contains($name, 'iptal') => ['heroicon-o-x-circle', 'İptal'],
                str_contains($name, 'yazdir') || str_contains($name, 'print') => ['heroicon-o-printer', 'Yazdır'],
                str_contains($name, 'indir') || str_contains($name, 'export') => ['heroicon-o-arrow-down-tray', 'İndir'],
                str_contains($name, 'aktar') || str_contains($name, 'import') => ['heroicon-o-arrow-up-tray', 'Aktar'],
                default => ['heroicon-o-ellipsis-horizontal', 'İşlem'],
            };

            if (! filled($action->getIcon())) {
                $action->icon($details[0]);
            }

            $action
                ->iconButton()
                ->tooltip($details[1]);
        });
    }
}

