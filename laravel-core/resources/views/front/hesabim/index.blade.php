@extends('front.layouts.app')

@section('title', __('front.account.title'))

@section('content')
    <div class="page-header">
        <div class="container">
            <div class="page-header-box">
                <h1 class="wow fadeInUp">{{ __('front.account.title') }}</h1>
            </div>
        </div>
    </div>

    <div class="container py-5">
        <div class="account-page-shell">
            <div class="account-page-frame">
                @include('front.hesabim.partials.nav')

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card account-inner-card p-3">
                            <h5 class="mb-1">{{ __('front.account.total_orders') }}</h5>
                            <div class="display-6">{{ (int) $siparisAdedi }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card account-inner-card p-3">
                            <h5 class="mb-1">{{ __('front.account.open_messages') }}</h5>
                            <div class="display-6">{{ (int) $acikMesajAdedi }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
