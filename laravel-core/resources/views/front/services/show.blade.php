@extends('front.layouts.app')

@section('title', $service->title . ' | ' . __('front.listing.services'))
@section('meta_description', $service->short_description)
@section('meta_keywords', $service->meta_keywords ?: trim(($service->category?->name ? $service->category->name . ', ' : '') . $service->title . ', yalova kamera kurulumu, güvenlik kamerası servisi, alarm sistemi servisi', ', '))

@section('content')
    <style>
        .service-cta-call::before,
        .service-cta-call::after,
        .service-cta-map::before,
        .service-cta-map::after {
            display: none !important;
        }

        .service-cta-buttons {
            align-items: center;
        }

        .service-cta-buttons .service-cta-btn {
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            width: auto !important;
            min-width: 0 !important;
            padding: 15px 24px !important;
            white-space: nowrap;
        }

        .service-cta-buttons .service-cta-call,
        .service-cta-buttons .service-cta-call:hover {
            background: #16a34a !important;
            border-color: #16a34a !important;
            color: #ffffff !important;
        }

        .service-cta-buttons .service-cta-call:hover {
            background: #15803d !important;
            border-color: #15803d !important;
        }
    </style>

    @php
        $companyName = 'Yalova Bilgisayar Ve Kamera Sistemleri';
        $companyPhone = '0226 352 07 24';
        $companyPhoneHref = '+902263520724';
        $companyEmail = 'info@yalovabilgisayar.com';
        $companyWebsite = 'yalovakamera.com';
        $companyAddress = 'Sahil Mah. Yalı Cad. No:3/A';
        $mapsUrl = 'https://www.google.com/maps?sca_esv=55e9f3c856495c1e&hl=tr&authuser=1&sxsrf=ANbL-n61DSsPVRPYZModXClzwG_qT-nwMw:1776269207212&kgmid=/g/11f3qxlkqv&shndl=30&kgs=0f06814bf374434a&um=1&ie=UTF-8&fb=1&gl=tr&sa=X&geocode=KY0iZYOX5MoUMSds0GDyWXEZ&daddr=Sahil,+Yal%C4%B1+Cd.+3A,+77600+%C3%87iftlikk%C3%B6y/Yalova';

        $emailSubject = rawurlencode($service->title . ' - Bilgi / Teklif');
    @endphp

    <div class="page-header parallaxie">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-12">
                    <div class="page-header-box">
                        <h1 class="wow fadeInUp" data-cursor="-opaque">{{ $service->title }}</h1>
                        <nav class="wow fadeInUp" data-wow-delay="0.2s">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('front.listing.home') }}</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('services.index') }}">{{ __('front.listing.services') }}</a></li>
                                @if($service->category)
                                    <li class="breadcrumb-item"><a href="{{ route('services.index.category', $service->category->slug) }}">{{ $service->category->name }}</a></li>
                                @endif
                                <li class="breadcrumb-item active" aria-current="page">{{ $service->title }}</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container py-5">
        @if($service->category)
            <p><a href="{{ route('services.index.category', $service->category->slug) }}" class="badge bg-secondary text-decoration-none">{{ $service->category->name }}</a></p>
        @endif
        @if($service->image_url)
            <figure class="mb-4">
                <img src="{{ $service->image_url }}" alt="{{ $service->title }}" class="img-fluid rounded" width="1200" height="675" loading="eager" decoding="async" fetchpriority="high">
            </figure>
        @endif
        @if($service->short_description)
            <p class="lead">{{ $service->short_description }}</p>
        @endif
        @if($service->content)
            <div class="content mb-4">{!! $service->content !!}</div>
        @endif

        <div class="mt-4 p-4 rounded" style="background: rgba(14, 27, 45, .06); border: 1px solid rgba(14, 27, 45, .08);">
            <h3 class="mb-3">İletişim</h3>
            <p class="mb-2"><strong>Firma:</strong> {{ $companyName }}</p>
            <p class="mb-2"><strong>Telefon:</strong> <a href="tel:{{ $companyPhoneHref }}">{{ $companyPhone }}</a></p>
            <p class="mb-2"><strong>E-posta:</strong> <a href="mailto:{{ $companyEmail }}?subject={{ $emailSubject }}">{{ $companyEmail }}</a></p>
            <p class="mb-2"><strong>Adres:</strong> {{ $companyAddress }}</p>
            <p class="mb-3"><strong>Web:</strong> {{ $companyWebsite }}</p>

            <div class="service-cta-buttons d-flex flex-wrap gap-2">
                <a class="btn-default service-cta-btn service-cta-call" href="tel:{{ $companyPhoneHref }}">
                    <span>Hemen Ara</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false" style="margin-left: 8px; fill: currentColor; vertical-align: -3px;">
                        <path d="M6.62 10.79a15.05 15.05 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.02-.24c1.12.37 2.33.56 3.57.56a1 1 0 0 1 1 1V20a1 1 0 0 1-1 1C10.85 21 3 13.15 3 3a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.24.19 2.45.56 3.57a1 1 0 0 1-.24 1.02l-2.2 2.2Z"/>
                    </svg>
                </a>
                <a class="btn-default service-cta-btn service-cta-map" href="{{ $mapsUrl }}" target="_blank" rel="noopener">
                    <span>Yol Tarifi Al</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false" style="margin-left: 8px; fill: currentColor; vertical-align: -3px;">
                        <path d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6a2.5 2.5 0 0 1 0 5.5Z"/>
                    </svg>
                </a>
            </div>
        </div>
    </div>
@endsection
