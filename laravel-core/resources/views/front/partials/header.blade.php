@php
    $menuItems = app(\App\Services\Menu\MenuService::class)->getMenuTree('primary');
    if ($menuItems->isEmpty()) {
        $menuItems = collect([
            (object) ['label' => __('front.menu.home'), 'href' => route('home'), 'target' => '_self', 'should_use_noopener' => false, 'css_class' => '', 'children' => collect()],
            (object) ['label' => __('front.menu.about'), 'href' => route('about'), 'target' => '_self', 'should_use_noopener' => false, 'css_class' => '', 'children' => collect()],
            (object) ['label' => __('front.menu.products'), 'href' => route('products.index'), 'target' => '_self', 'should_use_noopener' => false, 'css_class' => '', 'children' => collect()],
            (object) ['label' => __('front.menu.services'), 'href' => route('services.index'), 'target' => '_self', 'should_use_noopener' => false, 'css_class' => '', 'children' => collect()],
            (object) ['label' => __('front.menu.projects'), 'href' => route('projects.index'), 'target' => '_self', 'should_use_noopener' => false, 'css_class' => '', 'children' => collect()],
            (object) ['label' => __('front.menu.blogs'), 'href' => route('blog.index'), 'target' => '_self', 'should_use_noopener' => false, 'css_class' => '', 'children' => collect()],
            (object) ['label' => __('front.menu.contact'), 'href' => route('contact'), 'target' => '_self', 'should_use_noopener' => false, 'css_class' => '', 'children' => collect()],
            (object) ['label' => __('front.menu.phone'), 'href' => 'tel:+902263520724', 'target' => '_self', 'should_use_noopener' => false, 'css_class' => '', 'children' => collect()],
        ]);
    }
    $headerSettings = \App\Models\Setting::getMany([
        'menu_bg_color' => '',
        'menu_text_color' => '',
        'menu_hover_bg' => '',
        'menu_hover_text' => '',
        'menu_active_bg' => '',
        'menu_active_text' => '',
        'phone' => '0 (226) 352 07 24',
        'utility_menu_enabled' => true,
        'utility_menu_show_language' => true,
        'utility_menu_show_currency' => true,
        'utility_menu_show_search' => true,
        'utility_menu_show_campaign' => true,
        'utility_menu_show_account_links' => true,
        'utility_menu_show_cart' => true,
        'utility_menu_show_customer_service' => true,
        'utility_menu_campaign_text' => '',
        'ust_kampanya_duyurusu' => __('front.utility.campaign_default'),
        'utility_menu_customer_service_label' => '',
        'musteri_hizmetleri_etiket' => __('front.utility.customer_services'),
        'utility_menu_search_placeholder' => __('front.utility.search_placeholder'),
        'site_logo' => '',
        'site_title' => config('app.name'),
    ]);
    $menuBg = $headerSettings['menu_bg_color'];
    $menuText = $headerSettings['menu_text_color'];
    $menuHoverBg = $headerSettings['menu_hover_bg'];
    $menuHoverText = $headerSettings['menu_hover_text'];
    $menuActiveBg = $headerSettings['menu_active_bg'];
    $menuActiveText = $headerSettings['menu_active_text'];
    $currentUrl = rtrim(request()->url(), '/');
    $ecommerceKuralServisi = app(\App\Services\EcommerceModulKuralServisi::class);
    $ecommerceFirmaId = $ecommerceKuralServisi->firmaIdBelirle(request());
    $ecommerceAktifMi = $ecommerceKuralServisi->erisimAcikMi($ecommerceFirmaId);
    $telefonNo = trim((string) $headerSettings['phone']);
    $telefonHref = preg_replace('/[^0-9+]/', '', $telefonNo);
    $utilityMenuAktif = filter_var($headerSettings['utility_menu_enabled'], FILTER_VALIDATE_BOOL);
    $utilityDilGorunsun = filter_var($headerSettings['utility_menu_show_language'], FILTER_VALIDATE_BOOL);
    $utilityParaBirimiGorunsun = filter_var($headerSettings['utility_menu_show_currency'], FILTER_VALIDATE_BOOL);
    $utilityAramaGorunsun = filter_var($headerSettings['utility_menu_show_search'], FILTER_VALIDATE_BOOL);
    $utilityKampanyaGorunsun = filter_var($headerSettings['utility_menu_show_campaign'], FILTER_VALIDATE_BOOL);
    $utilityHesapBaglantilariGorunsun = filter_var($headerSettings['utility_menu_show_account_links'], FILTER_VALIDATE_BOOL);
    $utilitySepetGorunsun = filter_var($headerSettings['utility_menu_show_cart'], FILTER_VALIDATE_BOOL);
    $utilityMusteriHizmetleriGorunsun = filter_var($headerSettings['utility_menu_show_customer_service'], FILTER_VALIDATE_BOOL);
    $kampanyaDuyurusu = trim((string) ($headerSettings['utility_menu_campaign_text'] ?: $headerSettings['ust_kampanya_duyurusu']));
    $destekMetni = trim((string) ($headerSettings['utility_menu_customer_service_label'] ?: $headerSettings['musteri_hizmetleri_etiket']));
    $utilityAramaPlaceholder = trim((string) $headerSettings['utility_menu_search_placeholder']);
    $frontTercihServisi = app(\App\Services\Front\FrontTercihServisi::class);
    $desteklenenDiller = $frontTercihServisi->desteklenenDiller();
    $desteklenenParaBirimleri = $frontTercihServisi->desteklenenParaBirimleri();
    $aktifDil = $frontTercihServisi->aktifDil();
    $aktifParaBirimi = $frontTercihServisi->aktifParaBirimi();
    $frontFiyatServisi = app(\App\Services\Front\FrontFiyatServisi::class);
    $utilitySepet = null;
    $utilityRecaptchaEtkin = \App\Support\RecaptchaAyarlari::etkinMi();
    $utilityRecaptchaSiteKey = \App\Support\RecaptchaAyarlari::siteKey();
    $utilityLoginPanelAcik = (bool) old('mini_login', false);
    $sepetUrunAdedi = (int) session('aktif_sepet_urun_adedi', 0);
    if ($ecommerceAktifMi && $utilitySepetGorunsun) {
        $miniSepetYuklemeGerekli = $sepetUrunAdedi > 0
            || (int) session('aktif_sepet_id', 0) > 0
            || auth()->check()
            || request()->routeIs('cart.*', 'checkout.*', 'odeme.*', 'orders.track');
        $miniSepetWith = [
            'kalemler' => function ($query) {
                $query->select([
                    'id',
                    'sepet_id',
                    'stok_karti_id',
                    'urun_adi_snapshot',
                    'para_birimi',
                    'kdv_orani',
                    'miktar',
                    'satir_toplami',
                ])->latest('id');
            },
            'kalemler.stokKarti' => function ($query) {
                $query->select([
                    'id',
                    'slug',
                    'og_gorsel',
                ]);
            },
        ];

        if ($miniSepetYuklemeGerekli && $sepetUrunAdedi <= 0) {
            $aktifSepetId = (int) session('aktif_sepet_id', 0);
            if ($aktifSepetId > 0) {
                $utilitySepet = \App\Models\Ecommerce\Sepet::query()
                    ->select(['id', 'kullanici_id'])
                    ->with($miniSepetWith)
                    ->find($aktifSepetId);
                $sepetUrunAdedi = $utilitySepet
                    ? (int) round((float) $utilitySepet->kalemler->sum('miktar'))
                    : 0;
                session(['aktif_sepet_urun_adedi' => $sepetUrunAdedi]);
            } elseif (auth()->check()) {
                $utilitySepet = \App\Models\Ecommerce\Sepet::query()
                    ->select(['id', 'kullanici_id'])
                    ->with($miniSepetWith)
                    ->where('kullanici_id', (int) auth()->id())
                    ->latest('id')
                    ->first();
                if ($utilitySepet) {
                    $sepetUrunAdedi = (int) round((float) $utilitySepet->kalemler->sum('miktar'));
                    session(['aktif_sepet_urun_adedi' => $sepetUrunAdedi]);
                }
            }
        } elseif ($miniSepetYuklemeGerekli) {
            $aktifSepetId = (int) session('aktif_sepet_id', 0);
            if ($aktifSepetId > 0) {
                $utilitySepet = \App\Models\Ecommerce\Sepet::query()
                    ->select(['id', 'kullanici_id'])
                    ->with($miniSepetWith)
                    ->find($aktifSepetId);
            } elseif (auth()->check()) {
                $utilitySepet = \App\Models\Ecommerce\Sepet::query()
                    ->select(['id', 'kullanici_id'])
                    ->with($miniSepetWith)
                    ->where('kullanici_id', (int) auth()->id())
                    ->latest('id')
                    ->first();
            }
        }
    }
    $utilitySepetKalemleri = $utilitySepet?->kalemler ?? collect();
    $utilitySepetGosterilecekKalemler = $utilitySepetKalemleri->take(10);
    $utilitySepetFazlaUrunSayisi = max(0, $utilitySepetKalemleri->count() - $utilitySepetGosterilecekKalemler->count());
    $utilitySepetAraToplam = $utilitySepetKalemleri->sum(function ($kalem) use ($frontFiyatServisi) {
        $paraBirimi = strtoupper((string) ($kalem->para_birimi ?: 'TRY'));
        $kdvOrani = (float) ($kalem->kdv_orani ?? 0);
        $sonSatirToplami = round((float) $kalem->satir_toplami * (1 + ($kdvOrani / 100)), 2);

        return $frontFiyatServisi->cevir($sonSatirToplami, $paraBirimi);
    });
    $sepetAksiyonPanelAcik = (bool) session('cart_recently_added', false) && $sepetUrunAdedi > 0;
    $sepetToastMesaji = session('cart_recently_added') ? 'Ürün sepete eklendi.' : '';
    $utilityIstenenEtiketler = collect([
        __('front.menu.home'),
        __('front.menu.about'),
        __('front.menu.products'),
        __('front.menu.services'),
        __('front.menu.projects'),
        __('front.menu.blogs'),
        __('front.menu.contact'),
    ])->map(fn ($etiket) => mb_strtolower(trim((string) $etiket)))->all();
    $utilityMerkezLinkleri = collect($menuItems)
        ->filter(function ($item) use ($utilityIstenenEtiketler) {
            $etiket = mb_strtolower(trim((string) ($item->label ?? '')));

            return in_array($etiket, $utilityIstenenEtiketler, true);
        })
        ->values();
    foreach (array_keys($desteklenenParaBirimleri) as $paraBirimiKodu) {
        $frontFiyatServisi->cevrilebilirMi((string) $paraBirimiKodu, $aktifParaBirimi);
    }
    $kurUyariMesajlari = $frontFiyatServisi->eksikKurMesajlari();
@endphp
@if ($menuBg || $menuText || $menuHoverText || $menuActiveText)
<style>
    .main-header .navbar { @if($menuBg) background-color: {{ $menuBg }} !important; @endif }
    .main-header .navbar .nav-link { @if($menuText) color: {{ $menuText }} !important; @endif }
    @if($menuHoverBg) .main-header .navbar .nav-link:hover { background-color: {{ $menuHoverBg }} !important; } @endif
    @if($menuHoverText) .main-header .navbar .nav-link:hover { color: {{ $menuHoverText }} !important; } @endif
    @if($menuActiveBg) .main-header .navbar .nav-item.active .nav-link { background-color: {{ $menuActiveBg }} !important; } @endif
    @if($menuActiveText) .main-header .navbar .nav-item.active .nav-link { color: {{ $menuActiveText }} !important; } @endif
    @if($menuHoverText) .main-header .navbar .dropdown-menu .dropdown-item:hover { color: {{ $menuHoverText }}; } @endif
</style>
@endif
@if (! empty($kurUyariMesajlari))
<div class="currency-rate-warning">
    <div class="container">
        <strong>Kur bilgisi eksik:</strong>
        {{ implode(' ', $kurUyariMesajlari) }}
        Fiyat dönüşümleri doğru çalışmayabilir; lütfen yönetici panelinden güncel döviz kurunu ekleyin.
    </div>
</div>
@endif
@if ($utilityMenuAktif)
<div class="utility-bar">
    <div class="container utility-bar-inner">
        <div class="utility-left">
            @if($utilityDilGorunsun)
            <label class="d-none" for="utilityLang">{{ __('front.utility.language') }}</label>
            <select id="utilityLang" class="utility-select" aria-label="{{ __('front.utility.language_select') }}">
                @foreach($desteklenenDiller as $dilKod => $dilEtiket)
                    <option value="{{ $dilKod }}" @selected($aktifDil === $dilKod)>{{ $dilEtiket }}</option>
                @endforeach
            </select>
            @endif
            @if($utilityParaBirimiGorunsun)
            <label class="d-none" for="utilityCurrency">{{ __('front.utility.currency') }}</label>
            <select id="utilityCurrency" class="utility-select" aria-label="{{ __('front.utility.currency_select') }}">
                @foreach($desteklenenParaBirimleri as $paraBirimiKod => $paraBirimiEtiket)
                    <option value="{{ $paraBirimiKod }}" @selected($aktifParaBirimi === $paraBirimiKod)>{{ $paraBirimiEtiket }}</option>
                @endforeach
            </select>
            @endif
            @if($utilityAramaGorunsun)
            <form action="{{ route('products.index') }}" method="GET" class="utility-search" role="search" aria-label="{{ __('front.utility.search_aria') }}">
                <input
                    type="search"
                    name="arama"
                    value="{{ (string) request('arama', '') }}"
                    class="utility-search-input"
                    placeholder="{{ $utilityAramaPlaceholder !== '' ? $utilityAramaPlaceholder : __('front.utility.search_placeholder') }}"
                    aria-label="{{ __('front.utility.search_aria') }}"
                >
            </form>
            @endif
        </div>

        @if($utilityKampanyaGorunsun && $kampanyaDuyurusu !== '')
        <div class="utility-center" aria-label="{{ __('front.utility.campaign_aria') }}">
            <div class="utility-marquee">
                <span class="utility-campaign">{{ $kampanyaDuyurusu }}</span>
                <span class="utility-campaign">{{ $kampanyaDuyurusu }}</span>
            </div>
            @if($utilityMerkezLinkleri->isNotEmpty())
            <div class="utility-center-links" aria-label="Hızlı menü">
                @foreach($utilityMerkezLinkleri as $utilityLinkItem)
                    <a
                        href="{{ $utilityLinkItem->href }}"
                        target="{{ $utilityLinkItem->target }}"
                        class="utility-center-link"
                        @if($utilityLinkItem->should_use_noopener) rel="noopener noreferrer" @endif
                    >
                        {{ $utilityLinkItem->label }}
                    </a>
                @endforeach
            </div>
            @endif
        </div>
        @else
        <div class="utility-center" aria-hidden="true">
            @if($utilityMerkezLinkleri->isNotEmpty())
            <div class="utility-center-links" aria-label="Hızlı menü">
                @foreach($utilityMerkezLinkleri as $utilityLinkItem)
                    <a
                        href="{{ $utilityLinkItem->href }}"
                        target="{{ $utilityLinkItem->target }}"
                        class="utility-center-link"
                        @if($utilityLinkItem->should_use_noopener) rel="noopener noreferrer" @endif
                    >
                        {{ $utilityLinkItem->label }}
                    </a>
                @endforeach
            </div>
            @endif
        </div>
        @endif

        <div class="utility-right">
            @if($utilityHesapBaglantilariGorunsun)
            @auth
                <a href="{{ route('account.index') }}" class="utility-link">{{ __('front.utility.account') }}</a>
                <span class="utility-divider">|</span>
                <form action="{{ route('tenant.logout') }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="utility-link utility-link-btn">{{ __('front.utility.logout') }}</button>
                </form>
            @else
                <div class="utility-login-menu {{ $utilityLoginPanelAcik ? 'is-open' : '' }}">
                    <a href="{{ route('buyer.login') }}" class="utility-link">{{ __('front.utility.login') }}</a>
                    <div class="utility-login-menu-panel">
                        <div class="utility-login-title">Üye Girişi</div>
                        <form action="{{ route('buyer.login.attempt') }}" method="POST" class="d-grid gap-2">
                            @csrf
                            <input type="hidden" name="mini_login" value="1">
                            <div class="utility-login-field">
                                <label for="utility-login-kimlik">E-posta veya kullanıcı adı</label>
                                <input
                                    id="utility-login-kimlik"
                                    type="text"
                                    name="kullanici_adi_veya_eposta"
                                    value="{{ old('mini_login') ? old('kullanici_adi_veya_eposta') : '' }}"
                                    class="utility-login-input"
                                    autocomplete="username"
                                    required
                                >
                                @if(old('mini_login') && $errors->has('kullanici_adi_veya_eposta'))
                                    <div class="utility-login-error">{{ $errors->first('kullanici_adi_veya_eposta') }}</div>
                                @endif
                            </div>
                            <div class="utility-login-field">
                                <label for="utility-login-sifre">Şifre</label>
                                <input
                                    id="utility-login-sifre"
                                    type="password"
                                    name="sifre"
                                    class="utility-login-input"
                                    autocomplete="current-password"
                                    required
                                >
                                @if(old('mini_login') && $errors->has('sifre'))
                                    <div class="utility-login-error">{{ $errors->first('sifre') }}</div>
                                @endif
                            </div>
                            @if($utilityRecaptchaEtkin)
                                <div class="utility-login-recaptcha">
                                    <div class="g-recaptcha" data-sitekey="{{ $utilityRecaptchaSiteKey }}"></div>
                                </div>
                                @if(old('mini_login') && $errors->has('g-recaptcha-response'))
                                    <div class="utility-login-error">{{ $errors->first('g-recaptcha-response') }}</div>
                                @endif
                            @endif
                            <label class="utility-login-check">
                                <input type="checkbox" name="beni_hatirla" value="1" @checked(old('mini_login') && old('beni_hatirla'))>
                                <span>Beni hatırla</span>
                            </label>
                            <button type="submit" class="utility-login-submit">Giriş Yap</button>
                        </form>
                    </div>
                </div>
                <span class="utility-divider">|</span>
                <a href="{{ route('buyer.register') }}" class="utility-link">{{ __('front.utility.register') }}</a>
            @endauth
            @endif
            @if($ecommerceAktifMi && $utilitySepetGorunsun)
                <span class="utility-divider">|</span>
                <div class="utility-cart-menu {{ $sepetAksiyonPanelAcik ? 'is-open' : '' }}">
                    <a href="{{ route('cart.index') }}" class="utility-link utility-link-with-badge">
                        <span>{{ __('front.utility.cart') }}</span>
                        @if($sepetUrunAdedi > 0)
                            <span class="utility-cart-badge">{{ $sepetUrunAdedi }}</span>
                        @endif
                    </a>
                    @if($sepetUrunAdedi > 0)
                        <div class="utility-cart-menu-panel">
                            <div class="utility-cart-summary" data-mini-cart-summary>
                                <div class="utility-cart-summary-title">Sepet Özeti</div>
                                <div class="utility-cart-items" data-mini-cart-items>
                                    @foreach($utilitySepetGosterilecekKalemler as $miniKalem)
                                        @php
                                            $miniStok = $miniKalem->stokKarti;
                                            $miniGorselYolu = $miniStok?->og_gorsel;
                                            $miniGorselUrl = $miniGorselYolu
                                                ? asset('uploads/' . ltrim(str_replace('\\', '/', $miniGorselYolu), '/'))
                                                : asset('theme/yalovakamera/images/yalova_kamera.png');
                                            $miniParaBirimi = strtoupper((string) ($miniKalem->para_birimi ?: 'TRY'));
                                            $miniAdet = (float) $miniKalem->miktar;
                                            $miniKdvOrani = (float) ($miniKalem->kdv_orani ?? 0);
                                            $miniSonSatirToplami = round((float) $miniKalem->satir_toplami * (1 + ($miniKdvOrani / 100)), 2);
                                            $miniUrunUrl = $miniStok?->slug ? route('products.show', $miniStok->slug) : route('cart.index');
                                        @endphp
                                        <div class="utility-cart-item">
                                            <a href="{{ $miniUrunUrl }}" class="utility-cart-item-image-link" aria-label="{{ $miniKalem->urun_adi_snapshot }}">
                                                <img src="{{ $miniGorselUrl }}" alt="{{ $miniKalem->urun_adi_snapshot }}" class="utility-cart-item-image" loading="lazy" decoding="async">
                                            </a>
                                            <div>
                                                <a href="{{ $miniUrunUrl }}" class="utility-cart-item-name">{{ $miniKalem->urun_adi_snapshot }}</a>
                                                <div class="utility-cart-item-meta">
                                                    <span>{{ rtrim(rtrim(number_format($miniAdet, 2, ',', '.'), '0'), ',') }} adet</span>
                                                    <strong>{{ $frontFiyatServisi->cevirVeFormatla($miniSonSatirToplami, $miniParaBirimi) }}</strong>
                                                </div>
                                                <div class="utility-cart-item-controls">
                                                    <form action="{{ route('cart.update', $miniKalem->id) }}" method="POST" class="utility-cart-qty-form">
                                                        @csrf
                                                        @method('PATCH')
                                                        <span class="utility-cart-qty-control">
                                                            <input type="number" name="miktar" value="{{ rtrim(rtrim(number_format($miniAdet, 2, '.', ''), '0'), '.') }}" min="1" step="1" class="utility-cart-qty-input" aria-label="Adet">
                                                            <button type="button" class="utility-cart-qty-step utility-cart-qty-step-up" data-qty-step="up" aria-label="Adeti artır">+</button>
                                                            <button type="button" class="utility-cart-qty-step utility-cart-qty-step-down" data-qty-step="down" aria-label="Adeti azalt">-</button>
                                                        </span>
                                                        <button type="submit" class="utility-cart-mini-btn">Güncelle</button>
                                                    </form>
                                                    <form action="{{ route('cart.remove', $miniKalem->id) }}" method="POST" class="utility-cart-remove-form">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="utility-cart-mini-btn utility-cart-mini-btn-danger">Kaldır</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                @if($utilitySepetFazlaUrunSayisi > 0)
                                    <div class="utility-cart-more" data-mini-cart-more>+{{ $utilitySepetFazlaUrunSayisi }} ürün daha</div>
                                @else
                                    <div class="utility-cart-more d-none" data-mini-cart-more></div>
                                @endif
                                <div class="utility-cart-total">
                                    <span>Toplam</span>
                                    <strong data-mini-cart-subtotal>{{ $frontFiyatServisi->formatla((float) $utilitySepetAraToplam) }}</strong>
                                </div>
                                <div class="utility-cart-actions">
                                    <a href="{{ route('cart.index') }}" class="utility-cart-action utility-cart-action-secondary">{{ __('front.utility.go_to_cart') }}</a>
                                    <a href="{{ route('checkout.index') }}" class="utility-cart-action utility-cart-action-primary">{{ __('front.utility.buy_now') }}</a>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            @endif
            @if($utilityMusteriHizmetleriGorunsun && $destekMetni !== '')
                <span class="utility-divider utility-support-divider">|</span>
                <a href="{{ route('contact') }}#musteri-hizmetleri" class="utility-link utility-support-link">{{ $destekMetni }}</a>
            @endif
        </div>
    </div>
</div>
@endif

@if($utilityMenuAktif && $utilityRecaptchaEtkin && ! auth()->check())
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
@endif

<div class="cart-toast {{ $sepetToastMesaji !== '' ? 'is-visible' : '' }}" data-cart-toast>
    <span class="cart-toast-icon">✓</span>
    <span class="cart-toast-text" data-cart-toast-text>{{ $sepetToastMesaji !== '' ? $sepetToastMesaji : 'Ürün sepete eklendi.' }}</span>
</div>

<script>
    window.YKHeaderUtilityConfig = {
        csrfToken: @json(csrf_token()),
        localeUrl: @json(route('front.preference.locale')),
        currencyUrl: @json(route('front.preference.currency')),
        labels: {
            cartAdded: @json('Ürün sepete eklendi.'),
            goToCart: @json(__('front.utility.go_to_cart')),
            buyNow: @json(__('front.utility.buy_now')),
            preferenceSaveFailed: @json(__('front.utility.preference_save_failed')),
        },
    };
</script>
<script defer src="{{ $frontTheme->versionedAsset('theme/yalovakamera/js/header-utility.js') }}"></script>

<header class="main-header">
    <div class="header-sticky">
        <nav class="navbar navbar-expand-lg">
            <div class="container">
                @php
                    $headerLogo = $headerSettings['site_logo'];
                    $headerLogoUrl = $headerLogo
                        ? (str_starts_with($headerLogo, 'settings/') ? asset('uploads/' . ltrim($headerLogo, '/')) : asset($headerLogo))
                        : asset('theme/yalovakamera/images/yalova_kamera.png');
                @endphp
                <a class="navbar-brand site-logo" href="{{ route('home') }}">
                    @if($frontTheme->is('software') && ! $headerLogo)
                        <span class="software-brand-mark" aria-hidden="true">&lt;/&gt;</span>
                        <span class="software-brand-copy"><strong>{{ $headerSettings['site_title'] }}</strong><small>technology systems</small></span>
                    @else
                        <img src="{{ $headerLogoUrl }}" alt="{{ $headerSettings['site_title'] }} Logo">
                    @endif
                </a>

                <div class="collapse navbar-collapse main-menu">
                    <div class="nav-menu-wrapper">
                        <ul class="navbar-nav mr-auto" id="menu">
                            @forelse($menuItems as $item)
                                @php
                                    $href = $item->href;
                                    $isActive = $currentUrl === rtrim($href, '/') || request()->fullUrlIs(rtrim($href, '/') . '*');
                                    $hasChildren = $item->children->isNotEmpty();
                                @endphp

                                @if($hasChildren)
                                    <li class="nav-item submenu {{ $isActive ? 'active' : '' }} {{ $item->css_class }}">
                                        <a class="nav-link" href="{{ $href }}" target="{{ $item->target }}" @if($item->should_use_noopener) rel="noopener noreferrer" @endif>
                                            {{ $item->label }}
                                        </a>
                                        <ul>
                                            @foreach($item->children as $child)
                                                @php
                                                    $childHref = $child->href;
                                                    $childActive = $currentUrl === rtrim($childHref, '/');
                                                @endphp
                                                <li class="nav-item {{ $childActive ? 'active' : '' }} {{ $child->css_class }}">
                                                    <a class="nav-link" href="{{ $childHref }}" target="{{ $child->target }}" @if($child->should_use_noopener) rel="noopener noreferrer" @endif>
                                                        {{ $child->label }}
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </li>
                                @else
                                    <li class="nav-item {{ $isActive ? 'active' : '' }} {{ $item->css_class }}">
                                        <a class="nav-link" href="{{ $href }}" target="{{ $item->target }}" @if($item->should_use_noopener) rel="noopener noreferrer" @endif>
                                            {{ $item->label }}
                                        </a>
                                    </li>
                                @endif
                            @empty
                                <li class="nav-item {{ request()->routeIs('home') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('home') }}">{{ __('front.menu.home') }}</a>
                                </li>
                                <li class="nav-item {{ request()->routeIs('about') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('about') }}">{{ __('front.menu.about') }}</a>
                                </li>
                                <li class="nav-item {{ request()->routeIs('services*') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('services.index') }}">{{ __('front.menu.services') }}</a>
                                </li>
                                <li class="nav-item {{ request()->routeIs('projects*') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('projects.index') }}">{{ __('front.menu.projects') }}</a>
                                </li>
                                <li class="nav-item {{ request()->routeIs('blog*') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('blog.index') }}">{{ __('front.menu.blogs') }}</a>
                                </li>
                                <li class="nav-item {{ request()->routeIs('contact') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('contact') }}">{{ __('front.menu.contact') }}</a>
                                </li>
                            @endforelse
                        </ul>
                    </div>

                    <div class="header-actions">
                        <a href="tel:{{ $telefonHref !== '' ? $telefonHref : '+902263520724' }}" class="btn-default header-call-btn">
                            <span class="header-call-btn-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" role="img" focusable="false">
                                    <path d="M6.62 10.79a15.05 15.05 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.02-.24c1.12.37 2.33.56 3.57.56a1 1 0 0 1 1 1V20a1 1 0 0 1-1 1C10.85 21 3 13.15 3 3a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.24.19 2.45.56 3.57a1 1 0 0 1-.24 1.02l-2.2 2.2Z"/>
                                </svg>
                            </span>
                            <span class="header-call-btn-copy">
                                <span class="header-call-btn-label">Hemen Ara</span>
                                <span class="header-call-btn-number">{{ $telefonNo !== '' ? $telefonNo : '0 (226) 352 07 24' }}</span>
                            </span>
                        </a>
                    </div>
                </div>

                <div class="navbar-toggle"></div>
            </div>
        </nav>

        <div class="responsive-menu"></div>
    </div>
</header>
