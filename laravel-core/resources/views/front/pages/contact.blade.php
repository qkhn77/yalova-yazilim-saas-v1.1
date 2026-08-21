@php
    $contact = \App\Models\ContactPage::instance();
@endphp
@extends('front.layouts.app')

@section('title', $contact->meta_title ?: 'İletişim | Yalova Kamera Sistemleri')
@section('meta_description', $contact->meta_description ?: 'Yalova kamera kurulumu konusunda uzman ekibimiz.')
@section('meta_keywords', $contact->meta_keywords ?: 'yalova kamera kurulumu telefon, yalova güvenlik sistemi ara')

@php
    \App\Helpers\BreadcrumbHelper::clear();
    \App\Helpers\BreadcrumbHelper::add('Anasayfa', '/');
    \App\Helpers\BreadcrumbHelper::add('İletişim');
@endphp

@section('content')

    <div class="page-header parallaxie">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-12">
                    <div class="page-header-box">
                        <h1 class="wow fadeInUp">{{ $contact->header_heading ?: 'İletişim' }}</h1>
                        {!! \App\Helpers\BreadcrumbHelper::render() !!}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="page-contact-us">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-4">
                    <div class="contact-us-content">
                        <div class="section-title">
                            <h3 class="wow fadeInUp">{{ $contact->section_heading ?: 'İletişim' }}</h3>

                            <h2 class="wow fadeInUp" data-wow-delay="0.2s">
                                <span>{{ $contact->section_subheading ?: 'Güvenliğinizi bizimle sağlayın' }}</span>
                            </h2>

                            <p class="wow fadeInUp" data-wow-delay="0.4s">
                                {{ $contact->section_intro ?: 'Sorularınız mı var veya güvenlik sistemleri hakkında bilgi mi almak istiyorsunuz? Ekibimiz size yardımcı olmaktan memnuniyet duyar.' }}
                            </p>
                        </div>

                        <div class="contact-social-list wow fadeInUp" data-wow-delay="0.6s">
                            <ul>
                                @if (! empty($contact->instagram_url))
                                    <li><a href="{{ $contact->instagram_url }}" target="_blank" rel="noopener" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a></li>
                                @endif
                                @if (! empty($contact->facebook_url))
                                    <li><a href="{{ $contact->facebook_url }}" target="_blank" rel="noopener" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a></li>
                                @endif
                                @if (! empty($contact->linkedin_url))
                                    <li><a href="{{ $contact->linkedin_url }}" target="_blank" rel="noopener" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a></li>
                                @endif
                                @if (! empty($contact->pinterest_url))
                                    <li><a href="{{ $contact->pinterest_url }}" target="_blank" rel="noopener" aria-label="Pinterest"><i class="fa-brands fa-pinterest-p"></i></a></li>
                                @endif
                                @if (! empty($contact->twitter_url))
                                    <li><a href="{{ $contact->twitter_url }}" target="_blank" rel="noopener" aria-label="X"><i class="fa-brands fa-x-twitter"></i></a></li>
                                @endif
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="contact-info-list">
                        <div class="contact-info-item wow fadeInUp">
                            <div class="icon-box">
                                <img src="{{ asset('theme/yalovakamera/images/icon-phone.svg') }}">
                            </div>

                            <div class="contact-item-content">
                                <p>Telefon</p>

                                <h3>
                                    @if (! empty($contact->phone))
                                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $contact->phone) }}">{{ $contact->phone }}</a>
                                    @else
                                        -
                                    @endif
                                </h3>
                            </div>
                        </div>

                        <div class="contact-info-item wow fadeInUp" data-wow-delay="0.2s">
                            <div class="icon-box">
                                <img src="{{ asset('theme/yalovakamera/images/icon-mail.svg') }}">
                            </div>

                            <div class="contact-item-content">
                                <p>E-posta</p>

                                <h3>
                                    @if (! empty($contact->email))
                                        <a href="mailto:{{ $contact->email }}">{{ $contact->email }}</a>
                                    @else
                                        -
                                    @endif
                                </h3>
                            </div>
                        </div>

                        <div class="contact-info-item location-item wow fadeInUp" data-wow-delay="0.4s">
                            <div class="icon-box">
                                <img src="{{ asset('theme/yalovakamera/images/icon-location.svg') }}">
                            </div>

                            <div class="contact-item-content">
                                <p>Adres</p>
                                <h3>{{ $contact->address ?: '-' }}</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="contact-form-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="contact-us-box">
                        @if (! empty($contact->map_url))
                            <div class="google-map-iframe">
                                <iframe
                                    src="{{ $contact->map_url }}"
                                    loading="lazy">
                                </iframe>
                            </div>
                        @endif

                        <div class="contact-us-form">
                            <div class="section-title">
                                <h2>{{ $contact->form_heading ?: 'Mesaj Gönder' }}</h2>
                                <p>{{ $contact->form_intro ?: 'Güvenlik sistemleri hakkında bilgi almak için bize mesaj gönderebilirsiniz.' }}</p>
                            </div>

                            @if (session('success'))
                                <div class="alert alert-success mb-4">{{ session('success') }}</div>
                            @endif
                            @if (session('error'))
                                <div class="alert alert-danger mb-4">{{ session('error') }}</div>
                            @endif
                            @if ($errors->any())
                                <div class="alert alert-danger mb-4">
                                    <ul class="mb-0">
                                        @foreach ($errors->all() as $err)
                                            <li>{{ $err }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @php
                                $recaptchaEtkin = \App\Support\RecaptchaAyarlari::etkinMi();
                                $recaptchaSiteKey = \App\Support\RecaptchaAyarlari::siteKey();
                            @endphp

                            <form method="POST" action="{{ route('contact.store') }}" class="contact-form" id="contactForm">
                                @csrf

                                <div class="row">
                                    <div class="form-group col-md-6 mb-4">
                                        <input type="text" name="name" class="form-control" placeholder="Ad Soyad" value="{{ old('name') }}" required>
                                    </div>

                                    <div class="form-group col-md-6 mb-4">
                                        <input type="text" name="phone" class="form-control" placeholder="Telefon" value="{{ old('phone') }}">
                                    </div>

                                    <div class="form-group col-md-12 mb-4">
                                        <input type="email" name="email" class="form-control" placeholder="E-posta" value="{{ old('email') }}" required>
                                    </div>

                                    <div class="form-group col-md-12 mb-4">
                                        <textarea name="message" class="form-control" rows="3" placeholder="Mesajınız" required>{{ old('message') }}</textarea>
                                    </div>

                                    @if ($recaptchaEtkin)
                                        <div class="form-group col-md-12 mb-4">
                                            <div class="g-recaptcha" data-sitekey="{{ $recaptchaSiteKey }}"></div>
                                        </div>
                                    @endif

                                    <div class="col-md-12">
                                        <button type="submit" class="btn-default">
                                            Mesaj Gönder
                                        </button>
                                    </div>
                                </div>
                            </form>

                            @if ($recaptchaEtkin)
                                <script src="https://www.google.com/recaptcha/api.js" async defer></script>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
