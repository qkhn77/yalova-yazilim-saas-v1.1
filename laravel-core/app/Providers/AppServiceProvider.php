<?php

namespace App\Providers;

use App\Models\DenetimKayidi;
use App\Models\Ecommerce\Siparis;
use App\Models\Firma;
use App\Models\FirmaAboneligi;
use App\Models\FirmaKullanici;
use App\Models\FirmaModulu;
use App\Models\KullaniciYetki;
use App\Models\Modul;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\Birim;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\FaturaKalemi;
use App\Models\Muhasebe\PosHesabi;
use App\Models\Muhasebe\StokHareketi;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\StokKategorisi;
use App\Models\Muhasebe\Teklif;
use App\Models\Muhasebe\VergiOrani;
use App\Models\TeklifYonetimi\TeklifBaskiSablonu;
use App\Models\TeknikServis\TeknikServisAksesuarTanimi;
use App\Models\TeknikServis\TeknikServisArizaTanimi;
use App\Models\TeknikServis\TeknikServisCihazTanimi;
use App\Models\TeknikServis\TeknikServisDurumTanimi;
use App\Models\TeknikServis\TeknikServisKaydi;
use App\Models\TeknikServis\TeknikServisMarkaTanimi;
use App\Models\TeknikServis\TeknikServisMesajSablonu;
use App\Models\Plan;
use App\Models\PlanModulu;
use App\Models\SekreterGorevi;
use App\Models\SekreterNotu;
use App\Models\SekreterRandevusu;
use App\Models\Setting;
use App\Models\User;
use App\Observers\CariObserver;
use App\Observers\FaturaKalemiObserver;
use App\Observers\FaturaObserver;
use App\Observers\PosHesabiObserver;
use App\Observers\SiparisObserver;
use App\Observers\StokKartiObserver;
use App\Observers\TeknikServisFormSecenekCacheObserver;
use App\Observers\TeknikServisOkumaCacheObserver;
use App\Policies\CariPolicy;
use App\Policies\FaturaPolicy;
use App\Policies\FirmaIciKullaniciPolitikasi;
use App\Policies\FirmaPolicy;
use App\Policies\KullaniciOzelYetkiPolitikasi;
use App\Policies\KullaniciPolicy;
use App\Policies\ModulPolicy;
use App\Policies\PosHesabiPolicy;
use App\Policies\SadeceSuperAdminPolitikasi;
use App\Policies\SekreterGoreviPolicy;
use App\Policies\SekreterNotuPolicy;
use App\Policies\SekreterRandevusuPolicy;
use App\Policies\StokHareketiPolicy;
use App\Policies\StokKartiPolicy;
use App\Policies\StokKategorisiPolicy;
use App\Policies\TeklifPolicy;
use App\Policies\TeklifBaskiSablonuPolicy;
use App\Services\FirmaAyarDeposu;
use App\Services\ModulErisimService;
use App\Services\SidebarService;
use App\Services\TenantContextService;
use App\Services\YetkiService;
use App\Support\LivewireRequestSignatureValidator;
use App\Support\TanimAktarSilmeServisi;
use App\Support\TablePaginationDefaults;
use Filament\Tables\Table;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Uygulama çekirdeği laravel-core, web root ise kardeş public_html klasörüdür.
        $this->app->bind('path.public', fn () => dirname(base_path()) . DIRECTORY_SEPARATOR . 'public_html');

        $this->app->scoped(FirmaAyarDeposu::class);
        $this->app->scoped(TenantContextService::class);
        $this->app->scoped(ModulErisimService::class);
        $this->app->scoped(YetkiService::class);
        $this->app->scoped(SidebarService::class);
    }

    public function boot(): void
    {
        // ADMIN DATATABLE STANDARDI:
        // Yeni ortak davranış eklemeden önce performans ve prototip kapısını okuyun:
        // docs/architecture/admin-table-standard.md
        Table::configureUsing(
            fn (Table $table): Table => $table
                ->heading(fn (Table $table): string => (string) $table->getPluralModelLabel())
                ->paginationPageOptions(TablePaginationDefaults::OPTIONS)
                ->defaultPaginationPageOption(fn (): int|string => TablePaginationDefaults::forActiveTenant()),
        );

        $this->kayitRateLimiters();
        $this->tanimSilmeAksiyonlariniYapilandir();

        Request::macro('hasValidSignature', function (bool $absolute = true): bool {
            /** @var Request $this */
            return LivewireRequestSignatureValidator::hasValidSignature($this, $absolute);
        });

        Request::macro('hasValidRelativeSignature', function (): bool {
            /** @var Request $this */
            return LivewireRequestSignatureValidator::hasValidSignature($this, false);
        });

        Request::macro('hasValidSignatureWhileIgnoring', function (array $ignoreQuery = [], bool $absolute = true): bool {
            /** @var Request $this */
            return LivewireRequestSignatureValidator::hasValidSignature($this, $absolute, $ignoreQuery);
        });

        Request::macro('hasValidRelativeSignatureWhileIgnoring', function (array $ignoreQuery = []): bool {
            /** @var Request $this */
            return LivewireRequestSignatureValidator::hasValidSignature($this, false, $ignoreQuery);
        });

        PosHesabi::observe(PosHesabiObserver::class);
        Cari::observe(CariObserver::class);
        Fatura::observe(FaturaObserver::class);
        FaturaKalemi::observe(FaturaKalemiObserver::class);
        StokKarti::observe(StokKartiObserver::class);
        Siparis::observe(SiparisObserver::class);
        TeknikServisKaydi::observe(TeknikServisOkumaCacheObserver::class);
        TeknikServisDurumTanimi::observe(TeknikServisFormSecenekCacheObserver::class);
        TeknikServisDurumTanimi::observe(TeknikServisOkumaCacheObserver::class);
        TeknikServisCihazTanimi::observe(TeknikServisFormSecenekCacheObserver::class);
        TeknikServisMarkaTanimi::observe(TeknikServisFormSecenekCacheObserver::class);
        TeknikServisAksesuarTanimi::observe(TeknikServisFormSecenekCacheObserver::class);
        TeknikServisArizaTanimi::observe(TeknikServisFormSecenekCacheObserver::class);
        TeknikServisMesajSablonu::observe(TeknikServisFormSecenekCacheObserver::class);
        Birim::observe(TeknikServisFormSecenekCacheObserver::class);
        VergiOrani::observe(TeknikServisFormSecenekCacheObserver::class);

        // Tüm çıkış yollarında (web /cikis, Filament vb.) tenant oturumu tek sefer temizlensin.
        Event::listen(Logout::class, function (): void {
            app(TenantContextService::class)->temizle();
        });

        Gate::policy(Firma::class, FirmaPolicy::class);
        Gate::policy(PosHesabi::class, PosHesabiPolicy::class);
        Gate::policy(Cari::class, CariPolicy::class);
        Gate::policy(Fatura::class, FaturaPolicy::class);
        Gate::policy(Teklif::class, TeklifPolicy::class);
        Gate::policy(TeklifBaskiSablonu::class, TeklifBaskiSablonuPolicy::class);
        Gate::policy(StokKarti::class, StokKartiPolicy::class);
        Gate::policy(StokKategorisi::class, StokKategorisiPolicy::class);
        Gate::policy(StokHareketi::class, StokHareketiPolicy::class);
        Gate::policy(User::class, KullaniciPolicy::class);
        Gate::policy(Modul::class, ModulPolicy::class);
        Gate::policy(SekreterGorevi::class, SekreterGoreviPolicy::class);
        Gate::policy(SekreterRandevusu::class, SekreterRandevusuPolicy::class);
        Gate::policy(SekreterNotu::class, SekreterNotuPolicy::class);

        Gate::policy(DenetimKayidi::class, SadeceSuperAdminPolitikasi::class);
        Gate::policy(Plan::class, SadeceSuperAdminPolitikasi::class);
        Gate::policy(PlanModulu::class, SadeceSuperAdminPolitikasi::class);
        Gate::policy(FirmaAboneligi::class, SadeceSuperAdminPolitikasi::class);
        Gate::policy(FirmaModulu::class, SadeceSuperAdminPolitikasi::class);
        Gate::policy(FirmaKullanici::class, FirmaIciKullaniciPolitikasi::class);
        Gate::policy(KullaniciYetki::class, KullaniciOzelYetkiPolitikasi::class);

        if (! app()->runningInConsole()) {
            $istek = request();

            // Port dahil tam kök (örn. https://127.0.0.1:8000) — yoksa asset()/Filament CSS yanlış porta gider.
            // Bazı canlı ortamlarda SSL proxy arkasında request()->getScheme() hatalı olarak http dönebilir.
            $sema = $this->algilananIstekSemasi($istek);
            if ($this->httpsZorlanmaliMi($istek)) {
                $sema = 'https';
            }
            $kok = $sema.'://'.$istek->getHttpHost();
            $istekRootPath = rtrim((string) (parse_url($istek->root(), PHP_URL_PATH) ?? ''), '/');
            $altDizin = $istekRootPath !== '' ? $istekRootPath : rtrim((string) $istek->getBaseUrl(), '/');
            if ($altDizin === '') {
                $configAltDizin = rtrim((string) (parse_url((string) config('app.url', ''), PHP_URL_PATH) ?? ''), '/');
                $altDizin = $configAltDizin;
            }
            $kokUrl = rtrim($kok.$altDizin, '/');

            $uygulanacakKokUrl = $kokUrl;

            try {
                $url = Setting::get('site_url');
                if (! empty($url) && is_string($url)) {
                    $uygulanacakKokUrl = $this->normalizeSiteUrl($url, $kokUrl, $sema);
                }
            } catch (\Throwable $e) {
                // ilk kurulumda hata vermesin
            }

            URL::forceScheme((string) (parse_url($uygulanacakKokUrl, PHP_URL_SCHEME) ?: $sema));
            URL::forceRootUrl($uygulanacakKokUrl);
            config([
                'app.url' => $uygulanacakKokUrl,
                'filesystems.disks.public.url' => $uygulanacakKokUrl.'/storage',
            ]);

            // URL::forceRootUrl() alt-dizin yolunu zaten kok URL'e ekler.
            // Route tarafinda tekrar prefix vermek Livewire update adresini
            // /yalova-kamera/yalova-kamera/livewire/update haline getirir.
            if ($altDizin !== '') {
                Livewire::setUpdateRoute(function ($handle) {
                    return Route::post('/livewire/update', $handle)
                        ->middleware('web')
                        ->name('default.livewire.update');
                });

                Livewire::setScriptRoute(function ($handle) {
                    return config('app.debug')
                        ? Route::get('/livewire/livewire.js', $handle)
                        : Route::get('/livewire/livewire.min.js', $handle);
                });
            }

            // XAMPP alt-dizin kurulumunda (or. /yalova-kamera) Livewire asset URL'i
            // kokten (/livewire/...) uretilirse 404 verir. Base URL prefixi uygula.
            $appUrlPath = (string) (parse_url((string) config('app.url'), PHP_URL_PATH) ?: '');
            $appUrlPath = '/'.trim($appUrlPath, '/');
            if ($appUrlPath === '/') {
                $appUrlPath = '';
            }

            $livewireBasePath = $appUrlPath !== '' ? $appUrlPath : ($altDizin !== '' ? $altDizin : '');
            $livewireScript = (config('app.debug') ? 'livewire.js' : 'livewire.min.js');
            $livewireAssetPrefix = $livewireBasePath !== ''
                ? rtrim($livewireBasePath, '/').'/livewire/'.$livewireScript
                : null;

            config([
                'livewire.asset_url' => $livewireAssetPrefix,
            ]);

            try {
                // Mail ayarları
                $mailActive = (bool) Setting::get('mail_active', true);

                if ($mailActive) {
                    $hostMail = Setting::get('mail_host');

                    if (! empty($hostMail)) {
                        config([
                            'mail.default' => 'smtp',
                            'mail.mailers.smtp' => array_merge(config('mail.mailers.smtp', []), [
                                'host' => $hostMail,
                                'port' => (int) Setting::get('mail_port', 587),
                                'encryption' => Setting::get('mail_encryption') ?: 'tls',
                                'username' => Setting::get('mail_username'),
                                'password' => Setting::get('mail_password'),
                            ]),
                            'mail.from' => [
                                'address' => Setting::get('mail_username') ?: config('mail.from.address'),
                                'name' => config('mail.from.name'),
                            ],
                        ]);
                    }
                } else {
                    config(['mail.default' => 'log']);
                }

            } catch (\Throwable $e) {
                // ilk kurulumda hata vermesin
            }
        }
    }

    private function tanimSilmeAksiyonlariniYapilandir(): void
    {
        \Filament\Tables\Actions\DeleteAction::configureUsing(function ($action): void {
            $action
                ->form(fn (?\Illuminate\Database\Eloquent\Model $record): array => $record ? TanimAktarSilmeServisi::form($record) : [])
                ->using(fn (\Illuminate\Database\Eloquent\Model $record, array $data): bool => TanimAktarSilmeServisi::uygula($record, $data));
        });

        \Filament\Actions\DeleteAction::configureUsing(function ($action): void {
            $action
                ->form(fn (?\Illuminate\Database\Eloquent\Model $record): array => $record ? TanimAktarSilmeServisi::form($record) : [])
                ->using(fn (\Illuminate\Database\Eloquent\Model $record, array $data): bool => TanimAktarSilmeServisi::uygula($record, $data));
        });
    }

    private function kayitRateLimiters(): void
    {
        RateLimiter::for('tenant-login', function ($request) {
            $kimlik = (string) $request->input('kullanici_adi_veya_eposta', '');
            $firmaKodu = (string) $request->input('firma_kodu', '');
            $ip = (string) $request->ip();

            return [
                Limit::perMinute(10)->by('tenant-login:ip:'.$ip),
                Limit::perMinute(5)->by('tenant-login:kimlik:'.sha1(mb_strtolower(trim($firmaKodu.'|'.$kimlik)))),
            ];
        });

        RateLimiter::for('admin-login', function ($request) {
            $kimlik = (string) $request->input('kullanici_adi_veya_eposta', '');
            $ip = (string) $request->ip();

            return [
                Limit::perMinute(8)->by('admin-login:ip:'.$ip),
                Limit::perMinute(5)->by('admin-login:kimlik:'.sha1(mb_strtolower(trim($kimlik)))),
            ];
        });

        RateLimiter::for('checkout-submit', function ($request) {
            $userKey = auth()->check() ? 'user:'.(string) auth()->id() : 'ip:'.(string) $request->ip();

            return [
                Limit::perMinute(20)->by('checkout-submit:'.$userKey),
                Limit::perMinute(40)->by('checkout-submit:ip:'.(string) $request->ip()),
            ];
        });

        RateLimiter::for('odeme-retry', function ($request) {
            $siparis = (string) ($request->route('siparis') ?? '0');
            $userKey = auth()->check() ? 'user:'.(string) auth()->id() : 'ip:'.(string) $request->ip();

            return [
                Limit::perMinute(10)->by('odeme-retry:'.$userKey),
                Limit::perMinute(6)->by('odeme-retry:siparis:'.$siparis),
            ];
        });

        RateLimiter::for('contact-submit', function ($request) {
            $limitResponse = static fn ($request, array $headers) => redirect()
                ->route('contact')
                ->with('error', 'Bu IP adresinden iletişim formu gönderim sınırına ulaştınız. Lütfen daha sonra tekrar deneyin.')
                ->withHeaders($headers);

            return [
                Limit::perMinutes(10, 1)
                    ->by('contact-submit:ip:'.(string) $request->ip())
                    ->response($limitResponse),
                Limit::perDay(3)
                    ->by('contact-submit:daily-ip:'.(string) $request->ip())
                    ->response($limitResponse),
            ];
        });
    }

    private function algilananIstekSemasi(Request $istek): string
    {
        if ($istek->isSecure()) {
            return 'https';
        }

        $adaylar = [
            $istek->headers->get('x-forwarded-proto'),
            $istek->headers->get('x-forwarded-scheme'),
            $istek->headers->get('cloudfront-forwarded-proto'),
            $istek->server->get('HTTP_X_FORWARDED_PROTO'),
            $istek->server->get('HTTP_X_FORWARDED_SCHEME'),
            $istek->server->get('HTTP_CLOUDFRONT_FORWARDED_PROTO'),
            $istek->server->get('REQUEST_SCHEME'),
        ];

        foreach ($adaylar as $aday) {
            if (! is_string($aday) || trim($aday) === '') {
                continue;
            }

            $sema = strtolower(trim(explode(',', $aday)[0]));
            if (in_array($sema, ['http', 'https'], true)) {
                return $sema;
            }
        }

        $httpsDegeri = strtolower((string) ($istek->server->get('HTTPS') ?? ''));
        if (in_array($httpsDegeri, ['on', '1', 'https'], true)) {
            return 'https';
        }

        $forwardedSsl = strtolower((string) ($istek->headers->get('x-forwarded-ssl') ?? $istek->server->get('HTTP_X_FORWARDED_SSL') ?? ''));
        if ($forwardedSsl === 'on') {
            return 'https';
        }

        $cfVisitor = (string) ($istek->headers->get('cf-visitor') ?? $istek->server->get('HTTP_CF_VISITOR') ?? '');
        if ($cfVisitor !== '' && str_contains(strtolower($cfVisitor), '"https"')) {
            return 'https';
        }

        $serverPort = (string) ($istek->server->get('SERVER_PORT') ?? '');
        if ($serverPort === '443') {
            return 'https';
        }

        return $istek->getScheme();
    }

    private function httpsZorlanmaliMi(Request $istek): bool
    {
        $host = strtolower((string) $istek->getHost());
        if ($host === '' || $host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
            return false;
        }

        $hamDeger = env('APP_FORCE_HTTPS');
        $zorla = filter_var($hamDeger, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if ($zorla === true) {
            return true;
        }

        // Canlı hostlarda yanlışlıkla APP_FORCE_HTTPS=false tanımlı olsa bile
        // mutlak olarak HTTPS zorlanır.
        if ($zorla === false) {
            return ! app()->environment('local');
        }

        // APP_FORCE_HTTPS tanimli degilse localhost disinda her hostta https zorla.
        return true;
    }

    private function normalizeSiteUrl(string $siteUrl, string $istekKokUrl, string $istekSemasi): string
    {
        $siteUrl = rtrim(trim($siteUrl), '/');
        if ($siteUrl === '') {
            return $istekKokUrl;
        }

        $parcalar = parse_url($siteUrl);
        if ($parcalar === false) {
            return $istekKokUrl;
        }

        $siteSemasi = strtolower((string) ($parcalar['scheme'] ?? ''));
        $siteHost = (string) ($parcalar['host'] ?? '');
        $istekHost = (string) (parse_url($istekKokUrl, PHP_URL_HOST) ?? '');

        if ($siteHost !== '' && $istekHost !== '' && strcasecmp($siteHost, $istekHost) !== 0) {
            return $istekKokUrl;
        }

        if ($istekSemasi === 'https' && $siteSemasi === 'http') {
            $parcalar['scheme'] = 'https';
            $siteUrl = $this->buildUrlFromParts($parcalar);
        }

        $istekYolu = rtrim((string) (parse_url($istekKokUrl, PHP_URL_PATH) ?? ''), '/');
        $siteYolu = rtrim((string) ($parcalar['path'] ?? ''), '/');

        if ($istekYolu !== '' && ($siteYolu === '' || $siteYolu === '/')) {
            $parcalar['path'] = $istekYolu;
            $siteUrl = $this->buildUrlFromParts($parcalar);
        }

        if ($siteHost === '') {
            return $istekKokUrl;
        }

        return rtrim($siteUrl, '/');
    }

    private function buildUrlFromParts(array $parcalar): string
    {
        $sema = isset($parcalar['scheme']) ? $parcalar['scheme'].'://' : '';
        $kullanici = $parcalar['user'] ?? '';
        $sifre = isset($parcalar['pass']) ? ':'.$parcalar['pass'] : '';
        $auth = $kullanici !== '' ? $kullanici.$sifre.'@' : '';
        $host = $parcalar['host'] ?? '';
        $port = isset($parcalar['port']) ? ':'.$parcalar['port'] : '';
        $path = $parcalar['path'] ?? '';
        $query = isset($parcalar['query']) ? '?'.$parcalar['query'] : '';
        $fragment = isset($parcalar['fragment']) ? '#'.$parcalar['fragment'] : '';

        return $sema.$auth.$host.$port.$path.$query.$fragment;
    }
}
