@extends('front.layouts.app')

@php
    use App\Models\Ecommerce\Odeme;
    use App\Models\Ecommerce\Siparis;
    $bekleyenOdemeVar = $siparis->odemeler->contains(fn ($o) => $o->durum === Odeme::DURUM_BEKLEMEDE);
    $odemeAdimiAktif = in_array($siparis->durum, [
        Siparis::DURUM_ONAY_BEKLIYOR,
        Siparis::DURUM_ODEME_BEKLENIYOR,
        Siparis::DURUM_BASARISIZ_ODEME,
    ], true);
    $eftBekliyor = $siparis->durum === Siparis::DURUM_EFT_ONAYI_BEKLIYOR;
@endphp

@section('title', __('front.payment.title'))
@section('canonical_url', route('odeme.show', $siparis))
@section('meta_robots', 'noindex, nofollow')

@section('content')
    <div class="page-header">
        <div class="container">
            <div class="page-header-box">
                <h1 class="wow fadeInUp">{{ __('front.payment.simulation_title') }}</h1>
            </div>
        </div>
    </div>

    <div class="container py-5">
        @if (session('odeme') === 'fail_retry')
            <div class="alert alert-warning">{{ __('front.payment.failed_retry') }}</div>
        @endif
        @if (session('odeme') === 'yeni_deneme')
            <div class="alert alert-info">{{ __('front.payment.new_attempt_started') }}</div>
        @endif
        @if (session('odeme') === 'eft_talep')
            <div class="alert alert-success mb-4">
                Havale / EFT sipariş talebiniz oluşturuldu. Aşağıdaki hesaba ödemenizi gönderip açıklama alanına sipariş numaranızı yazmanız yeterlidir. Yönetici onayından sonra sipariş süreci devam edecektir.
            </div>
        @endif
        @if (session('ecommerce_testmodu_bilgi'))
            <div class="alert alert-info mb-4">{{ session('ecommerce_testmodu_bilgi') }}</div>
        @endif
        @if (session('ecommerce_ayar_uyari'))
            <div class="alert alert-warning mb-4">{{ session('ecommerce_ayar_uyari') }}</div>
        @endif

        <p class="mb-2">
            {{ __('front.payment.order_no') }}: <strong>{{ $siparis->siparis_no }}</strong> · {{ __('front.payment.amount') }}:
            <strong>{{ number_format((float) $siparis->genel_toplam, 2, ',', '.') }} {{ $siparis->para_birimi }}</strong>
        </p>
        @if ($odemeAdimiAktif && $siparis->odeme_suresi_bitis_at)
            <p class="small text-muted mb-2">
                {{ __('front.payment.deadline') }}: {{ $siparis->odeme_suresi_bitis_at->timezone(config('app.timezone'))->format('d.m.Y H:i') }}
                · {{ __('front.payment.attempt_count') }}: {{ (int) $siparis->odeme_deneme_sayisi }}
            </p>
        @endif

        @if (! $eftBekliyor)
            <p class="text-muted small mb-4">
                {{ __('front.payment.mock_info') }}
            </p>
        @endif

        @if ($siparis->durum === Siparis::DURUM_IPTAL)
            <div class="alert alert-secondary">{{ __('front.payment.order_cancelled') }}</div>
            <a href="{{ route('products.index') }}" class="btn-default">{{ __('front.payment.back_to_shop') }}</a>
        @elseif ($eftBekliyor)
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-body p-4">
                            <h3 class="h5 fw-bold mb-3">Havale / EFT Bilgileri</h3>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="text-muted small mb-1">Ödeme Yöntemi</div>
                                    <div class="fw-semibold">{{ $siparis->odeme_yontemi_ad ?: 'Havale / EFT' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted small mb-1">Sipariş Durumu</div>
                                    <div class="fw-semibold text-warning">{{ Siparis::durumEtiketi($siparis->durum) }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted small mb-1">Banka</div>
                                    <div class="fw-semibold">{{ $siparis->havale_banka_adi ?: '—' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted small mb-1">Hesap Sahibi / Şirket</div>
                                    <div class="fw-semibold">{{ $siparis->havale_hesap_sahibi ?: '—' }}</div>
                                </div>
                                <div class="col-12">
                                    <div class="text-muted small mb-1">IBAN</div>
                                    <div class="fw-semibold fs-5">{{ $siparis->havale_iban ?: '—' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted small mb-1">Açıklama / Referans</div>
                                    <div class="fw-semibold">{{ $siparis->havale_referans_kodu ?: $siparis->siparis_no }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted small mb-1">Gönderilecek Tutar</div>
                                    <div class="fw-semibold">{{ number_format((float) $siparis->genel_toplam, 2, ',', '.') }} {{ $siparis->para_birimi }}</div>
                                </div>
                                <div class="col-12">
                                    <div class="text-muted small mb-1">Not</div>
                                    <div class="small text-secondary" style="white-space: pre-line;">{{ $siparis->havale_aciklama_notu ?: 'Ödemeyi gönderdikten sonra yönetici onayı beklenecektir.' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-body p-4">
                            <h3 class="h5 fw-bold mb-3">Süreç Nasıl İşler?</h3>
                            <div class="d-grid gap-3">
                                <div class="p-3 rounded-3" style="background: #f8fbff;">
                                    <div class="fw-semibold mb-1">1. Havalenizi gönderin</div>
                                    <div class="small text-muted">Yukarıdaki IBAN bilgisine ödeme yapın ve açıklamaya sipariş numaranızı yazın.</div>
                                </div>
                                <div class="p-3 rounded-3" style="background: #f8fbff;">
                                    <div class="fw-semibold mb-1">2. Yönetici onayı beklensin</div>
                                    <div class="small text-muted">Ödemeniz kontrol edildikten sonra siparişiniz onaylanır.</div>
                                </div>
                                <div class="p-3 rounded-3" style="background: #f8fbff;">
                                    <div class="fw-semibold mb-1">3. Sipariş süreci devam etsin</div>
                                    <div class="small text-muted">Onay sonrası hazırlık, kargo ve teslim akışı normal şekilde ilerler.</div>
                                </div>
                            </div>
                            <div class="alert alert-warning mt-4 mb-0">
                                Bu sipariş için ödeme kaydı yöneticinin manuel onayı sonrasında tamamlanacaktır.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @elseif (Siparis::odemeAlindiDurumMu($siparis->durum))
            <div class="alert alert-success">{{ __('front.payment.order_paid') }}</div>
            <a href="{{ route('checkout.success') }}" class="btn-default">{{ __('front.payment.order_summary') }}</a>
        @elseif ($odemeAdimiAktif)
            @if (! $bekleyenOdemeVar)
                <div class="alert alert-warning mb-3">{{ __('front.payment.no_pending_payment') }}</div>
                <form action="{{ route('odeme.tekrar_dene', $siparis) }}" method="post" class="mb-4">
                    @csrf
                    <button type="submit" class="btn-default">{{ __('front.payment.new_attempt') }}</button>
                </form>
            @endif

            @if ($bekleyenOdemeVar)
                <div class="d-flex flex-wrap gap-3">
                    <form action="{{ route('odeme.basarili', $siparis) }}" method="post">
                        @csrf
                        <button type="submit" class="btn-default">{{ __('front.payment.success_simulation') }}</button>
                    </form>
                    <form action="{{ route('odeme.basarisiz', $siparis) }}" method="post">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary">{{ __('front.payment.failed_simulation') }}</button>
                    </form>
                </div>
            @endif
        @else
            <div class="alert alert-info">{{ __('front.payment.step_not_allowed', ['status' => Siparis::durumEtiketi($siparis->durum)]) }}</div>
            <a href="{{ route('products.index') }}" class="btn-default">{{ __('front.payment.back_to_shop') }}</a>
        @endif
    </div>
@endsection
