@extends('front.layouts.app', ['authShellPage' => true])

@section('content')
@php
    $recaptchaEtkin = \App\Support\RecaptchaAyarlari::etkinMi();
    $recaptchaSiteKey = \App\Support\RecaptchaAyarlari::siteKey();
    $girisVideoMp4 = is_file(public_path('theme/yalovakamera/images/hero-bg-video.mp4'))
        ? asset('theme/yalovakamera/images/hero-bg-video.mp4')
        : '';
    $girisVideoPoster = is_file(public_path('theme/yalovakamera/images/hero-image.png'))
        ? asset('theme/yalovakamera/images/hero-image.png')
        : '';
@endphp

<x-auth-shell
    title="Firma Girişi"
    eyebrow="Firma hesabı"
    heading="Firma Girişi"
    subtitle="Firma kodu, kullanıcı adı veya e-posta ve şifreniz ile giriş yapın."
    hero-title="Firma panelinize güvenli ve hızlı giriş."
    hero-text="Stok, satış, muhasebe ve teknik servis akışlarını tek panelden yönetmek için firma bilgilerinizi kullanarak oturum açın."
    footnote="Giriş yaparak yalnızca yetkili olduğunuz firma verilerine erişirsiniz. Sorun yaşarsanız firma yöneticinizle iletişime geçin."
    :recaptcha="$recaptchaEtkin"
    :recaptcha-site-key="$recaptchaSiteKey"
    :hero-video-mp4="$girisVideoMp4"
    :hero-video-poster="$girisVideoPoster"
    hero-video-mode="page"
    :embedded="true"
>
    <x-slot:quickLinks>
        <a href="{{ \App\Support\UygulamaUrl::rota('buyer.login', [], request()) }}">Üye girişi</a>
        <a href="{{ \App\Support\UygulamaUrl::rota('yonetici.login', [], request()) }}">Yönetici girişi</a>
        <a href="{{ \App\Support\UygulamaUrl::rota('tenant.firma-kodu-bul.form', [], request()) }}">Firma kodumu bul</a>
    </x-slot:quickLinks>

    <form id="firma-giris-formu" method="POST" action="{{ \App\Support\UygulamaUrl::rota('tenant.login.attempt', [], request()) }}" novalidate>
        @csrf

        @if (session('status'))
            <div class="notice info">{{ session('status') }}</div>
        @endif

        @if ($errors->has('panel') || $errors->has('firma'))
            <div class="notice error">
                {{ $errors->first('panel') ?: $errors->first('firma') }}
            </div>
        @endif

        <div class="field">
            <label for="firma_kodu">Firma Kodu</label>
            <input id="firma_kodu" type="text" name="firma_kodu" value="{{ old('firma_kodu') }}" autocomplete="organization" autocapitalize="characters" required autofocus>
            <div id="firma-kodu-hatirlandi" class="hint" hidden>Son kullandığınız firma kodu dolduruldu.</div>
            @error('firma_kodu') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div class="field">
            <label for="kullanici_adi_veya_eposta">Kullanıcı Adı veya E-posta</label>
            <input id="kullanici_adi_veya_eposta" type="text" name="kullanici_adi_veya_eposta" value="{{ old('kullanici_adi_veya_eposta') }}" autocomplete="username" required>
            @error('kullanici_adi_veya_eposta') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div class="field">
            <label for="sifre">Şifre</label>
            <div class="password-wrap">
                <input id="sifre" type="password" name="sifre" autocomplete="current-password" required>
                <button type="button" id="sifre-toggle" aria-controls="sifre" aria-pressed="false">Göster</button>
            </div>
            <div id="caps-warning" class="caps-warning">Caps Lock açık görünüyor.</div>
            @error('sifre') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div class="form-row">
            <label class="checkbox-label">
                <input id="beni_hatirla" type="checkbox" name="beni_hatirla" value="1" @checked(old('beni_hatirla'))>
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

        <button id="giris-submit" class="submit" type="submit" data-normal-label="Giriş Yap" data-loading-label="Giriş yapılıyor...">Giriş Yap</button>
    </form>

    <x-slot:scripts>
        <script>
            (() => {
                const form = document.getElementById('firma-giris-formu');
                const firmaKodu = document.getElementById('firma_kodu');
                const remember = document.getElementById('beni_hatirla');
                const rememberedHint = document.getElementById('firma-kodu-hatirlandi');
                const password = document.getElementById('sifre');
                const passwordToggle = document.getElementById('sifre-toggle');
                const capsWarning = document.getElementById('caps-warning');
                const submit = document.getElementById('giris-submit');
                const storageKey = 'yalova_kamera_firma_kodu';

                if (!form || !firmaKodu || !remember || !password || !passwordToggle || !submit) {
                    return;
                }

                try {
                    const savedCompanyCode = window.localStorage.getItem(storageKey);
                    if (savedCompanyCode && firmaKodu.value.trim() === '') {
                        firmaKodu.value = savedCompanyCode;
                        remember.checked = true;
                        if (rememberedHint) rememberedHint.hidden = false;
                        password.focus();
                    }
                } catch (error) {}

                passwordToggle.addEventListener('click', () => {
                    const visible = password.type === 'text';
                    password.type = visible ? 'password' : 'text';
                    passwordToggle.textContent = visible ? 'Göster' : 'Gizle';
                    passwordToggle.setAttribute('aria-pressed', visible ? 'false' : 'true');
                });

                const updateCapsWarning = (event) => {
                    if (!capsWarning || typeof event.getModifierState !== 'function') return;
                    capsWarning.classList.toggle('is-visible', event.getModifierState('CapsLock'));
                };

                password.addEventListener('keydown', updateCapsWarning);
                password.addEventListener('keyup', updateCapsWarning);
                password.addEventListener('blur', () => capsWarning?.classList.remove('is-visible'));

                form.addEventListener('submit', () => {
                    const code = firmaKodu.value.trim();
                    try {
                        if (remember.checked && code !== '') {
                            window.localStorage.setItem(storageKey, code);
                        } else {
                            window.localStorage.removeItem(storageKey);
                        }
                    } catch (error) {}

                    submit.disabled = true;
                    submit.textContent = submit.dataset.loadingLabel || 'Giriş yapılıyor...';
                });
            })();
        </script>
    </x-slot:scripts>
</x-auth-shell>
@endsection
