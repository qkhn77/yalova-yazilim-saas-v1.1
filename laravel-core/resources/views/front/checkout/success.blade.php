@extends('front.layouts.app')

@section('title', __('front.checkout.success_title'))
@section('canonical_url', route('checkout.success'))
@section('meta_robots', 'noindex, nofollow')

@section('content')
    @php
        $kargoTakipServisi = app(\App\Services\EcommerceKargoTakipServisi::class);
        $takipUrl = $siparis ? $kargoTakipServisi->takipUrl($siparis->kargo_firmasi, $siparis->takip_no) : null;
    @endphp
    <div class="page-header">
        <div class="container">
            <div class="page-header-box">
                <h1 class="wow fadeInUp">{{ __('front.checkout.success_received') }}</h1>
            </div>
        </div>
    </div>

    <div class="container py-5">
        @if($siparis)
            <div class="alert alert-success">
                @if(session('odeme') === 'ok')
                    <strong>{{ __('front.checkout.payment_received') }}</strong> {{ __('front.checkout.order_no_label') }}: <strong>{{ $siparis->siparis_no }}</strong>
                @else
                    <strong>{{ __('front.checkout.order_created') }}</strong> {{ __('front.checkout.order_no_label') }}: <strong>{{ $siparis->siparis_no }}</strong>
                    @if($siparis->durum === \App\Models\Ecommerce\Siparis::DURUM_ODEME_BEKLENIYOR)
                        <p class="mb-0 mt-2">{{ __('front.checkout.payment_pending_note') }}</p>
                    @endif
                @endif
            </div>
            <div class="card mb-4" style="max-width: 640px;">
                <div class="card-body">
                    <h2 class="h5 mb-3">{{ __('front.checkout.next_steps') }}</h2>
                    <ol class="mb-0 ps-3">
                        <li class="mb-2">{{ __('front.checkout.step_save_order_no', ['orderNo' => $siparis->siparis_no]) }}</li>
                        <li class="mb-2">{{ __('front.checkout.step_check_contact') }}</li>
                        <li>{{ __('front.checkout.step_contact_support') }}</li>
                    </ol>
                </div>
            </div>
            <div class="card mb-4" style="max-width: 640px;">
                <div class="card-body">
                    <h2 class="h5 mb-3">Teslimat ve Kargo</h2>
                    <p class="mb-2"><strong>Kargo:</strong> {{ $siparis->kargo_firmasi ?: 'Sipariş sonrası belirlenecek' }}</p>
                    <p class="mb-2"><strong>Kargo Ücreti:</strong> {{ number_format((float) ($siparis->kargo_ucreti ?? 0), 2, ',', '.') }} {{ $siparis->kargo_para_birimi ?: 'TRY' }}</p>
                    <p class="mb-2"><strong>Teslimat:</strong> {{ $siparis->teslimat_ilce ? $siparis->teslimat_ilce.', ' : '' }}{{ $siparis->teslimat_il ? $siparis->teslimat_il.', ' : '' }}{{ $siparis->teslimat_ulke ?: 'TR' }}</p>
                    <p class="mb-0"><strong>Kargo Durumu:</strong> {{ $kargoTakipServisi->durumMesaji($siparis) }}</p>
                    @if($takipUrl)
                        <a href="{{ $takipUrl }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm mt-3">Kargoyu Takip Et</a>
                    @endif
                </div>
            </div>
        @else
            <div class="alert alert-info">{{ __('front.checkout.order_not_found') }}</div>
        @endif
        <a href="{{ route('products.index') }}" class="btn-default">{{ __('front.checkout.continue_shopping') }}</a>
    </div>
@endsection
