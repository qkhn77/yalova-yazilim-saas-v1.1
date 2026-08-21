@extends('front.layouts.app')

@section('title', 'Sipariş Takip')
@section('canonical_url', route('orders.track'))
@section('meta_robots', 'noindex, nofollow')

@section('content')
    <div class="page-header">
        <div class="container">
            <div class="page-header-box">
                <h1 class="wow fadeInUp">Sipariş Takip</h1>
            </div>
        </div>
    </div>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm p-4 p-md-5">
                    <h2 class="h4 mb-3">Sipariş durumunuzu sorgulayın</h2>
                    <p class="text-muted mb-4">Sipariş numaranız ile siparişte kullanılan e-posta adresi veya telefon numarasını yazarak kargo ve sipariş durumunu görüntüleyebilirsiniz.</p>

                    @if(! empty($hata))
                        <div class="alert alert-warning">{{ $hata }}</div>
                    @endif

                    @if($errors->any())
                        <div class="alert alert-danger">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('orders.track.search') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="siparis_no" class="form-label">Sipariş Numarası</label>
                            <input type="text" id="siparis_no" name="siparis_no" value="{{ old('siparis_no') }}" class="form-control" placeholder="Örn: SIP-20260416-0001" required>
                        </div>
                        <div class="mb-4">
                            <label for="dogrulama" class="form-label">E-posta veya Telefon</label>
                            <input type="text" id="dogrulama" name="dogrulama" value="{{ old('dogrulama') }}" class="form-control" placeholder="ornek@eposta.com veya 05xx xxx xx xx" required>
                        </div>
                        <button type="submit" class="btn-default">Siparişi Sorgula</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
