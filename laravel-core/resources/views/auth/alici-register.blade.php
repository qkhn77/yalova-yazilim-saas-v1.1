@extends('front.layouts.app')

@section('content')
@php
    $uyeGirisVideoMp4 = is_file(public_path('theme/yalovakamera/images/hero-bg-video.mp4'))
        ? asset('theme/yalovakamera/images/hero-bg-video.mp4')
        : '';
    $uyeGirisVideoPoster = is_file(public_path('theme/yalovakamera/images/hero-image.png'))
        ? asset('theme/yalovakamera/images/hero-image.png')
        : '';
@endphp

<x-auth-shell
    title="Üye Kaydı"
    eyebrow="Yeni üyelik"
    heading="Üye Kaydı"
    subtitle="Alışveriş hesabınızı oluşturmak için bilgilerinizi girin."
    hero-title="Sipariş takibi için hızlı bir üye hesabı oluşturun."
    hero-text="Kayıt sonrası siparişlerinizi, adreslerinizi ve destek mesajlarınızı tek yerden takip edebilirsiniz."
    footnote="Üye hesabı e-ticaret işlemleri içindir. Firma paneli erişimi için firma hesabı gerekir."
    :hero-video-mp4="$uyeGirisVideoMp4"
    :hero-video-poster="$uyeGirisVideoPoster"
    hero-video-mode="page"
    :embedded="true"
>
    <x-slot:quickLinks>
        <a href="{{ \App\Support\UygulamaUrl::rota('buyer.login', [], request()) }}">Üye girişi</a>
        <a href="{{ \App\Support\UygulamaUrl::rota('tenant.login', [], request()) }}">Firma girişi</a>
        <a href="{{ \App\Support\UygulamaUrl::rota('tenant.firma-kodu-bul.form', [], request()) }}">Firma kodu</a>
    </x-slot:quickLinks>

    <form method="POST" action="{{ \App\Support\UygulamaUrl::rota('buyer.register.attempt', [], request()) }}" data-auth-form novalidate>
        @csrf

        <div class="field">
            <label for="ad_soyad">Ad Soyad</label>
            <input id="ad_soyad" type="text" name="ad_soyad" value="{{ old('ad_soyad') }}" autocomplete="name" placeholder="Adınız Soyadınız" required autofocus>
            @error('ad_soyad') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div class="field">
            <label for="email">E-posta</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" placeholder="ornek@firma.com" required>
            @error('email') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div class="field">
            <label for="telefon">Telefon</label>
            <div class="phone-row">
                <select id="telefon_ulke_kodu" name="telefon_ulke_kodu" class="phone-code" required>
                    <option value="+90" @selected(old('telefon_ulke_kodu', '+90') === '+90')>+90</option>
                    <option value="+1" @selected(old('telefon_ulke_kodu') === '+1')>+1</option>
                    <option value="+49" @selected(old('telefon_ulke_kodu') === '+49')>+49</option>
                    <option value="+44" @selected(old('telefon_ulke_kodu') === '+44')>+44</option>
                    <option value="+31" @selected(old('telefon_ulke_kodu') === '+31')>+31</option>
                </select>
                <input id="telefon" type="tel" name="telefon" class="phone-input" inputmode="tel" maxlength="18" placeholder="(555) 000 00 00" value="{{ old('telefon') }}" autocomplete="tel" required>
            </div>
            @error('telefon') <div class="error">{{ $message }}</div> @enderror
            @error('telefon_ulke_kodu') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div class="field">
            <label for="sifre">Şifre</label>
            <div class="password-wrap">
                <input id="sifre" type="password" name="sifre" autocomplete="new-password" data-password-strength required>
                <button type="button" data-password-toggle="sifre" aria-controls="sifre" aria-pressed="false">Göster</button>
            </div>
            <div class="strength" data-strength-wrap>
                <div class="strength-bar" aria-hidden="true"><span data-strength-bar></span></div>
                <div class="strength-text" data-strength-text>Şifre gücü</div>
            </div>
            <div class="hint">En az 8 karakter; büyük/küçük harf ve rakam kullanmanız önerilir.</div>
            <div class="caps-warning">Caps Lock açık görünüyor.</div>
            @error('sifre') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div class="field">
            <label for="sifre_confirmation">Şifre Tekrar</label>
            <div class="password-wrap">
                <input id="sifre_confirmation" type="password" name="sifre_confirmation" autocomplete="new-password" required>
                <button type="button" data-password-toggle="sifre_confirmation" aria-controls="sifre_confirmation" aria-pressed="false">Göster</button>
            </div>
            <div class="caps-warning">Caps Lock açık görünüyor.</div>
        </div>

        <button class="submit" type="submit" data-submit-loading="Kayıt oluşturuluyor...">Kayıt Ol</button>
    </form>

    <x-slot:scripts>
        <script>
            (() => {
                const telefon = document.getElementById('telefon');
                const ulkeKodu = document.getElementById('telefon_ulke_kodu');
                if (!telefon || !ulkeKodu) return;

                const formatla = () => {
                    let rakamlar = (telefon.value || '').replace(/\D+/g, '');

                    if (ulkeKodu.value === '+90') {
                        if (rakamlar.startsWith('90') && rakamlar.length >= 12) rakamlar = rakamlar.slice(2);
                        if (rakamlar.startsWith('0')) rakamlar = rakamlar.slice(1);
                        rakamlar = rakamlar.slice(0, 10);

                        const a = rakamlar.slice(0, 3);
                        const b = rakamlar.slice(3, 6);
                        const c = rakamlar.slice(6, 8);
                        const d = rakamlar.slice(8, 10);

                        if (rakamlar.length <= 3) telefon.value = a ? `(${a}` : '';
                        else if (rakamlar.length <= 6) telefon.value = `(${a}) ${b}`;
                        else if (rakamlar.length <= 8) telefon.value = `(${a}) ${b} ${c}`;
                        else telefon.value = `(${a}) ${b} ${c} ${d}`;
                        return;
                    }

                    telefon.value = rakamlar.slice(0, 15);
                };

                telefon.addEventListener('input', formatla);
                ulkeKodu.addEventListener('change', formatla);
                formatla();
            })();
        </script>
    </x-slot:scripts>
</x-auth-shell>
@endsection
