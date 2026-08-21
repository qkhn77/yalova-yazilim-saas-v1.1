@extends('front.layouts.app')

@section('content')
@php
    $recaptchaEtkin = \App\Support\RecaptchaAyarlari::etkinMi();
    $recaptchaSiteKey = \App\Support\RecaptchaAyarlari::siteKey();
    $uyeGirisVideoMp4 = is_file(public_path('theme/yalovakamera/images/hero-bg-video.mp4'))
        ? asset('theme/yalovakamera/images/hero-bg-video.mp4')
        : '';
    $uyeGirisVideoPoster = is_file(public_path('theme/yalovakamera/images/hero-image.png'))
        ? asset('theme/yalovakamera/images/hero-image.png')
        : '';
@endphp

<x-auth-shell
    title="Üye Girişi"
    eyebrow="Üye hesabı"
    heading="Üye Girişi"
    subtitle="Alışveriş hesabınıza kullanıcı adı veya e-posta ve şifreniz ile giriş yapın."
    hero-title="Siparişlerinize ve hesap bilgilerinize hızlı erişin."
    hero-text="Üye panelinden siparişlerinizi, adreslerinizi ve mesajlarınızı güvenli şekilde yönetin."
    footnote="Üye girişi yalnızca alışveriş hesabı içindir. Firma personeli firma girişini kullanmalıdır."
    :recaptcha="$recaptchaEtkin"
    :recaptcha-site-key="$recaptchaSiteKey"
    :hero-video-mp4="$uyeGirisVideoMp4"
    :hero-video-poster="$uyeGirisVideoPoster"
    hero-video-mode="page"
    :embedded="true"
>
    <x-slot:quickLinks>
        <a href="{{ \App\Support\UygulamaUrl::rota('buyer.register', [], request()) }}">Üye kaydı</a>
        <a href="{{ \App\Support\UygulamaUrl::rota('tenant.login', [], request()) }}">Firma girişi</a>
        <a href="{{ \App\Support\UygulamaUrl::rota('yonetici.login', [], request()) }}">Yönetici girişi</a>
    </x-slot:quickLinks>

    <form method="POST" action="{{ \App\Support\UygulamaUrl::rota('buyer.login.attempt', [], request()) }}" data-auth-form data-remember-key="yalova_kamera_uye_giris" novalidate>
        @csrf

        @if (session('status'))
            <div class="notice info">{{ session('status') }}</div>
        @endif

        @if ($errors->has('firma'))
            <div class="notice error">{{ $errors->first('firma') }}</div>
        @endif

        <div class="field">
            <label for="kullanici_adi_veya_eposta">Kullanıcı Adı veya E-posta</label>
            <input id="kullanici_adi_veya_eposta" type="text" name="kullanici_adi_veya_eposta" value="{{ old('kullanici_adi_veya_eposta') }}" autocomplete="username" placeholder="ornek@firma.com" data-remember-source data-remember-key="yalova_kamera_uye_giris" required autofocus>
            <div class="field-meta">
                <span class="hint">Son kullanılan hesap bu cihazda hatırlanır.</span>
                <button class="inline-action" type="button" data-clear-remember="yalova_kamera_uye_giris">Hatırlananı temizle</button>
            </div>
            @error('kullanici_adi_veya_eposta') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div class="field">
            <label for="sifre">Şifre</label>
            <div class="password-wrap">
                <input id="sifre" type="password" name="sifre" autocomplete="current-password" required>
                <button type="button" data-password-toggle="sifre" aria-controls="sifre" aria-pressed="false">Göster</button>
            </div>
            <div class="caps-warning">Caps Lock açık görünüyor.</div>
            @error('sifre') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div class="form-row">
            <label class="checkbox-label">
                <input type="checkbox" name="beni_hatirla" value="1" @checked(old('beni_hatirla'))>
                <span>Beni hatırla</span>
            </label>
            <span class="security-note">Ortak cihazlarda işaretlemeyin.</span>
        </div>

        @if ($recaptchaEtkin)
            <div class="recaptcha-area">
                <div class="g-recaptcha" data-sitekey="{{ $recaptchaSiteKey }}"></div>
                @error('g-recaptcha-response') <div class="error">{{ $message }}</div> @enderror
            </div>
        @endif

        <button class="submit" type="submit" data-submit-loading="Giriş yapılıyor...">Giriş Yap</button>
    </form>
</x-auth-shell>
@endsection
