<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1">

@php
    $frontTheme = app(\App\Services\FrontThemeService::class);
    $settings = \App\Models\Setting::getMany([
        'google_search_console' => '',
        'google_analytics_code' => '',
        'google_tag_manager_id' => '',
        'meta_pixel_code' => '',
        'site_title' => config('app.name'),
        'meta_description' => 'Yalova\'da profesyonel kamera kurulumu, güvenlik kamerası ve alarm sistemleri için keşif, kurulum, servis ve bakım hizmetleri.',
        'meta_keywords' => 'yalova kamera kurulumu, güvenlik kamerası, alarm sistemi, cctv',
        'site_author' => 'Yalova Kamera Sistemleri',
        'default_meta_robots' => 'index, follow',
        'default_og_image' => '',
        'favicon_logo' => '',
        'homepage_meta_title' => '',
        'homepage_meta_description' => '',
        'homepage_meta_keywords' => '',
        'homepage_og_title' => '',
        'homepage_og_description' => '',
        'homepage_og_image' => '',
    ]);
    $contact = \App\Models\ContactPage::instance();
    $gsc = $settings['google_search_console'];
    $gaCode = $settings['google_analytics_code'];
    $gtmId = trim((string) $settings['google_tag_manager_id']);
    $metaPixelCode = $settings['meta_pixel_code'];

    $assetFromSetting = function (?string $path, string $fallback): string {
        if (is_string($path) && $path !== '') {
            return str_starts_with($path, 'settings/')
                ? asset('uploads/' . ltrim($path, '/'))
                : asset($path);
        }

        return asset($fallback);
    };

    $siteTitle = $settings['site_title'] ?: 'Yalova Kamera Sistemleri';
    $siteDescription = $settings['meta_description'] ?: 'Yalova\'da profesyonel kamera kurulumu, güvenlik kamerası ve alarm sistemleri için keşif, kurulum, servis ve bakım hizmetleri.';
    $siteKeywords = $settings['meta_keywords'] ?: 'yalova kamera kurulumu, güvenlik kamerası, alarm sistemi, cctv';
    $siteAuthor = $settings['site_author'] ?: 'Yalova Kamera Sistemleri';
    $defaultRobots = $settings['default_meta_robots'] ?: 'index, follow';
    $defaultOgImage = $assetFromSetting($settings['default_og_image'], 'theme/yalovakamera/images/yalova_kamera.png');
    $faviconFallback = $frontTheme->faviconPath() ?: 'theme/yalovakamera/images/favicon.png';
    $faviconLogoUrl = $assetFromSetting($settings['favicon_logo'], $faviconFallback);

    $defaultOgTitle = $siteTitle;
    $defaultOgDescription = $siteDescription;
    $aktifLocale = app()->getLocale();
    $localeSeoMap = [
        'tr' => ['meta' => 'tr-TR', 'og' => 'tr_TR'],
        'en' => ['meta' => 'en-US', 'og' => 'en_US'],
        'de' => ['meta' => 'de-DE', 'og' => 'de_DE'],
    ];
    $localeSeo = $localeSeoMap[$aktifLocale] ?? $localeSeoMap['tr'];
    $isHomePage = request()->routeIs('home');
    $loadSliderCss = request()->routeIs('home', 'about');
    $loadPopupCss = request()->routeIs('home');
    $loadProductListCss = request()->routeIs('products.index', 'products.category');
    $loadProductDetailCss = request()->routeIs('products.show');
    $canonicalBaseUrl = rtrim((string) config('app.url', url('/')), '/');
    $canonicalBaseUrl = $canonicalBaseUrl !== '' ? $canonicalBaseUrl : rtrim(url('/'), '/');
    $canonicalHomeUrl = $canonicalBaseUrl.'/';
    $canonicalizeUrl = function (?string $url = null) use ($canonicalBaseUrl, $canonicalHomeUrl): string {
        $url = trim((string) ($url ?: url()->current()));

        if ($url === '' || $url === '/') {
            return $canonicalHomeUrl;
        }

        $parts = parse_url($url);
        $path = isset($parts['path']) ? '/'.ltrim((string) $parts['path'], '/') : '/';
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';

        return $path === '/'
            ? $canonicalHomeUrl.$query
            : $canonicalBaseUrl.$path.$query;
    };
    $pageCanonicalUrl = $isHomePage
        ? $canonicalHomeUrl
        : $canonicalizeUrl($__env->yieldContent('canonical_url', url()->current()));
    $homeTitle = $settings['homepage_meta_title'] ?: $siteTitle;
    $homeDescription = $settings['homepage_meta_description'] ?: $siteDescription;
    $homeKeywords = $settings['homepage_meta_keywords'] ?: $siteKeywords;
    $homeOgTitle = $settings['homepage_og_title'] ?: $homeTitle;
    $homeOgDescription = $settings['homepage_og_description'] ?: $homeDescription;
    $homeOgImage = $assetFromSetting($settings['homepage_og_image'] ?: $settings['default_og_image'], 'theme/yalovakamera/images/yalova_kamera.png');

    $sameAs = array_values(array_filter([
        $contact->instagram_url,
        $contact->facebook_url,
        $contact->linkedin_url,
        $contact->pinterest_url,
        $contact->twitter_url,
    ]));

    $localBusinessSchema = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'LocalBusiness',
        'name' => $siteTitle,
        'url' => $canonicalHomeUrl,
        'image' => $defaultOgImage,
        'telephone' => $contact->phone ?: null,
        'address' => $contact->address ?: null,
        'areaServed' => 'Yalova',
        'sameAs' => $sameAs !== [] ? $sameAs : null,
    ], fn ($value) => $value !== null && $value !== '');

@endphp

@if (! empty($gsc))
    <meta name="google-site-verification" content="{{ $gsc }}">
@endif

@if (! empty($gaCode))
    {!! $gaCode !!}
@elseif ($gaId = config('services.google.analytics_id'))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', '{{ $gaId }}', { 'anonymize_ip': true });
    </script>
@endif

@if (! empty($gtmId))
    <script>
      (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
      new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
      j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
      'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
      })(window,document,'script','dataLayer','{{ $gtmId }}');
    </script>
@endif

@if (! empty($metaPixelCode))
    {!! $metaPixelCode !!}
@endif

<title>{{ $isHomePage ? $homeTitle : $__env->yieldContent('title', $siteTitle) }}</title>
<meta name="description" content="{{ $isHomePage ? $homeDescription : $__env->yieldContent('meta_description', $siteDescription) }}">
<meta name="keywords" content="{{ $isHomePage ? $homeKeywords : $__env->yieldContent('meta_keywords', $siteKeywords) }}">
<meta name="robots" content="{{ $isHomePage ? $defaultRobots : $__env->yieldContent('meta_robots', $defaultRobots) }}">
<meta name="author" content="{{ $siteAuthor }}">
<meta name="language" content="{{ $localeSeo['meta'] }}">
<meta name="geo.region" content="TR-77">
<meta name="geo.placename" content="Yalova">

<meta property="og:type" content="{{ $isHomePage ? 'website' : $__env->yieldContent('og_type', 'website') }}">
<meta property="og:url" content="{{ $pageCanonicalUrl }}">
<meta property="og:title" content="{{ $isHomePage ? $homeOgTitle : $__env->yieldContent('og_title', $defaultOgTitle) }}">
<meta property="og:description" content="{{ $isHomePage ? $homeOgDescription : $__env->yieldContent('og_description', $defaultOgDescription) }}">
<meta property="og:image" content="{{ $isHomePage ? $homeOgImage : $__env->yieldContent('og_image', $defaultOgImage) }}">
<meta property="og:site_name" content="{{ $siteTitle }}">
<meta property="og:locale" content="{{ $localeSeo['og'] }}">

<meta property="twitter:card" content="summary_large_image">
<meta property="twitter:url" content="{{ $pageCanonicalUrl }}">
<meta property="twitter:title" content="{{ $isHomePage ? $homeOgTitle : $__env->yieldContent('og_title', $defaultOgTitle) }}">
<meta property="twitter:description" content="{{ $isHomePage ? $homeOgDescription : $__env->yieldContent('og_description', $defaultOgDescription) }}">
<meta property="twitter:image" content="{{ $isHomePage ? $homeOgImage : $__env->yieldContent('og_image', $defaultOgImage) }}">

<link rel="canonical" href="{{ $pageCanonicalUrl }}">
<link rel="shortcut icon" type="image/x-icon" href="{{ $faviconLogoUrl }}">

<script type="application/ld+json">{!! json_encode($localBusinessSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@stack('head_meta')

<link rel="preconnect" href="https://fonts.googleapis.com/">
<link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin>

<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700;800;900&display=swap" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"></noscript>

<link href="{{ asset('theme/yalovakamera/css/bootstrap.min.css') }}" rel="stylesheet" media="screen">
<link href="{{ asset('theme/yalovakamera/css/slicknav.min.css') }}" rel="stylesheet">
@if($loadSliderCss)
    <link href="{{ asset('theme/yalovakamera/css/swiper-bundle.min.css') }}" rel="stylesheet">
@endif
<link href="{{ asset('theme/yalovakamera/css/all.min.css') }}" rel="stylesheet" media="screen">
<link href="{{ asset('theme/yalovakamera/css/animate.css') }}" rel="stylesheet">
@if($loadPopupCss)
    <link href="{{ asset('theme/yalovakamera/css/magnific-popup.css') }}" rel="stylesheet">
@endif
<link href="{{ asset('theme/yalovakamera/css/mousecursor.css') }}" rel="stylesheet">
<link href="{{ $frontTheme->versionedAsset('theme/yalovakamera/css/custom.css') }}" rel="stylesheet" media="screen">
<link href="{{ $frontTheme->versionedAsset('theme/yalovakamera/css/header-utility.css') }}" rel="stylesheet" media="screen">
@if($loadProductListCss)
    <link href="{{ $frontTheme->versionedAsset('theme/yalovakamera/css/products-list.css') }}" rel="stylesheet" media="screen">
@endif
@if($loadProductDetailCss)
    <link href="{{ $frontTheme->versionedAsset('theme/yalovakamera/css/product-detail.css') }}" rel="stylesheet" media="screen">
@endif
@if($frontTheme->active() !== \App\Services\FrontThemeService::DEFAULT_THEME && $frontTheme->cssPath())
    <link href="{{ $frontTheme->versionedAsset($frontTheme->cssPath()) }}" rel="stylesheet" media="screen">
@endif
