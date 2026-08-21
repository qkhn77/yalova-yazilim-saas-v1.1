@extends('front.layouts.app')

@section('title', __('front.payment.paytr_title'))
@section('canonical_url', route('odeme.show', $siparis))
@section('meta_robots', 'noindex, nofollow')

@section('content')
    <div class="page-header">
        <div class="container">
            <div class="page-header-box">
                <h1 class="wow fadeInUp">{{ __('front.payment.paytr_heading') }}</h1>
            </div>
        </div>
    </div>

    <div class="container py-5">
        <p class="mb-4">
            {{ __('front.payment.order_no') }}: <strong>{{ $siparis->siparis_no }}</strong> ·
            {{ __('front.payment.amount') }}: <strong>{{ number_format((float) $siparis->genel_toplam, 2, ',', '.') }} {{ $siparis->para_birimi }}</strong>
        </p>

        <p class="text-muted small mb-3">{{ __('front.payment.paytr_open_info') }}</p>

        <script src="https://www.paytr.com/js/iframeResizer.min.js"></script>
        <iframe
            src="{{ $iframe_src }}"
            id="paytriframe"
            frameborder="0"
            scrolling="no"
            style="width: 100%;"
        ></iframe>
        <script>iFrameResize({}, '#paytriframe');</script>
    </div>
@endsection
