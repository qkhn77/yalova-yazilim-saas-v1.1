<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    @include('front.partials.head')
</head>
@php
    $frontTheme = app(\App\Services\FrontThemeService::class);
@endphp
<body class="{{ $frontTheme->bodyClass() }}">
@php
    $layoutSettings = \App\Models\Setting::getMany([
        'google_tag_manager_id' => '',
        'whatsapp_enabled' => true,
        'whatsapp_code' => '',
        'whatsapp_button_text' => 'Sohbet',
        'whatsapp_welcome_message' => 'Merhaba, bilgi almak istiyorum.',
        'call_button_enabled' => true,
        'call_button_phone' => '',
        'call_button_text' => 'Bizi Arayin',
        'footer_bottom_text' => '© ' . date('Y') . ' Yalova Kamera',
    ]);
    $gtmId = trim((string) $layoutSettings['google_tag_manager_id']);
    $whatsappEnabled = filter_var($layoutSettings['whatsapp_enabled'], FILTER_VALIDATE_BOOL);
    $floatingWhatsapp = preg_replace('/[^0-9]/', '', (string) $layoutSettings['whatsapp_code']);
    $floatingWhatsappText = trim((string) $layoutSettings['whatsapp_button_text']);
    $floatingWhatsappMessage = trim((string) $layoutSettings['whatsapp_welcome_message']);
    $floatingWhatsappHref = 'https://api.whatsapp.com/send?phone=' . $floatingWhatsapp;
    if ($floatingWhatsapp !== '' && $floatingWhatsappMessage !== '') {
        $floatingWhatsappHref .= '&text=' . rawurlencode($floatingWhatsappMessage);
    }

    $callButtonEnabled = filter_var($layoutSettings['call_button_enabled'], FILTER_VALIDATE_BOOL);
    $callButtonPhone = preg_replace('/[^0-9+]/', '', (string) $layoutSettings['call_button_phone']);
    $callButtonText = trim((string) $layoutSettings['call_button_text']);
    $loadInteractiveThemeAssets = request()->routeIs(
        'home',
        'about',
        'contact',
        'services.*',
        'projects.*',
        'blog.*',
        'information.*',
        'page.show'
    );
    $loadSliderAssets = request()->routeIs('home', 'about');
    $loadCounterAssets = request()->routeIs('home', 'about');
    $loadRevealAssets = request()->routeIs('home', 'about', 'information.show');
    $loadPopupAssets = request()->routeIs('home');
    $loadParallaxAssets = request()->routeIs('contact', 'services.show', 'projects.show', 'information.show');
    $loadIsotopeAssets = request()->routeIs('home');
    $loadFormValidationAssets = request()->routeIs('contact');
    $loadProductListAssets = request()->routeIs('products.index', 'products.category');
    $loadProductDetailAssets = request()->routeIs('products.show');
    $authShellPage = ($authShellPage ?? false)
        || request()->routeIs('tenant.login', 'yonetici.login', 'buyer.login', 'buyer.register', 'firma-kodu.*');
@endphp

@if($gtmId !== '')
    <noscript>
        <iframe
            src="https://www.googletagmanager.com/ns.html?id={{ $gtmId }}"
            height="0"
            width="0"
            style="display:none;visibility:hidden"
        ></iframe>
    </noscript>
@endif

@include('front.partials.header')

@yield('content')

@include('front.partials.footer')

@if(($callButtonEnabled && $callButtonPhone !== '') || ($whatsappEnabled && $floatingWhatsapp !== ''))
    <div class="floating-contact-stack" aria-label="Sabit iletisim butonlari">
        @if($callButtonEnabled && $callButtonPhone !== '')
            <a
                href="tel:{{ $callButtonPhone }}"
                aria-label="Bizi arayin"
                class="floating-contact-button floating-call-button"
            >
                <i class="fa-solid fa-phone" aria-hidden="true"></i>
                <span>{{ $callButtonText !== '' ? $callButtonText : 'Bizi Arayin' }}</span>
            </a>
        @endif

        @if($whatsappEnabled && $floatingWhatsapp !== '')
            <a
                href="{{ $floatingWhatsappHref }}"
                target="_blank"
                rel="noopener"
                aria-label="WhatsApp sohbet"
                class="floating-contact-button floating-whatsapp-button"
            >
                <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>
                <span>{{ $floatingWhatsappText !== '' ? $floatingWhatsappText : 'Sohbet' }}</span>
            </a>
        @endif
    </div>
@endif

<footer style="padding: 20px; background: #333; color: white; text-align: center;">
    <p>{{ $layoutSettings['footer_bottom_text'] }}</p>
</footer>

<script defer src="{{ asset('theme/yalovakamera/js/jquery-3.7.1.min.js') }}"></script>
<script defer src="{{ asset('theme/yalovakamera/js/bootstrap.min.js') }}"></script>
<script defer src="{{ asset('theme/yalovakamera/js/jquery.slicknav.js') }}"></script>
@if($loadSliderAssets)
    <script defer src="{{ asset('theme/yalovakamera/js/swiper-bundle.min.js') }}"></script>
@endif
@if($loadCounterAssets)
    <script defer src="{{ asset('theme/yalovakamera/js/jquery.waypoints.min.js') }}"></script>
    <script defer src="{{ asset('theme/yalovakamera/js/jquery.counterup.min.js') }}"></script>
@endif
@if($loadPopupAssets)
    <script defer src="{{ asset('theme/yalovakamera/js/jquery.magnific-popup.min.js') }}"></script>
@endif
@if($loadParallaxAssets)
    <script defer src="{{ asset('theme/yalovakamera/js/parallaxie.js') }}"></script>
@endif
@if($loadRevealAssets)
    <script defer src="{{ asset('theme/yalovakamera/js/gsap.min.js') }}"></script>
    <script defer src="{{ asset('theme/yalovakamera/js/ScrollTrigger.min.js') }}"></script>
@endif
@if($loadIsotopeAssets)
    <script defer src="{{ asset('theme/yalovakamera/js/isotope.min.js') }}"></script>
@endif
@if($loadInteractiveThemeAssets)
    <script defer src="{{ asset('theme/yalovakamera/js/wow.min.js') }}"></script>
@endif
@if($loadFormValidationAssets)
    <script defer src="{{ asset('theme/yalovakamera/js/validator.min.js') }}"></script>
@endif

@if(! $authShellPage)
    <script defer src="{{ $frontTheme->versionedAsset('theme/yalovakamera/js/function.js') }}"></script>
@endif
@if($frontTheme->active() !== \App\Services\FrontThemeService::DEFAULT_THEME && $frontTheme->jsPath())
    <script defer src="{{ $frontTheme->versionedAsset($frontTheme->jsPath()) }}"></script>
@endif
@if($loadProductListAssets)
    <script defer src="{{ $frontTheme->versionedAsset('theme/yalovakamera/js/products-list.js') }}"></script>
@endif
@if($loadProductDetailAssets)
    <script defer src="{{ $frontTheme->versionedAsset('theme/yalovakamera/js/product-detail.js') }}"></script>
@endif



@stack('scripts')

</body>
</html>
