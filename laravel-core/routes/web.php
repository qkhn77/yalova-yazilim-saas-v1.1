<?php

use App\Http\Controllers\Auth\TenantAuthController;
use App\Http\Controllers\Auth\YoneticiGirisDenetleyici;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\EcommerceCronFallbackController;
use App\Http\Controllers\HesabimController;
use App\Http\Controllers\KargoWebhookDenetleyici;
use App\Http\Controllers\NewsletterSubscriberController;
use App\Http\Controllers\NewsletterSubscriberExportController;
use App\Http\Controllers\FrontTercihController;
use App\Http\Controllers\OdemeController;
use App\Http\Controllers\OdemeWebhookDenetleyici;
use App\Http\Controllers\RestoranQrMenuController;
use App\Http\Controllers\SepetController;
use App\Http\Controllers\SiparisTakipController;
use App\Http\Controllers\SistemHealthController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\TeklifPdfController;
use App\Http\Controllers\UrunController;
use App\Filament\Pages\SistemYedekleriSayfasi;
use App\Services\SistemYedekleriServisi;
use App\Support\UygulamaUrl;
use App\Models\BilgiSayfa;
use App\Models\Page;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Muhasebe\Masraf as MasrafModel;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Support\FrontIcerikCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/giris', [TenantAuthController::class, 'girisFormu'])->name('tenant.login');
Route::post('/giris', [TenantAuthController::class, 'giris'])
    ->middleware('throttle:tenant-login')
    ->name('tenant.login.attempt');

Route::get('/uye-giris', [TenantAuthController::class, 'aliciGirisFormu'])->name('buyer.login');
Route::post('/uye-giris', [TenantAuthController::class, 'aliciGiris'])
    ->middleware('throttle:tenant-login')
    ->name('buyer.login.attempt');

Route::get('/yonetici-giris', [YoneticiGirisDenetleyici::class, 'girisFormu'])->name('yonetici.login');
Route::post('/yonetici-giris', [YoneticiGirisDenetleyici::class, 'giris'])
    ->middleware('throttle:admin-login')
    ->name('yonetici.login.attempt');

// Geriye donuk uyumluluk: eski alici URL'leri yeni uye ekranina yonlenir.
Route::redirect('/alici-giris', '/uye-giris', 301);
Route::post('/alici-giris', [TenantAuthController::class, 'aliciGiris'])
    ->middleware('throttle:tenant-login');

Route::middleware('guest')->group(function (): void {
    Route::get('/kayit', [TenantAuthController::class, 'kayitFormu'])->name('tenant.register');
    Route::post('/kayit', [TenantAuthController::class, 'kayit'])
        ->middleware('throttle:10,1')
        ->name('tenant.register.attempt');
    Route::get('/uye-kayit', [TenantAuthController::class, 'aliciKayitFormu'])->name('buyer.register');
    Route::post('/uye-kayit', [TenantAuthController::class, 'kayit'])
        ->middleware('throttle:10,1')
        ->name('buyer.register.attempt');

    // Geriye donuk uyumluluk: eski alici URL'leri yeni uye ekranina yonlenir.
    Route::redirect('/alici-kayit', '/uye-kayit', 301);
    Route::post('/alici-kayit', [TenantAuthController::class, 'kayit'])
        ->middleware('throttle:10,1');

    Route::get('/firma-kodumu-bul', [TenantAuthController::class, 'firmaKoduBulFormu'])
        ->name('tenant.firma-kodu-bul.form');
    Route::post('/firma-kodumu-bul', [TenantAuthController::class, 'firmaKoduBul'])
        ->middleware('throttle:5,1')
        ->name('tenant.firma-kodu-bul');
});

Route::post('/cikis', [TenantAuthController::class, 'cikis'])
    ->middleware('auth')
    ->name('tenant.logout');

Route::post('/tercih/dil', [FrontTercihController::class, 'dilGuncelle'])
    ->middleware('throttle:60,1')
    ->name('front.preference.locale');
Route::post('/tercih/para-birimi', [FrontTercihController::class, 'paraBirimiGuncelle'])
    ->middleware('throttle:60,1')
    ->name('front.preference.currency');

Route::get('/restoran/qr-menu/{firmaKodu}', [RestoranQrMenuController::class, 'goster'])
    ->middleware('throttle:120,1')
    ->name('restoran.qr-menu');
Route::get('/restoran/qr-menu/{firmaKodu}/masalar/{masaQrKodu}', [RestoranQrMenuController::class, 'masaMenusu'])
    ->middleware('throttle:120,1')
    ->name('restoran.qr-menu.masa');
Route::post('/restoran/qr-menu/{firmaKodu}/masalar/{masaQrKodu}/siparis', [RestoranQrMenuController::class, 'siparisEkle'])
    ->middleware('throttle:60,1')
    ->name('restoran.qr-menu.siparis');
Route::get('/restoran/qr-menu/{firmaKodu}/masalar/{masaQrKodu}/adisyon', [RestoranQrMenuController::class, 'aktifAdisyon'])
    ->middleware('throttle:120,1')
    ->name('restoran.qr-menu.adisyon');
Route::delete('/restoran/qr-menu/{firmaKodu}/masalar/{masaQrKodu}/kalemler/{kalemId}', [RestoranQrMenuController::class, 'kalemIptalEt'])
    ->middleware('throttle:60,1')
    ->name('restoran.qr-menu.kalem-iptal');

// Bazı ortamlarda çıkış linki GET /{adminPath}/logout olarak tetiklenebiliyor.
// Bu fallback, kullanıcıyı 403 sayfasında bırakmadan güvenli şekilde login ekranına yönlendirir.
Route::get('/'.\App\Providers\Filament\AdminPanelProvider::adminPath().'/logout', function (\Illuminate\Http\Request $request) {
    \Illuminate\Support\Facades\Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->to(UygulamaUrl::rota('yonetici.login', [], request()));
})->name('filament.logout.fallback');

// Geriye donuk uyumluluk: masraf takibi muhasebe cluster'ından bağımsız modüle taşındı.
Route::middleware([
    \Filament\Http\Middleware\SetUpPanel::class.':admin',
    \App\Http\Middleware\GzipResponseMiddleware::class,
    \App\Http\Middleware\FilamentAuthenticate::class,
    \Filament\Http\Middleware\AuthenticateSession::class,
    \App\Http\Middleware\FilamentTenantContextMiddleware::class,
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
    \Filament\Http\Middleware\DisableBladeIconComponents::class,
    \Filament\Http\Middleware\DispatchServingFilamentEvent::class,
])
    ->get('/'.\App\Providers\Filament\AdminPanelProvider::adminPath().'/muhasebe/masraflar', function () {
        return redirect()->to(\App\Filament\Clusters\MasrafTakip\Pages\MasrafTakibiSayfasi::getUrl());
    })
    ->name('admin.legacy-muhasebe.masraflar');

// Geriye donuk urun/kategori URL'leri: Filament panelini baslatmadan kanonik Web
// sayfalarina aktarilir. Eski baglantilar korunur; asil sayfanin tasarimi ve icerigi degismez.
Route::redirect(
    '/'.\App\Providers\Filament\AdminPanelProvider::adminPath().'/products',
    '/'.\App\Providers\Filament\AdminPanelProvider::adminPath().'/web/urunler/urun-listesi',
    302
)->name('admin.legacy-web-products.products.index-redirect');
Route::redirect(
    '/'.\App\Providers\Filament\AdminPanelProvider::adminPath().'/products/create',
    '/'.\App\Providers\Filament\AdminPanelProvider::adminPath().'/web/urunler/urun-listesi/create',
    302
)->name('admin.legacy-web-products.products.create-redirect');
Route::redirect(
    '/'.\App\Providers\Filament\AdminPanelProvider::adminPath().'/product-categories',
    '/'.\App\Providers\Filament\AdminPanelProvider::adminPath().'/web/urunler/urun-kategorileri',
    302
)->name('admin.legacy-web-products.product-categories.index-redirect');
Route::redirect(
    '/'.\App\Providers\Filament\AdminPanelProvider::adminPath().'/product-categories/create',
    '/'.\App\Providers\Filament\AdminPanelProvider::adminPath().'/web/urunler/urun-kategorileri/create',
    302
)->name('admin.legacy-web-products.product-categories.create-redirect');

Route::prefix(\App\Providers\Filament\AdminPanelProvider::adminPath())
    ->name('admin.legacy-web-products.')
    ->group(function (): void {
        Route::get(
            '/products/{record}/edit',
            \App\Filament\Clusters\Web\Resources\UrunKaynagi\Pages\ListUrunler::class
        )
            ->middleware([
                \Filament\Http\Middleware\SetUpPanel::class.':admin',
                \App\Http\Middleware\GzipResponseMiddleware::class,
                \App\Http\Middleware\FilamentAuthenticate::class,
                \Filament\Http\Middleware\AuthenticateSession::class,
                \App\Http\Middleware\FilamentTenantContextMiddleware::class,
                \Illuminate\Routing\Middleware\SubstituteBindings::class,
                \Filament\Http\Middleware\DisableBladeIconComponents::class,
                \Filament\Http\Middleware\DispatchServingFilamentEvent::class,
            ])
            ->whereNumber('record')
            ->name('products.edit');

        Route::get(
            '/product-categories/{record}/edit',
            \App\Filament\Clusters\Web\Resources\UrunKategoriKaynagi\Pages\ListUrunKategorileri::class
        )
            ->middleware([
                \Filament\Http\Middleware\SetUpPanel::class.':admin',
                \App\Http\Middleware\GzipResponseMiddleware::class,
                \App\Http\Middleware\FilamentAuthenticate::class,
                \Filament\Http\Middleware\AuthenticateSession::class,
                \App\Http\Middleware\FilamentTenantContextMiddleware::class,
                \Illuminate\Routing\Middleware\SubstituteBindings::class,
                \Filament\Http\Middleware\DisableBladeIconComponents::class,
                \Filament\Http\Middleware\DispatchServingFilamentEvent::class,
            ])
            ->whereNumber('record')
            ->name('product-categories.edit');
    });

Route::middleware([
    \Filament\Http\Middleware\AuthenticateSession::class,
    \App\Http\Middleware\FilamentTenantContextMiddleware::class,
    \App\Http\Middleware\FilamentAuthenticate::class,
])
    ->prefix(\App\Providers\Filament\AdminPanelProvider::adminPath().'/teklif-yonetimi')
    ->name('admin.teklif-yonetimi.')
    ->group(function (): void {
        Route::get('/teklifler-pdf/{teklif}', [TeklifPdfController::class, 'teklifIndir'])
            ->name('teklifler.pdf');
        Route::get('/sablonlar-pdf/{sablon}', [TeklifPdfController::class, 'sablonIndir'])
            ->name('sablonlar.pdf');
        Route::get('/sablonlar-onizleme/{sablon}', [TeklifPdfController::class, 'sablonOnizlemeFrame'])
            ->name('sablonlar.preview-frame');
    });

Route::middleware('auth')->prefix('admin-tools/newsletter-subscribers')->group(function (): void {
    Route::get('/template', [NewsletterSubscriberExportController::class, 'template'])->name('newsletter-subscribers.template');
    Route::get('/export-csv', [NewsletterSubscriberExportController::class, 'csv'])->name('newsletter-subscribers.export-csv');
    Route::get('/export-excel', [NewsletterSubscriberExportController::class, 'excel'])->name('newsletter-subscribers.export-excel');
});

// SQL yedekleri yalnızca sistem yöneticilerine sunulur; dosya adı güvenli
// servis katmanında doğrulanır ve yedek dizini web kökünün dışındadır.
Route::middleware([
    \Filament\Http\Middleware\SetUpPanel::class.':admin',
    \App\Http\Middleware\FilamentAuthenticate::class,
    \Filament\Http\Middleware\AuthenticateSession::class,
    \App\Http\Middleware\FilamentTenantContextMiddleware::class,
])->get('/'.\App\Providers\Filament\AdminPanelProvider::adminPath().'/sistem-yedekleri/{yedek}/download', function (string $yedek, SistemYedekleriServisi $yedekServisi) {
    abort_unless(SistemYedekleriSayfasi::canAccess(), 403);

    return $yedekServisi->indir($yedek);
})->where('yedek', '[A-Za-z0-9._-]+')->name('admin.sistem-yedekleri.download');

Route::get('/', function () {
    $html = file_get_contents(public_path('themes/deep-original/index.htm'));
    $html = str_replace([
        '__CSRF_TOKEN__',
        '__TENANT_LOGIN_ACTION__',
        '__ADMIN_LOGIN_ACTION__',
        '__FIND_COMPANY_ACTION__',
    ], [
        csrf_token(),
        UygulamaUrl::rota('tenant.login.attempt', [], request()),
        UygulamaUrl::rota('yonetici.login.attempt', [], request()),
        UygulamaUrl::rota('tenant.firma-kodu-bul', [], request()),
    ], $html);

    $errorBag = session('errors');
    $errors = $errorBag instanceof \Illuminate\Support\ViewErrorBag
        ? $errorBag->getBag('default')->all()
        : [];
    $activeLoginPanel = session()->getOldInput('firma_kodu') !== null
        ? 'firma'
        : (session()->getOldInput('firma_adi') !== null ? 'kod' : 'yonetici');
    $loginState = json_encode([
        'active' => empty($errors) && ! session('status') ? 'firma' : $activeLoginPanel,
        'errors' => array_values($errors),
        'status' => session('status'),
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $html = str_replace('</body>', '<script>window.yyLoginFeedback = '.$loginState.';</script></body>', $html);

    return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
})->name('home');

Route::view('/hakkimizda', 'front.pages.about')->name('about');
Route::view('/teklif-formu', 'front.pages.teklif-formu')->name('offer.form');

// SERV力SLER
Route::get('/Servisler', function () {
    $servicesSurum = FrontIcerikCache::surum('services');
    $page = max(1, (int) request('page', 1));

    $services = Cache::remember('front.services.'.$servicesSurum.'.index.items.page.'.$page, 600, function () use ($page) {
        return Service::with('category:id,name,slug')
            ->where('is_active', true)
            ->select([
                'id',
                'service_category_id',
                'title',
                'slug',
                'short_description',
                'image',
                'sort_order',
                'is_active',
            ])
            ->orderBy('sort_order')
            ->paginate(18, ['*'], 'page', $page);
    });

    $categories = Cache::remember('front.services.'.$servicesSurum.'.categories', 600, function () {
        return ServiceCategory::where('is_active', true)
            ->select(['id', 'name', 'slug', 'sort_order', 'is_active'])
            ->orderBy('sort_order')
            ->get();
    });

    $category = null;

    return view('front.services.index', compact('services', 'categories', 'category'));
})->name('services.index');

Route::get('/Servisler/kategori/{categorySlug}', function ($categorySlug) {
    $servicesSurum = FrontIcerikCache::surum('services');
    $page = max(1, (int) request('page', 1));

    $category = Cache::remember("front.services.{$servicesSurum}.category.{$categorySlug}", 600, function () use ($categorySlug) {
        return ServiceCategory::where('slug', $categorySlug)
            ->where('is_active', true)
            ->firstOrFail();
    });

    $services = Cache::remember("front.services.{$servicesSurum}.category.items.{$category->id}.page.{$page}", 600, function () use ($category, $page) {
        return $category->services()
            ->with('category:id,name,slug')
            ->where('is_active', true)
            ->select([
                'id',
                'service_category_id',
                'title',
                'slug',
                'short_description',
                'image',
                'sort_order',
                'is_active',
            ])
            ->orderBy('sort_order')
            ->paginate(18, ['*'], 'page', $page);
    });

    $categories = Cache::remember('front.services.'.$servicesSurum.'.categories', 600, function () {
        return ServiceCategory::where('is_active', true)
            ->select(['id', 'name', 'slug', 'sort_order', 'is_active'])
            ->orderBy('sort_order')
            ->get();
    });

    return view('front.services.index', compact('services', 'categories', 'category'));
})->name('services.index.category');

Route::get('/Servisler/{slug}', function ($slug) {
    $servicesSurum = FrontIcerikCache::surum('services');

    $service = Cache::remember("front.services.{$servicesSurum}.show.{$slug}", 600, function () use ($slug) {
        return Service::with('category')
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();
    });

    return view('front.services.show', compact('service'));
})->name('services.show');

// PROJELER
Route::get('/Projeler', function () {
    $projectsSurum = FrontIcerikCache::surum('projects');

    $projects = Cache::remember('front.projects.'.$projectsSurum.'.index.items', 600, function () {
        return Project::with('category:id,name,slug')
            ->where('is_active', true)
            ->select([
                'id',
                'project_category_id',
                'title',
                'slug',
                'description',
                'image',
                'sort_order',
                'is_active',
            ])
            ->orderBy('sort_order')
            ->get();
    });

    $categories = Cache::remember('front.projects.'.$projectsSurum.'.categories', 600, function () {
        return ProjectCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    });

    $category = null;

    return view('front.projects.index', compact('projects', 'categories', 'category'));
})->name('projects.index');

Route::get('/Projeler/kategori/{categorySlug}', function ($categorySlug) {
    $projectsSurum = FrontIcerikCache::surum('projects');

    $category = Cache::remember("front.projects.{$projectsSurum}.category.{$categorySlug}", 600, function () use ($categorySlug) {
        return ProjectCategory::where('slug', $categorySlug)
            ->where('is_active', true)
            ->firstOrFail();
    });

    $projects = Cache::remember("front.projects.{$projectsSurum}.category.items.{$category->id}", 600, function () use ($category) {
        return $category->projects()
            ->with('category:id,name,slug')
            ->where('is_active', true)
            ->select([
                'id',
                'project_category_id',
                'title',
                'slug',
                'description',
                'image',
                'sort_order',
                'is_active',
            ])
            ->orderBy('sort_order')
            ->get();
    });

    $categories = Cache::remember('front.projects.'.$projectsSurum.'.categories', 600, function () {
        return ProjectCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    });

    return view('front.projects.index', compact('projects', 'categories', 'category'));
})->name('projects.index.category');

Route::get('/Projeler/{slug}', function ($slug) {
    $projectsSurum = FrontIcerikCache::surum('projects');

    $project = Cache::remember("front.projects.{$projectsSurum}.show.{$slug}", 600, function () use ($slug) {
        return Project::with('category')
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();
    });

    return view('front.projects.show', compact('project'));
})->name('projects.show');

Route::get('/WebProje', fn () => redirect()->to(UygulamaUrl::rota('projects.index', [], request()), 301));
Route::get('/WebProje/kategori/{categorySlug}', fn ($categorySlug) => redirect()->to(UygulamaUrl::rota('projects.index.category', ['categorySlug' => $categorySlug], request()), 301));
Route::get('/WebProje/{slug}', fn ($slug) => redirect()->to(UygulamaUrl::rota('projects.show', ['slug' => $slug], request()), 301));

// ÜRÜNLER
Route::get('/urunler', [UrunController::class, 'index'])->name('products.index');
Route::get('/kategori/{slug}', [UrunController::class, 'kategori'])->name('products.category');
Route::get('/urun/{slug}', [UrunController::class, 'show'])->name('products.show');
Route::middleware('ecommerce.front.erisim')->group(function (): void {
    Route::get('/sepet', [SepetController::class, 'index'])->name('cart.index');
    Route::post('/sepet/ekle/{slug}', [SepetController::class, 'ekle'])
        ->middleware('throttle:60,1')
        ->name('cart.add');
    Route::patch('/sepet/{kalemId}', [SepetController::class, 'guncelle'])->name('cart.update');
    Route::delete('/sepet/{kalemId}', [SepetController::class, 'sil'])->name('cart.remove');
    Route::post('/sepet/kupon', [SepetController::class, 'kuponUygula'])->name('cart.coupon');
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::get('/checkout/kargo-secenekleri', [CheckoutController::class, 'kargoSecenekleri'])
        ->middleware('throttle:60,1')
        ->name('checkout.shipping-options');
    Route::post('/checkout', [CheckoutController::class, 'store'])
        ->middleware('throttle:checkout-submit')
        ->name('checkout.store');
    Route::get('/siparis-basarili', [CheckoutController::class, 'success'])->name('checkout.success');
    Route::get('/siparis-takip', [SiparisTakipController::class, 'index'])->name('orders.track');
    Route::post('/siparis-takip', [SiparisTakipController::class, 'sorgula'])
        ->middleware('throttle:20,1')
        ->name('orders.track.search');
    Route::get('/odeme/{siparis}', [OdemeController::class, 'show'])->name('odeme.show');
    Route::post('/odeme/{siparis}/basarili', [OdemeController::class, 'basarili'])->name('odeme.basarili');
    Route::post('/odeme/{siparis}/basarisiz', [OdemeController::class, 'basarisiz'])->name('odeme.basarisiz');
    Route::post('/odeme/{siparis}/tekrar-dene', [OdemeController::class, 'tekrarDene'])
        ->middleware('throttle:odeme-retry')
        ->name('odeme.tekrar_dene');
});

Route::middleware(['auth', 'aktif.firma', 'ecommerce.front.erisim'])->prefix('hesabim')->group(function (): void {
    Route::get('/', [HesabimController::class, 'index'])->name('account.index');
    Route::get('/profil', [HesabimController::class, 'profil'])->name('account.profile');
    Route::post('/profil', [HesabimController::class, 'profilGuncelle'])->name('account.profile.update');
    Route::get('/adresler', [HesabimController::class, 'adresler'])->name('account.addresses');
    Route::post('/adresler', [HesabimController::class, 'adresKaydet'])->name('account.addresses.store');
    Route::delete('/adresler', [HesabimController::class, 'adresleriSil'])->name('account.addresses.bulk-destroy');
    Route::patch('/adresler/fatura', [HesabimController::class, 'faturaAdresiGuncelle'])->name('account.invoice-address.update');
    Route::patch('/adresler/{adres}/varsayilan-teslimat', [HesabimController::class, 'varsayilanTeslimatAdresiYap'])->name('account.addresses.default-delivery');
    Route::patch('/adresler/{adres}/fatura-yap', [HesabimController::class, 'adresiFaturaBilgisiYap'])->name('account.addresses.make-invoice');
    Route::patch('/adresler/{adres}', [HesabimController::class, 'adresGuncelle'])->name('account.addresses.update');
    Route::delete('/adresler/{adres}', [HesabimController::class, 'adresSil'])->name('account.addresses.destroy');
    Route::get('/siparisler', [HesabimController::class, 'siparisler'])->name('account.orders');
    Route::get('/siparisler/{siparis}', [HesabimController::class, 'siparisDetay'])->name('account.orders.show');
    Route::post('/siparisler/{siparis}/talep', [HesabimController::class, 'siparisTalep'])
        ->middleware('throttle:10,1')
        ->name('account.orders.request');
    Route::get('/mesajlar', [HesabimController::class, 'mesajlar'])->name('account.messages');
    Route::get('/mesajlar/yeni', [HesabimController::class, 'mesajYeniForm'])->name('account.messages.new');
    Route::post('/mesajlar', [HesabimController::class, 'mesajYeniKaydet'])->name('account.messages.store');
    Route::get('/mesajlar/{konu}', [HesabimController::class, 'mesajDetay'])->name('account.messages.show');
    Route::post('/mesajlar/{konu}', [HesabimController::class, 'mesajGonder'])->name('account.messages.reply');
});

// Hosting/cPanel ortamında cron scheduler çalışmasa bile ödeme süresi dolan siparişler web endpoint ile işlenebilir.
Route::get('/sistem/cron/odeme-zaman-asimi', [EcommerceCronFallbackController::class, 'odemeZamanAsimi'])
    ->middleware('throttle:20,1')
    ->name('ecommerce.cron.odeme-zaman-asimi');
Route::get('/sistem/health', SistemHealthController::class)
    ->middleware('throttle:30,1')
    ->name('sistem.health');

Route::post('/api/odeme/callback/{provider}', [OdemeWebhookDenetleyici::class, 'isle'])
    ->middleware('throttle:240,1')
    ->name('odeme.webhook.callback');

Route::post('/api/kargo/callback/{entegrasyon}', [KargoWebhookDenetleyici::class, 'isle'])
    ->middleware('throttle:240,1')
    ->name('kargo.webhook.callback');

// Legacy kategori rotasi: eski urun-kategori ile gelenler yeni kategoriye yonlendirilsin.
Route::get('/urun-kategori/{slug}', function (string $slug) {
    return redirect()->to(UygulamaUrl::rota('products.category', ['slug' => $slug], request()));
});

// BLOG
Route::get('/blog', function () {
    $blogSurum = FrontIcerikCache::surum('blog');
    $page = max(1, (int) request('page', 1));

    $posts = Cache::remember('front.blog.'.$blogSurum.'.index.items.page.'.$page, 600, function () use ($page) {
        return Post::with('category:id,name,slug')
            ->where('is_published', true)
            ->select([
                'id',
                'post_category_id',
                'title',
                'slug',
                'excerpt',
                'content',
                'image',
                'published_at',
                'is_published',
                'sort_order',
            ])
            ->orderByDesc('published_at')
            ->paginate(18, ['*'], 'page', $page);
    });

    $categories = Cache::remember('front.blog.'.$blogSurum.'.categories', 600, function () {
        return PostCategory::where('is_active', true)
            ->select(['id', 'name', 'slug', 'sort_order', 'is_active'])
            ->orderBy('sort_order')
            ->get();
    });

    $category = null;

    return view('front.blog.index', compact('posts', 'categories', 'category'));
})->name('blog.index');

Route::get('/blog/kategori/{categorySlug}', function ($categorySlug) {
    $blogSurum = FrontIcerikCache::surum('blog');
    $page = max(1, (int) request('page', 1));

    $category = Cache::remember("front.blog.{$blogSurum}.category.{$categorySlug}", 600, function () use ($categorySlug) {
        return PostCategory::where('slug', $categorySlug)
            ->where('is_active', true)
            ->firstOrFail();
    });

    $posts = Cache::remember("front.blog.{$blogSurum}.category.items.{$category->id}.page.{$page}", 600, function () use ($category, $page) {
        return $category->posts()
            ->with('category:id,name,slug')
            ->where('is_published', true)
            ->select([
                'id',
                'post_category_id',
                'title',
                'slug',
                'excerpt',
                'content',
                'image',
                'published_at',
                'is_published',
                'sort_order',
            ])
            ->orderByDesc('published_at')
            ->paginate(18, ['*'], 'page', $page);
    });

    $categories = Cache::remember('front.blog.'.$blogSurum.'.categories', 600, function () {
        return PostCategory::where('is_active', true)
            ->select(['id', 'name', 'slug', 'sort_order', 'is_active'])
            ->orderBy('sort_order')
            ->get();
    });

    return view('front.blog.index', compact('posts', 'categories', 'category'));
})->name('blog.index.category');

Route::get('/blog/{slug}', function ($slug) {
    $blogSurum = FrontIcerikCache::surum('blog');

    $post = Cache::remember("front.blog.{$blogSurum}.show.{$slug}", 600, function () use ($slug) {
        return Post::with('category')
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();
    });

    return view('front.blog.show', compact('post'));
})->name('blog.show');

Route::get('/iletisim', fn () => view('front.pages.contact'))->name('contact');
Route::post('/iletisim', [ContactController::class, 'store'])
    ->middleware('throttle:contact-submit')
    ->name('contact.store');
Route::post('/abonelik', [NewsletterSubscriberController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('newsletter.subscribe');

// Dinamik sayfalar
Route::get('/sayfa/{slug}', function ($slug) {
    $pageSurum = FrontIcerikCache::surum('pages');

    $page = Cache::remember("front.pages.{$pageSurum}.dynamic.{$slug}", 600, function () use ($slug) {
        return Page::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();
    });

    return view('front.pages.dynamic', compact('page'));
})->name('page.show');

// Bilgi sayfaları (Filament Bilgi Sayfalarından eklenenler)
Route::get('/bilgi', function () {
    $bilgiSurum = FrontIcerikCache::surum('information');

    $bilgiSayfalari = Cache::remember("front.information.{$bilgiSurum}.index", 600, function () {
        return BilgiSayfa::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->get();
    });

    return view('front.information.information-index', compact('bilgiSayfalari'));
})->name('information.index');

Route::get('/bilgi/{slug}', function ($slug) {
    $bilgiSurum = FrontIcerikCache::surum('information');

    $bilgiSayfa = Cache::remember("front.information.{$bilgiSurum}.show.{$slug}", 600, function () use ($slug) {
        return BilgiSayfa::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();
    });

    return view('front.information.information-show', compact('bilgiSayfa'));
})->name('information.show');

// Google SEO: Dinamik sitemap ve robots.txt (Filament’ten eklenen içerik otomatik dahil)
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

// Masraf belgelerini firma sınırı ve oturum kontrolüyle sun.
Route::middleware('auth')->get('/admin/masraf-takip/masraf/{masraf}/belge', function (int $masraf) {
    $firmaId = (int) (app(\App\Services\TenantContextService::class)->aktifFirmaId() ?? 0);
    $kayit = MasrafModel::query()
        ->where('firma_id', $firmaId)
        ->findOrFail($masraf);

    abort_if(! $kayit->belge_yolu, 404);

    $disk = Storage::disk('public');
    if (! $disk->exists($kayit->belge_yolu)) {
        $disk = Storage::disk('local');
    }

    abort_unless($disk->exists($kayit->belge_yolu), 404);
    $fullPath = $disk->path($kayit->belge_yolu);
    abort_unless(is_file($fullPath) && is_readable($fullPath), 404);

    return response()->file($fullPath, [
        'Content-Type' => File::mimeType($fullPath) ?: 'application/octet-stream',
        'Cache-Control' => 'private, no-store',
    ]);
})->name('masraf.belge');

// /storage/* dosyalarını sun (symlink yoksa veya document root public değilse)
// Not: Eğer sunucuda public/storage symlink'i varsa, web server dosyayı zaten direkt servis eder.
Route::get('/storage/{path}', function (string $path) {
    $path = str_replace(['..', '\\'], ['', '/'], $path);
    $path = ltrim($path, '/');

    $disk = Storage::disk('public');
    if (! $disk->exists($path)) {
        // Linux case-sensitive: DB'deki isim farklı case ile kaydedilmiş olabilir.
        $dir = trim(dirname($path), './\\');
        $dir = $dir === '' ? null : $dir;
        $base = basename($path);
        $files = $disk->files($dir);
        $matched = collect($files)->first(
            fn (string $f) => strcasecmp(basename($f), $base) === 0
        );
        if (! $matched) {
            abort(404);
        }
        $path = $matched;
    }

    $fullPath = $disk->path($path);
    if (! is_file($fullPath) || ! is_readable($fullPath)) {
        abort(404);
    }

    return response()->file($fullPath, [
        'Content-Type' => File::mimeType($fullPath) ?: 'application/octet-stream',
        'Cache-Control' => 'public, max-age=31536000, immutable',
    ]);
})->where('path', '.*')->name('storage.serve');

// /uploads/* dosyalarını sun (public disk URL'i)
Route::get('/uploads/{path}', function (string $path) {
    $path = str_replace(['..', '\\'], ['', '/'], $path);
    $path = ltrim($path, '/');

    $disk = Storage::disk('public');
    if (! $disk->exists($path)) {
        // Linux case-sensitive: DB'deki isim farklı case ile kaydedilmiş olabilir.
        $dir = trim(dirname($path), './\\');
        $dir = $dir === '' ? null : $dir;
        $base = basename($path);
        $files = $disk->files($dir);
        $matched = collect($files)->first(
            fn (string $f) => strcasecmp(basename($f), $base) === 0
        );
        if (! $matched) {
            abort(404);
        }
        $path = $matched;
    }

    $fullPath = $disk->path($path);
    if (! is_file($fullPath) || ! is_readable($fullPath)) {
        abort(404);
    }

    return response()->file($fullPath, [
        'Content-Type' => File::mimeType($fullPath) ?: 'application/octet-stream',
        'Cache-Control' => 'public, max-age=31536000, immutable',
    ]);
})->where('path', '.*')->name('uploads.serve');
