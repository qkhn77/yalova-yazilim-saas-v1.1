@extends('front.layouts.app')

@section('title', 'Sayfa Bulunamadı | Yalova Kamera Sistemleri')
@section('meta_description', 'Aradığınız sayfa bulunamadı. Yalova Kamera güvenlik sistemleri ana sayfasına dönebilirsiniz.')
@section('meta_keywords', '404, sayfa bulunamadı, yalova kamera sistemleri')
@push('head_meta')
    <meta name="robots" content="noindex, follow">
@endpush

@php
    \App\Helpers\BreadcrumbHelper::clear();
    \App\Helpers\BreadcrumbHelper::add('Anasayfa', route('home'));
    \App\Helpers\BreadcrumbHelper::add('404');
@endphp

@section('content')

    <!-- Page Header Start -->
    <div class="page-header parallaxie">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-12">
                    <div class="page-header-box">
                        <h1 class="wow fadeInUp" data-cursor="-opaque">Sayfa bulunamadı</h1>
                        {!! \App\Helpers\BreadcrumbHelper::render() !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Page Header End -->

    <!-- Error Section Start -->
    <div class="error-page">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="error-page-image wow fadeInUp">
                        <img src="{{ asset('theme/yalovakamera/images/404-error-img.png') }}" alt="404 - Sayfa bulunamadı">
                    </div>
                    <div class="error-page-content">
                        <div class="section-title">
                            <h2 class="wow fadeInUp" data-cursor="-opaque" data-wow-delay="0.2s"><span>Oops!</span> sayfa bulunamadı</h2>
                        </div>
                        <div class="error-page-content-body">
                            <p class="wow fadeInUp" data-wow-delay="0.4s">Aradığınız sayfa mevcut değil veya taşınmış olabilir.</p>
                            <a class="btn-default wow fadeInUp" data-wow-delay="0.6s" href="{{ route('home') }}">Ana sayfaya dön</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Error Section End -->

@endsection
