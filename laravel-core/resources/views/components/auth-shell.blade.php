@props([
    'title',
    'eyebrow',
    'heading',
    'subtitle',
    'heroTitle' => 'Panelinize güvenli ve hızlı erişim.',
    'heroText' => 'Hesabınıza giriş yaparak işlemlerinize kaldığınız yerden devam edin.',
    'footnote' => 'Bilgileriniz güvenli oturum altyapısı ile korunur.',
    'tone' => 'blue',
    'recaptcha' => false,
    'recaptchaSiteKey' => '',
    'heroVideoMp4' => '',
    'heroVideoWebm' => '',
    'heroVideoPoster' => '',
    'heroVideoMode' => 'panel',
    'embedded' => false,
])

@if(! $embedded)
<!doctype html>
<html lang="{{ app()->getLocale() ?: 'tr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $title }}</title>
@endif
    <style>
        :root {
            color-scheme: light;
            --bg: #f3f6fb;
            --panel: #ffffff;
            --ink: #0f172a;
            --muted: #64748b;
            --line: #d8e0ec;
            --primary: {{ $tone === 'amber' ? '#b45309' : '#0f66e8' }};
            --primary-dark: {{ $tone === 'amber' ? '#92400e' : '#0b4fb6' }};
            --soft: {{ $tone === 'amber' ? '#fff7ed' : '#eaf2ff' }};
            --danger-bg: #fff1f2;
            --danger: #be123c;
            --success-bg: #ecfdf5;
            --success: #047857;
        }
    </style>
    @php
        $versionedAuthAsset = function (string $path): string {
            static $versions = [];

            $fullPath = public_path($path);
            $versions[$fullPath] ??= is_file($fullPath) ? (int) filemtime($fullPath) : time();

            return asset($path).'?v='.$versions[$fullPath];
        };
    @endphp
    <link rel="stylesheet" href="{{ $versionedAuthAsset('theme/yalovakamera/css/auth-shell.css') }}">
@if(! $embedded)
</head>
<body class="auth-shell-body">
@endif
@if($embedded)
    <div class="auth-theme-scope">
@endif
    <a class="skip-link" href="#auth-form">Forma geç</a>

    @php
        $ayarlar = \App\Models\Setting::getMany([
            'site_title' => 'Yalova Kamera',
            'site_logo' => '',
        ]);
        $siteBasligi = $ayarlar['site_title'] ?: 'Yalova Kamera';
        $siteLogo = trim((string) ($ayarlar['site_logo'] ?? ''));
        $siteLogoUrl = '';
        $siteKokUrl = rtrim(\App\Support\UygulamaUrl::uygulamaKoku(request()), '/') ?: url('/');
        $videoMp4 = trim((string) $heroVideoMp4);
        $videoWebm = trim((string) $heroVideoWebm);
        $videoPoster = trim((string) $heroVideoPoster);
        $videoVarMi = $videoMp4 !== '' || $videoWebm !== '';
        $videoTamSayfaMi = $videoVarMi && $heroVideoMode === 'page';
        $videoPaneldeMi = $videoVarMi && ! $videoTamSayfaMi;
        if ($siteLogo !== '') {
            if (str_starts_with($siteLogo, 'http://') || str_starts_with($siteLogo, 'https://')) {
                $siteLogoUrl = $siteLogo;
            } elseif (str_starts_with($siteLogo, '/')) {
                $siteLogoUrl = rtrim(\App\Support\UygulamaUrl::uygulamaKoku(request()), '/').$siteLogo;
            } else {
                $siteLogoUrl = asset('storage/'.ltrim($siteLogo, '/'));
            }
        }
    @endphp

    <main class="page {{ $videoTamSayfaMi ? 'has-page-video' : '' }}">
        @if($videoTamSayfaMi)
            <video class="page-video" autoplay muted loop playsinline preload="none" poster="{{ $videoPoster ?: $siteLogoUrl }}" data-auth-lazy-video>
                @if($videoWebm !== '')
                    <source data-src="{{ $videoWebm }}" type="video/webm">
                @endif
                @if($videoMp4 !== '')
                    <source data-src="{{ $videoMp4 }}" type="video/mp4">
                @endif
            </video>
        @endif

        <section class="brand-panel {{ $videoPaneldeMi ? 'has-video' : '' }}" aria-label="Uygulama bilgisi">
            @if($videoPaneldeMi)
                <video class="hero-video" autoplay muted loop playsinline preload="none" poster="{{ $videoPoster ?: $siteLogoUrl }}" data-auth-lazy-video>
                    @if($videoWebm !== '')
                        <source data-src="{{ $videoWebm }}" type="video/webm">
                    @endif
                    @if($videoMp4 !== '')
                        <source data-src="{{ $videoMp4 }}" type="video/mp4">
                    @endif
                </video>
            @endif

            <a class="brand" href="{{ $siteKokUrl }}" aria-label="{{ $siteBasligi }} ana sayfa">
                @if($siteLogoUrl !== '')
                    <img class="brand-logo" src="{{ $siteLogoUrl }}" alt="{{ $siteBasligi }}" loading="eager" decoding="async" fetchpriority="high">
                @else
                    <span class="brand-mark">YK</span>
                @endif
                <span class="brand-name">{{ $siteBasligi }}</span>
            </a>

            <div class="hero-copy">
                <h1>{{ $heroTitle }}</h1>
                <p>{{ $heroText }}</p>
            </div>

            <div class="feature-row" aria-label="Panel özellikleri">
                <div class="feature">
                    <strong>Hızlı erişim</strong>
                    <span>İşlemlerinize kaldığınız yerden devam edin.</span>
                </div>
                <div class="feature">
                    <strong>Güvenli oturum</strong>
                    <span>Yetki ve oturum kontrolleri arka planda korunur.</span>
                </div>
                <div class="feature">
                    <strong>Tek panel</strong>
                    <span>Satış, servis ve hesap işlemleriniz aynı deneyimde birleşir.</span>
                </div>
            </div>
        </section>

        <section class="login-wrap" aria-label="{{ $heading }}">
            <div class="card">
                <div class="card-head">
                    <p class="eyebrow">{{ $eyebrow }}</p>
                    <h2>{{ $heading }}</h2>
                    <p class="subtitle">{{ $subtitle }}</p>
                </div>

                @isset($quickLinks)
                    <nav class="quick-links" aria-label="Giriş bağlantıları">
                        {{ $quickLinks }}
                    </nav>
                @endisset

                <div id="auth-form">
                    {{ $slot }}
                </div>

                <div class="card-foot">
                    {{ $footnote }}
                </div>
            </div>
        </section>
    </main>
    <script defer src="{{ $versionedAuthAsset('theme/yalovakamera/js/auth-shell.js') }}"></script>
    {{ $scripts ?? '' }}

    @if ($recaptcha)
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    @endif
@if($embedded)
    </div>
@endif
@if(! $embedded)
</body>
</html>
@endif
