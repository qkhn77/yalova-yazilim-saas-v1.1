@extends('front.layouts.app')

@section('title', __('front.payment.iyzico_title'))
@section('canonical_url', route('odeme.show', $siparis))
@section('meta_robots', 'noindex, nofollow')

@section('content')
    <div class="page-header">
        <div class="container">
            <div class="page-header-box">
                <h1 class="wow fadeInUp">{{ __('front.payment.iyzico_heading') }}</h1>
            </div>
        </div>
    </div>

    <div class="container py-5">
        @php
            $html = $checkout_form_content !== ''
                ? base64_decode($checkout_form_content, true)
                : null;
        @endphp

        @if (is_string($html) && $html !== '')
            {!! $html !!}
        @else
            <div class="alert alert-warning">
                {{ __('front.payment.iyzico_render_failed') }}
            </div>
        @endif
    </div>
@endsection
