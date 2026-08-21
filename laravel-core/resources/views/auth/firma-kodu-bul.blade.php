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
    title="Firma Kodumu Bul"
    eyebrow="Firma bilgisi"
    heading="Firma Kodumu Bul"
    subtitle="Firma adınızı yazarak girişte kullanacağınız firma kodunu öğrenin."
    hero-title="Firma kodunuzu hızlıca bulun."
    hero-text="Firma girişinde kullanılan kodu hatırlamıyorsanız firma adınızla güvenli arama yapabilirsiniz."
    footnote="Güvenlik için yalnızca eşleşen aktif firma kodları listelenir."
    :recaptcha="$recaptchaEtkin"
    :recaptcha-site-key="$recaptchaSiteKey"
    :hero-video-mp4="$uyeGirisVideoMp4"
    :hero-video-poster="$uyeGirisVideoPoster"
    hero-video-mode="page"
    :embedded="true"
>
    <x-slot:quickLinks>
        <a href="{{ \App\Support\UygulamaUrl::rota('tenant.login', [], request()) }}">Firma girişi</a>
        <a href="{{ \App\Support\UygulamaUrl::rota('buyer.login', [], request()) }}">Üye girişi</a>
        <a href="{{ \App\Support\UygulamaUrl::rota('yonetici.login', [], request()) }}">Yönetici girişi</a>
    </x-slot:quickLinks>

    <form method="POST" action="{{ \App\Support\UygulamaUrl::rota('tenant.firma-kodu-bul', [], request()) }}" data-auth-form novalidate>
        @csrf

        @if (session('bulunan_firma_kodlari'))
            <div class="notice success">
                <strong>Eşleşen firma kodları:</strong>
                <div class="result-list">
                    @foreach(session('bulunan_firma_kodlari', []) as $firma)
                        <div class="result-item">
                            <span>{{ $firma['ad'] }} - <strong>{{ $firma['firma_kodu'] }}</strong></span>
                            <button class="copy-code" type="button" data-copy-text="{{ $firma['firma_kodu'] }}">Kopyala</button>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if (session('status'))
            <div class="notice info">{{ session('status') }}</div>
        @endif

        <div class="field">
            <label for="firma_adi">Firma Adı</label>
            <input id="firma_adi" type="text" name="firma_adi" value="{{ old('firma_adi') }}" autocomplete="organization" placeholder="Firma adınız" required autofocus>
            <div class="hint">Firma adının tamamını bilmiyorsanız ayırt edici birkaç kelime yazabilirsiniz.</div>
            @error('firma_adi') <div class="error">{{ $message }}</div> @enderror
        </div>

        @if ($recaptchaEtkin)
            <div class="recaptcha-area">
                <div class="g-recaptcha" data-sitekey="{{ $recaptchaSiteKey }}"></div>
                @error('g-recaptcha-response') <div class="error">{{ $message }}</div> @enderror
            </div>
        @endif

        <button class="submit" type="submit" data-submit-loading="Kod aranıyor...">Kodu Bul</button>
    </form>
</x-auth-shell>
@endsection
