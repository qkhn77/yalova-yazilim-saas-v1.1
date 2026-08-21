@extends('front.layouts.app')

@section('title', __('front.account.order_detail_title'))

@section('content')
    @php
        $fiyatServisi = app(\App\Services\Front\FrontFiyatServisi::class);
        $kargoTakipServisi = app(\App\Services\EcommerceKargoTakipServisi::class);
        $siparisParaBirimi = (string) ($siparis->para_birimi ?: 'TRY');
        $toplamKdv = 0.0;
        $toplamIndirim = 0.0;
        $takipUrl = $kargoTakipServisi->takipUrl($siparis->kargo_firmasi, $siparis->takip_no);
        $takipAdimlari = $kargoTakipServisi->durumAdimlari($siparis);
    @endphp
    <style>
        .account-order-image {
            width: 64px;
            height: 64px;
            border-radius: 14px;
            object-fit: contain;
            background: #fff;
            border: 1px solid rgba(15, 76, 129, .12);
            padding: 4px;
        }

        .account-order-product {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 260px;
        }

        .account-order-summary {
            max-width: 420px;
            margin-left: auto;
            border: 1px solid rgba(15, 76, 129, .10);
            border-radius: 18px;
            padding: 18px;
            background: #fff;
        }

        .account-order-summary p {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 10px;
        }

        .account-order-tracking {
            border: 1px solid rgba(15, 76, 129, .10);
            border-radius: 18px;
            padding: 18px;
            background: linear-gradient(135deg, #f8fbff 0%, #ffffff 100%);
        }

        .account-order-tracking-steps {
            display: grid;
            gap: 10px;
            margin-top: 16px;
        }

        .account-order-tracking-step {
            border-radius: 14px;
            padding: 12px 14px;
            background: #f4f7fa;
            color: #4f667d;
        }

        .account-order-tracking-step.is-done {
            background: #eef7f2;
            color: #1f5e39;
        }
    </style>
    <div class="page-header">
        <div class="container">
            <div class="page-header-box">
                <h1 class="wow fadeInUp">{{ __('front.account.order_detail_title') }}</h1>
            </div>
        </div>
    </div>

    <div class="container py-5">
        @if(empty($misafirTakip))
            @include('front.hesabim.partials.nav')
        @else
            <div class="mb-4">
                <a href="{{ route('orders.track') }}" class="btn btn-outline-secondary btn-sm">Yeni sorgu yap</a>
            </div>
        @endif

        <div class="card p-3 mb-4">
            <div><strong>{{ __('front.account.order_no') }}:</strong> {{ $siparis->siparis_no }}</div>
            <div><strong>{{ __('front.account.status') }}:</strong> {{ \App\Models\Ecommerce\Siparis::durumEtiketi($siparis->durum) }}</div>
            <div><strong>{{ __('front.account.amount') }}:</strong> {{ $fiyatServisi->cevirVeFormatla((float) $siparis->genel_toplam, $siparisParaBirimi) }}</div>
            <div><strong>{{ __('front.account.delivery_address') }}:</strong> {{ $siparis->teslimat_ilce ? $siparis->teslimat_ilce.', ' : '' }}{{ $siparis->teslimat_il ? $siparis->teslimat_il.', ' : '' }}{{ $siparis->teslimat_ulke ?: 'TR' }} - {{ $siparis->teslimat_adresi }}</div>
            <div><strong>Kargo:</strong> {{ $siparis->kargo_firmasi ?: '—' }} @if((float) ($siparis->kargo_ucreti ?? 0) > 0) · {{ $fiyatServisi->cevirVeFormatla((float) $siparis->kargo_ucreti, $siparisParaBirimi) }} @endif</div>
            @if($siparis->takip_no)
                <div><strong>{{ __('front.account.tracking_no') }}:</strong> {{ $siparis->takip_no }}</div>
            @endif
        </div>

        <div class="account-order-tracking mb-4">
            <div class="d-flex flex-wrap justify-content-between gap-3">
                <div>
                    <h2 class="h5 mb-2">Kargo Takibi</h2>
                    <div class="text-muted">{{ $kargoTakipServisi->durumMesaji($siparis) }}</div>
                </div>
                <div class="text-end">
                    <div><strong>Kargo Firması:</strong> {{ $siparis->kargo_firmasi ?: 'Henüz atanmadı' }}</div>
                    <div><strong>Takip No:</strong> {{ $siparis->takip_no ?: 'Henüz oluşmadı' }}</div>
                    @if($takipUrl)
                        <a href="{{ $takipUrl }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm mt-2">Kargoyu Takip Et</a>
                    @endif
                </div>
            </div>
            <div class="account-order-tracking-steps">
                @foreach($takipAdimlari as $adim)
                    <div class="account-order-tracking-step {{ $adim['done'] ? 'is-done' : '' }}">
                        <strong>{{ $adim['title'] }}</strong>
                        <div class="small mt-1">{{ $adim['description'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                <tr>
                    <th>Görsel</th>
                    <th>{{ __('front.account.product') }}</th>
                    <th>{{ __('front.account.quantity') }}</th>
                    <th>{{ __('front.account.unit_price') }}</th>
                    <th>İndirim</th>
                    <th>KDV</th>
                    <th>{{ __('front.account.line_total') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach($siparis->kalemler as $kalem)
                    @php
                        $stok = $kalem->stokKarti;
                        $gorselYolu = $stok?->kapak_gorsel_yolu ?: $stok?->og_gorsel;
                        $gorselUrl = $gorselYolu
                            ? asset('uploads/' . ltrim(str_replace('\\', '/', $gorselYolu), '/'))
                            : asset('theme/yalovakamera/images/yalova_kamera.png');
                        $kalemParaBirimi = strtoupper((string) ($kalem->getAttribute('para_birimi') ?: $siparisParaBirimi));
                        $kalemKdvOrani = (float) ($kalem->kdv_orani ?? 0);
                        $miktar = (float) $kalem->miktar;
                        $listeBirimFiyat = round((float) ($stok?->satis_fiyati ?: $kalem->birim_fiyat), 2);
                        $indirimTutari = round(max(0, ($listeBirimFiyat - (float) $kalem->birim_fiyat) * $miktar), 2);
                        $kdvTutari = round((float) $kalem->satir_toplami * ($kalemKdvOrani / 100), 2);
                        $satirGenelToplam = round((float) $kalem->satir_toplami + $kdvTutari, 2);
                        $toplamIndirim += $indirimTutari;
                        $toplamKdv += $kdvTutari;
                    @endphp
                    <tr>
                        <td><img src="{{ $gorselUrl }}" alt="{{ $kalem->urun_adi_snapshot }}" class="account-order-image" loading="lazy" decoding="async"></td>
                        <td>
                            <div class="account-order-product">
                                <div>
                                    <strong>{{ $kalem->urun_adi_snapshot }}</strong>
                                    @if($kalem->urun_kodu_snapshot)
                                        <div class="small text-muted">{{ $kalem->urun_kodu_snapshot }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>{{ number_format($miktar, 2, ',', '.') }}</td>
                        <td>{{ $fiyatServisi->satisFiyatiFormatla((float) $kalem->birim_fiyat, $kalemKdvOrani, $kalemParaBirimi) }}</td>
                        <td>-{{ $fiyatServisi->cevirVeFormatla($indirimTutari, $kalemParaBirimi) }}</td>
                        <td>{{ $fiyatServisi->cevirVeFormatla($kdvTutari, $kalemParaBirimi) }}</td>
                        <td>{{ $fiyatServisi->cevirVeFormatla($satirGenelToplam, $kalemParaBirimi) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="account-order-summary mt-4">
            <p><strong>Ara Toplam</strong><span>{{ $fiyatServisi->cevirVeFormatla((float) $siparis->ara_toplam, $siparisParaBirimi) }}</span></p>
            <p><strong>İndirim</strong><span>-{{ $fiyatServisi->cevirVeFormatla((float) ($siparis->indirim_toplami ?: $toplamIndirim), $siparisParaBirimi) }}</span></p>
            <p><strong>KDV</strong><span>{{ $fiyatServisi->cevirVeFormatla((float) ($siparis->kdv_toplam ?: $toplamKdv), $siparisParaBirimi) }}</span></p>
            <p><strong>Kargo</strong><span>{{ $fiyatServisi->cevirVeFormatla((float) ($siparis->kargo_ucreti ?? 0), $siparisParaBirimi) }}</span></p>
            <hr>
            <p class="fs-5 mb-0"><strong>Genel Toplam</strong><span>{{ $fiyatServisi->cevirVeFormatla((float) $siparis->genel_toplam, $siparisParaBirimi) }}</span></p>
        </div>
    </div>
@endsection
