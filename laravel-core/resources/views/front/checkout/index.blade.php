@extends('front.layouts.app')

@section('title', __('front.checkout.title'))
@section('canonical_url', route('checkout.index'))
@section('meta_robots', 'noindex, nofollow')

@section('content')
    @php
        $fiyatServisi = app(\App\Services\Front\FrontFiyatServisi::class);
        $aktifParaBirimi = $fiyatServisi->aktifParaBirimi();
        $ulkeServisi = app(\App\Services\EcommerceUlkeServisi::class);
        $kargoSecenekleri = $kargoSecenekleri ?? collect();
        $ilkKargoSecenegi = $kargoSecenekleri->first();
        $seciliKargoId = (int) old('kargo_yontemi_id', is_array($ilkKargoSecenegi) ? (int) $ilkKargoSecenegi['yontem']->id : 0);
        $seciliKargoSecenegi = $kargoSecenekleri->first(fn ($item) => (int) $item['yontem']->id === $seciliKargoId);
        $adresler = $adresler ?? collect();
        $varsayilanAdres = $adresler->firstWhere('varsayilan_teslimat_mi', true) ?: $adresler->first();
        $seciliAdresId = (int) old('selected_address_id', $varsayilanAdres?->id ?? 0);
        $ulkeSecenekleri = $ulkeSecenekleri ?? ['TR' => 'Türkiye'];
        $varsayilanUlke = $varsayilanUlke ?? 'TR';
        $ulkeKurallari = collect($ulkeSecenekleri)
            ->mapWithKeys(fn ($ad, $kod) => [$kod => $ulkeServisi->postaKoduKurali((string) $kod)])
            ->all();
        $bolgeSecenekleri = $ulkeServisi->bolgeSecenekleri();
    @endphp
    <style>
        .checkout-shell {
            max-width: 1180px;
            margin: 0 auto;
        }

        .checkout-card {
            background: #ffffff;
            border: 1px solid rgba(15, 76, 129, 0.10);
            border-radius: 22px;
            box-shadow: 0 18px 48px rgba(15, 76, 129, 0.08);
            padding: 24px;
        }

        .checkout-card-title {
            font-size: 18px;
            font-weight: 800;
            color: #14324d;
            margin-bottom: 16px;
        }

        .checkout-shipping-option {
            display: block;
            border: 1px solid rgba(15, 76, 129, 0.14);
            border-radius: 16px;
            padding: 14px 16px;
            cursor: pointer;
            transition: border-color .2s ease, box-shadow .2s ease, background-color .2s ease;
        }

        .checkout-shipping-option:has(input:checked) {
            border-color: #0f4c81;
            background: #f3f8fd;
            box-shadow: 0 0 0 3px rgba(15, 76, 129, .10);
        }

        .checkout-shipping-main {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }

        .checkout-shipping-name {
            font-weight: 800;
            color: #14324d;
        }

        .checkout-shipping-meta {
            color: #60738b;
            font-size: 13px;
            margin-top: 4px;
        }

        .checkout-shipping-price {
            font-weight: 900;
            color: #0f4c81;
            white-space: nowrap;
        }

        .checkout-summary-line {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 10px;
            color: #193956;
        }

        .checkout-summary-line strong {
            font-weight: 800;
        }

        .checkout-trust-list {
            display: grid;
            gap: 10px;
            margin-top: 16px;
            color: #405771;
            font-size: 14px;
        }

        .checkout-trust-list span {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .checkout-auth-box {
            border: 1px solid rgba(15, 76, 129, 0.12);
            border-radius: 18px;
            background: linear-gradient(135deg, #f8fbff 0%, #ffffff 100%);
            padding: 18px;
        }

        .checkout-address-option {
            border: 1px solid rgba(15, 76, 129, 0.14);
            border-radius: 16px;
            padding: 14px;
            cursor: pointer;
            height: 100%;
            transition: border-color .2s ease, box-shadow .2s ease, background-color .2s ease;
        }

        .checkout-address-option:has(input:checked) {
            border-color: #0f4c81;
            background: #f3f8fd;
            box-shadow: 0 0 0 3px rgba(15, 76, 129, .10);
        }

        .checkout-address-title {
            font-weight: 800;
            color: #14324d;
        }

        .checkout-address-text {
            color: #61758c;
            font-size: 13px;
            margin-top: 4px;
            white-space: pre-line;
        }

        .checkout-shipping-loading {
            color: #60738b;
            font-size: 14px;
            padding: 12px 0;
        }

        .checkout-payment-option {
            display: block;
            border: 1px solid rgba(15, 76, 129, 0.14);
            border-radius: 18px;
            padding: 16px;
            cursor: pointer;
            transition: border-color .2s ease, box-shadow .2s ease, background-color .2s ease;
            height: 100%;
        }

        .checkout-payment-option:has(input:checked) {
            border-color: #0f4c81;
            background: #f3f8fd;
            box-shadow: 0 0 0 3px rgba(15, 76, 129, .10);
        }

        .checkout-payment-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            font-weight: 800;
            color: #14324d;
        }

        .checkout-payment-type {
            font-size: 12px;
            font-weight: 700;
            color: #0f4c81;
            background: rgba(15, 76, 129, .10);
            border-radius: 999px;
            padding: 4px 10px;
        }

        .checkout-payment-description {
            color: #60738b;
            font-size: 13px;
            margin-top: 8px;
        }

        .checkout-payment-preview {
            margin-top: 14px;
            border: 1px dashed rgba(15, 76, 129, 0.18);
            border-radius: 16px;
            background: linear-gradient(135deg, #f8fbff 0%, #ffffff 100%);
            padding: 16px;
        }

        .checkout-payment-preview-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px 18px;
        }

        .checkout-payment-preview-label {
            font-size: 12px;
            font-weight: 700;
            color: #60738b;
            text-transform: uppercase;
            letter-spacing: .03em;
            margin-bottom: 4px;
        }

        .checkout-payment-preview-value {
            font-size: 14px;
            font-weight: 700;
            color: #14324d;
            word-break: break-word;
        }

        .checkout-payment-preview-note {
            margin-top: 14px;
            color: #405771;
            font-size: 13px;
            white-space: pre-line;
        }

        .checkout-field-error {
            font-size: 13px;
            color: #c62828;
            margin-top: 6px;
        }

        @media (max-width: 767.98px) {
            .checkout-payment-preview-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <div class="page-header">
        <div class="container">
            <div class="page-header-box">
                <h1 class="wow fadeInUp">{{ __('front.checkout.title') }}</h1>
            </div>
        </div>
    </div>

    <div class="container py-5">
        <div class="checkout-shell">
            @if(session('checkout_bilgi'))
                <div class="alert alert-info">
                    {{ session('checkout_bilgi') }}
                </div>
            @endif

            @if(session('checkout_uyari'))
                <div class="alert alert-warning">
                    {{ session('checkout_uyari') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">
                    <strong>Siparişe devam edebilmek için aşağıdaki bilgileri kontrol edin.</strong>
                    <ul class="mb-0 mt-2 ps-3">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('checkout.store') }}" method="POST" class="row g-4">
                @csrf
                <div class="col-lg-8">
                    <div class="checkout-card mb-4">
                        <div class="checkout-card-title">Üyelik Durumu</div>
                        @auth
                            <div class="checkout-auth-box">
                                <strong>{{ auth()->user()->name ?: auth()->user()->email }}</strong> hesabıyla devam ediyorsunuz.
                                <div class="text-muted mt-1">Kayıtlı adreslerinizi seçebilir, yeni adres ekleyebilir ve siparişlerinizi hesabınızdan takip edebilirsiniz.</div>
                            </div>
                        @else
                            <div class="checkout-auth-box d-flex flex-wrap align-items-center justify-content-between gap-3">
                                <div>
                                    <strong>Misafir olarak devam edebilirsiniz.</strong>
                                    <div class="text-muted mt-1">Üye girişi yaparsanız adresleriniz ve sipariş geçmişiniz hesabınıza kaydedilir.</div>
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="{{ route('buyer.login') }}" class="btn btn-outline-primary">Giriş Yap</a>
                                    <a href="{{ route('buyer.register') }}" class="btn btn-primary">Üye Ol</a>
                                </div>
                            </div>
                        @endauth
                    </div>

                    <div class="checkout-card mb-4">
                        <div class="checkout-card-title">Müşteri Bilgileri</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('front.checkout.name_surname') }}</label>
                                <input class="form-control @error('musteri_ad_soyad') is-invalid @enderror" name="musteri_ad_soyad" required value="{{ old('musteri_ad_soyad') }}">
                                @error('musteri_ad_soyad')<div class="checkout-field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('front.checkout.phone') }}</label>
                                <input class="form-control @error('musteri_telefon') is-invalid @enderror" name="musteri_telefon" required value="{{ old('musteri_telefon') }}">
                                @error('musteri_telefon')<div class="checkout-field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('front.checkout.email') }}</label>
                                <input class="form-control @error('musteri_email') is-invalid @enderror" name="musteri_email" type="email" value="{{ old('musteri_email') }}">
                                @error('musteri_email')<div class="checkout-field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('front.checkout.coupon_code') }}</label>
                                <input class="form-control" name="kupon_kodu" value="{{ old('kupon_kodu', $kuponKodu ?? '') }}" placeholder="{{ __('front.checkout.optional') }}">
                            </div>
                        </div>
                    </div>

                    <div class="checkout-card mb-4">
                        <div class="checkout-card-title">Teslimat Adresi</div>
                        @auth
                            @if($adresler->isNotEmpty())
                                <div class="row g-3 mb-3">
                                    @foreach($adresler as $adres)
                                        <div class="col-md-6">
                                            <label
                                                class="checkout-address-option"
                                                data-address-option
                                                data-name="{{ $adres->ad_soyad }}"
                                                data-phone="{{ $adres->telefon }}"
                                                data-country="{{ $adres->ulke_kodu ?: 'TR' }}"
                                                data-city="{{ $adres->sehir }}"
                                                data-district="{{ $adres->ilce }}"
                                                data-postal="{{ $adres->posta_kodu }}"
                                                data-address="{{ trim(($adres->mahalle ? $adres->mahalle . "\n" : '') . $adres->acik_adres) }}"
                                            >
                                                <input
                                                    type="radio"
                                                    name="selected_address_id"
                                                    value="{{ $adres->id }}"
                                                    class="form-check-input me-2"
                                                    @checked($seciliAdresId === (int) $adres->id)
                                                >
                                                <span class="checkout-address-title">{{ $adres->baslik }}</span>
                                                @if($adres->varsayilan_teslimat_mi)
                                                    <span class="badge bg-primary ms-1">Varsayılan</span>
                                                @endif
                                                <span class="checkout-address-text d-block">
                                                    {{ $adres->ad_soyad }} · {{ $adres->telefon }}
                                                    {{ "\n" }}{{ $adres->ilce }} / {{ $adres->sehir }} · {{ $adres->ulke_kodu ?: 'TR' }}
                                                    {{ "\n" }}{{ $adres->acik_adres }}
                                                </span>
                                            </label>
                                        </div>
                                    @endforeach
                                    <div class="col-md-6">
                                        <label class="checkout-address-option">
                                            <input type="radio" name="selected_address_id" value="0" class="form-check-input me-2" @checked($seciliAdresId === 0)>
                                            <span class="checkout-address-title">Yeni adres kullan</span>
                                            <span class="checkout-address-text d-block">Aşağıdaki alanlara yeni teslimat adresinizi yazın.</span>
                                        </label>
                                    </div>
                                </div>
                            @else
                                <div class="alert alert-info">Kayıtlı adresiniz yok. Aşağıdan yeni adres girip isterseniz adres defterinize kaydedebilirsiniz.</div>
                            @endif
                        @endauth
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Ülke</label>
                                <select class="form-control @error('teslimat_ulke') is-invalid @enderror" name="teslimat_ulke" data-checkout-country>
                                    @foreach($ulkeSecenekleri as $kod => $ad)
                                        <option value="{{ $kod }}" @selected(old('teslimat_ulke', $varsayilanUlke) === $kod)>{{ $ad }}</option>
                                    @endforeach
                                </select>
                                @error('teslimat_ulke')<div class="checkout-field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">İl / Eyalet</label>
                                <select class="form-control @error('teslimat_il') is-invalid @enderror d-none" data-checkout-city-select></select>
                                <input class="form-control @error('teslimat_il') is-invalid @enderror" data-checkout-city-input name="teslimat_il" value="{{ old('teslimat_il') }}" placeholder="Yalova">
                                @error('teslimat_il')<div class="checkout-field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">İlçe / Bölge</label>
                                <input class="form-control @error('teslimat_ilce') is-invalid @enderror" name="teslimat_ilce" value="{{ old('teslimat_ilce') }}" placeholder="Çiftlikköy">
                                @error('teslimat_ilce')<div class="checkout-field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Posta Kodu</label>
                                <input class="form-control @error('teslimat_posta_kodu') is-invalid @enderror" name="teslimat_posta_kodu" value="{{ old('teslimat_posta_kodu') }}" placeholder="{{ $ulkeKurallari[old('teslimat_ulke', $varsayilanUlke)]['example'] ?? 'Posta kodu' }}">
                                <div class="form-text" data-postal-help></div>
                                @error('teslimat_posta_kodu')<div class="checkout-field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">{{ __('front.checkout.address') }}</label>
                                <textarea class="form-control @error('teslimat_adresi') is-invalid @enderror" name="teslimat_adresi" rows="3" required>{{ old('teslimat_adresi') }}</textarea>
                                @error('teslimat_adresi')<div class="checkout-field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label">{{ __('front.checkout.note') }}</label>
                                <textarea class="form-control" name="notlar" rows="3">{{ old('notlar') }}</textarea>
                            </div>
                            @auth
                                <div class="col-md-4">
                                    <label class="form-label">Adres Başlığı</label>
                                    <input class="form-control" name="adres_baslik" value="{{ old('adres_baslik', 'Teslimat Adresi') }}" placeholder="Ev, İş, Depo">
                                </div>
                                <div class="col-md-8 d-flex flex-wrap align-items-end gap-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="adresi-kaydet" name="adresi_kaydet" value="1" @checked(old('adresi_kaydet'))>
                                        <label class="form-check-label" for="adresi-kaydet">Bu yeni adresi adres defterime kaydet</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="varsayilan-teslimat" name="varsayilan_teslimat_mi" value="1" @checked(old('varsayilan_teslimat_mi'))>
                                        <label class="form-check-label" for="varsayilan-teslimat">Varsayılan teslimat adresim olsun</label>
                                    </div>
                                </div>
                            @endauth
                        </div>
                    </div>

                    <div class="checkout-card">
                        <div class="checkout-card-title">Kargo Yöntemi</div>
                        <div data-shipping-options>
                            @if($kargoSecenekleri->isEmpty())
                                <div class="alert alert-warning mb-0">
                                    Bu teslimat adresi için aktif kargo yöntemi bulunamadı. Sipariş sonrası ekip sizinle iletişime geçecektir.
                                </div>
                            @else
                                <div class="row g-3">
                                    @foreach($kargoSecenekleri as $secenek)
                                        @php
                                            $yontem = $secenek['yontem'];
                                            $checked = $seciliKargoId === (int) $yontem->id;
                                        @endphp
                                        <div class="col-md-6">
                                            <label class="checkout-shipping-option" data-shipping-option>
                                                <input
                                                    type="radio"
                                                    name="kargo_yontemi_id"
                                                    value="{{ $yontem->id }}"
                                                    class="form-check-input me-2"
                                                    data-kargo-ucret="{{ number_format((float) $secenek['ucret'], 2, '.', '') }}"
                                                    data-kargo-label="{{ $secenek['ucret_formatli'] }}"
                                                    @checked($checked)
                                                    required
                                                >
                                                <span class="checkout-shipping-main">
                                                    <span>
                                                        <span class="checkout-shipping-name">{{ $yontem->ad }}</span>
                                                        <span class="checkout-shipping-meta d-block">
                                                            {{ $secenek['tahmini_teslim'] }}
                                                            @if($yontem->yurt_disi_aktif)
                                                                · Yurt dışı destekli
                                                            @endif
                                                        </span>
                                                    </span>
                                                    <span class="checkout-shipping-price">{{ $secenek['ucret_formatli'] }}</span>
                                                </span>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="checkout-card mt-4">
                        <div class="checkout-card-title">Ödeme Yöntemi</div>
                        @if(empty($odemeYontemleri))
                            <div class="alert alert-warning mb-0">
                                Bu firma için şu an aktif ödeme yöntemi bulunmuyor. Lütfen daha sonra tekrar deneyin.
                            </div>
                        @else
                            <div class="row g-3">
                                @foreach($odemeYontemleri as $yontem)
                                    @php
                                        $yontemSecimi = (string) ($yontem['secim'] ?? '');
                                        $yontemTipi = (string) ($yontem['tip'] ?? '');
                                    @endphp
                                    <div class="col-md-6">
                                        <label
                                            class="checkout-payment-option"
                                            data-payment-option
                                            data-payment-selection="{{ $yontemSecimi }}"
                                            data-payment-type="{{ $yontemTipi }}"
                                            data-payment-name="{{ $yontem['ad'] ?? '' }}"
                                            data-payment-description="{{ $yontem['aciklama'] ?? '' }}"
                                            data-payment-bank="{{ $yontem['banka_adi'] ?? '' }}"
                                            data-payment-holder="{{ $yontem['hesap_sahibi'] ?? '' }}"
                                            data-payment-iban="{{ $yontem['iban'] ?? '' }}"
                                            data-payment-note="{{ $yontem['odeme_notu'] ?? '' }}"
                                        >
                                            <input
                                                type="radio"
                                                name="odeme_yontemi_secimi"
                                                value="{{ $yontemSecimi }}"
                                                class="form-check-input me-2"
                                                @checked((string) $seciliOdemeYontemi === $yontemSecimi)
                                                required
                                            >
                                            <span class="checkout-payment-title">
                                                <span>{{ $yontem['ad'] }}</span>
                                                <span class="checkout-payment-type">
                                                    {{ $yontemTipi === 'havale_eft' ? 'Havale / EFT' : 'Online Ödeme' }}
                                                </span>
                                            </span>
                                            <span class="checkout-payment-description d-block">{{ $yontem['aciklama'] }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            @error('odeme_yontemi_secimi')<div class="checkout-field-error">{{ $message }}</div>@enderror

                            <div class="checkout-payment-preview" data-payment-preview>
                                <div class="fw-bold text-dark mb-2" data-payment-preview-title></div>
                                <div class="text-muted small mb-3" data-payment-preview-description></div>
                                <div class="checkout-payment-preview-grid d-none" data-payment-preview-grid>
                                    <div>
                                        <div class="checkout-payment-preview-label">Banka</div>
                                        <div class="checkout-payment-preview-value" data-payment-preview-bank></div>
                                    </div>
                                    <div>
                                        <div class="checkout-payment-preview-label">Hesap Sahibi</div>
                                        <div class="checkout-payment-preview-value" data-payment-preview-holder></div>
                                    </div>
                                    <div style="grid-column: 1 / -1;">
                                        <div class="checkout-payment-preview-label">IBAN</div>
                                        <div class="checkout-payment-preview-value" data-payment-preview-iban></div>
                                    </div>
                                </div>
                                <div class="checkout-payment-preview-note d-none" data-payment-preview-note></div>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="checkout-card position-sticky" style="top: 110px;">
                        <div class="checkout-card-title">Sipariş Özeti</div>
                        <div class="checkout-summary-line">
                            <strong>{{ __('front.checkout.sub_total') }}</strong>
                            <span>{{ $fiyatServisi->formatla((float) $toplamlar['ara_toplam'], (string) ($toplamlar['para_birimi'] ?? $aktifParaBirimi)) }}</span>
                        </div>
                        @if((float) ($toplamlar['indirim_toplami'] ?? 0) > 0)
                            <div class="checkout-summary-line text-success">
                                <strong>{{ __('front.checkout.discount') }}</strong>
                                <span>-{{ $fiyatServisi->formatla((float) $toplamlar['indirim_toplami'], (string) ($toplamlar['para_birimi'] ?? $aktifParaBirimi)) }}</span>
                            </div>
                        @endif
                        <div class="checkout-summary-line">
                            <strong>{{ __('front.checkout.vat') }}</strong>
                            <span>{{ $fiyatServisi->formatla((float) $toplamlar['kdv_toplam'], (string) ($toplamlar['para_birimi'] ?? $aktifParaBirimi)) }}</span>
                        </div>
                        <div class="checkout-summary-line">
                            <strong>Kargo</strong>
                            <span data-checkout-kargo-label>{{ $seciliKargoSecenegi['ucret_formatli'] ?? 'Seçiniz' }}</span>
                        </div>
                        <hr>
                        <div class="checkout-summary-line fs-5">
                            <strong>{{ __('front.checkout.grand_total') }}</strong>
                            <span data-checkout-grand-total data-base-total="{{ number_format((float) $toplamlar['genel_toplam'], 2, '.', '') }}">
                                {{ $fiyatServisi->formatla((float) $toplamlar['genel_toplam'] + (float) ($seciliKargoSecenegi['ucret'] ?? 0), (string) ($toplamlar['para_birimi'] ?? $aktifParaBirimi)) }}
                            </span>
                        </div>
                        @if(! empty($toplamlar['uygulanan_kampanya']))
                            <p class="text-success mb-3">
                                {{ __('front.checkout.campaign_applied') }}: <strong>{{ $toplamlar['uygulanan_kampanya']['ad'] }}</strong>
                            </p>
                        @endif
                        <button type="submit" class="btn-default w-100 text-center" data-checkout-submit @disabled(empty($odemeYontemleri))>{{ __('front.checkout.create_order') }}</button>

                        <div class="checkout-trust-list">
                            <span>✓ SSL korumalı güvenli ödeme</span>
                            <span>✓ Aras Kargo ve UPS seçenekleri</span>
                            <span>✓ Sipariş sonrası takip numarası desteği</span>
                            <span>✓ İade kargo süreci panelden yönetilir</span>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            const totalEl = document.querySelector('[data-checkout-grand-total]');
            const kargoLabel = document.querySelector('[data-checkout-kargo-label]');
            const countrySelect = document.querySelector('[data-checkout-country]');
            const addressOptions = document.querySelectorAll('[data-address-option]');
            const paymentOptions = document.querySelectorAll('[data-payment-option]');
            const cityInput = document.querySelector('[data-checkout-city-input]');
            const citySelect = document.querySelector('[data-checkout-city-select]');
            const postalInput = document.querySelector('[name="teslimat_posta_kodu"]');
            const shippingContainer = document.querySelector('[data-shipping-options]');
            const postalHelp = document.querySelector('[data-postal-help]');
            const paymentPreview = document.querySelector('[data-payment-preview]');
            const paymentPreviewTitle = document.querySelector('[data-payment-preview-title]');
            const paymentPreviewDescription = document.querySelector('[data-payment-preview-description]');
            const paymentPreviewGrid = document.querySelector('[data-payment-preview-grid]');
            const paymentPreviewBank = document.querySelector('[data-payment-preview-bank]');
            const paymentPreviewHolder = document.querySelector('[data-payment-preview-holder]');
            const paymentPreviewIban = document.querySelector('[data-payment-preview-iban]');
            const paymentPreviewNote = document.querySelector('[data-payment-preview-note]');
            const submitButton = document.querySelector('[data-checkout-submit]');
            if (! totalEl || ! shippingContainer) {
                return;
            }

            const formatter = new Intl.NumberFormat('tr-TR', {
                style: 'currency',
                currency: @json((string) ($toplamlar['para_birimi'] ?? $aktifParaBirimi)),
            });

            let requestTimer = null;
            let requestCounter = 0;
            const countryRules = @json($ulkeKurallari, JSON_UNESCAPED_UNICODE);
            const regionOptions = @json($bolgeSecenekleri, JSON_UNESCAPED_UNICODE);

            const getRadios = () => document.querySelectorAll('input[name="kargo_yontemi_id"][data-kargo-ucret]');

            const update = () => {
                const checked = document.querySelector('input[name="kargo_yontemi_id"][data-kargo-ucret]:checked');
                const base = Number(totalEl.dataset.baseTotal || 0);
                const cargo = checked ? Number(checked.dataset.kargoUcret || 0) : 0;
                totalEl.textContent = formatter.format(base + cargo);
                if (kargoLabel) {
                    kargoLabel.textContent = checked ? (checked.dataset.kargoLabel || formatter.format(cargo)) : 'Seçiniz';
                }
            };

            const bindShippingEvents = () => {
                getRadios().forEach((radio) => radio.addEventListener('change', update));
                update();
            };

            const selectedPaymentOption = () => Array.from(paymentOptions).find((option) => {
                const radio = option.querySelector('input[name="odeme_yontemi_secimi"]');

                return radio && radio.checked;
            }) || null;

            const syncPaymentPreview = () => {
                const selected = selectedPaymentOption();

                if (!selected || !paymentPreview || !paymentPreviewTitle || !paymentPreviewDescription) {
                    return;
                }

                const type = selected.dataset.paymentType || 'online';
                const name = selected.dataset.paymentName || 'Ödeme yöntemi';
                const description = selected.dataset.paymentDescription || '';
                const bank = selected.dataset.paymentBank || '';
                const holder = selected.dataset.paymentHolder || '';
                const iban = selected.dataset.paymentIban || '';
                const note = selected.dataset.paymentNote || '';

                paymentPreviewTitle.textContent = name;
                paymentPreviewDescription.textContent = description;

                if (type === 'havale_eft') {
                    paymentPreviewGrid?.classList.remove('d-none');
                    if (paymentPreviewBank) {
                        paymentPreviewBank.textContent = bank || '—';
                    }
                    if (paymentPreviewHolder) {
                        paymentPreviewHolder.textContent = holder || '—';
                    }
                    if (paymentPreviewIban) {
                        paymentPreviewIban.textContent = iban || '—';
                    }
                    if (paymentPreviewNote) {
                        paymentPreviewNote.textContent = note || 'Sipariş talebiniz oluşturulduktan sonra tam banka bilgileri ve açıklama notu ayrı ekranda gösterilecektir.';
                        paymentPreviewNote.classList.remove('d-none');
                    }
                    if (submitButton) {
                        submitButton.textContent = 'Sipariş Talebi Oluştur';
                    }

                    return;
                }

                paymentPreviewGrid?.classList.add('d-none');
                if (paymentPreviewNote) {
                    paymentPreviewNote.textContent = 'Siparişiniz oluşturulduktan sonra güvenli online ödeme adımına yönlendirilirsiniz.';
                    paymentPreviewNote.classList.remove('d-none');
                }
                if (submitButton) {
                    submitButton.textContent = '{{ __('front.checkout.create_order') }}';
                }
            };

            const syncPostalHint = () => {
                const country = (countrySelect ? countrySelect.value : '{{ $varsayilanUlke }}').toUpperCase();
                const rule = countryRules[country] || { example: 'Posta kodu', required: false };

                if (postalInput) {
                    postalInput.placeholder = rule.example || 'Posta kodu';
                    postalInput.required = !! rule.required;
                }

                if (postalHelp) {
                    postalHelp.textContent = rule.required
                        ? `Bu ülke için posta kodu zorunludur. Örnek: ${rule.example}`
                        : `Örnek: ${rule.example}`;
                }
            };

            const activeCityValue = () => {
                if (citySelect && !citySelect.classList.contains('d-none')) {
                    return citySelect.value || '';
                }

                return cityInput ? cityInput.value : '';
            };

            const syncRegionField = () => {
                if (!countrySelect || !cityInput || !citySelect) {
                    return;
                }

                const country = countrySelect.value.toUpperCase();
                const options = regionOptions[country] || [];
                const currentValue = activeCityValue();

                if (options.length) {
                    citySelect.innerHTML = `<option value="">Seçiniz</option>${options.map((item) => `<option value="${item}">${item}</option>`).join('')}`;
                    citySelect.value = currentValue && options.includes(currentValue) ? currentValue : '';
                    citySelect.name = 'teslimat_il';
                    cityInput.name = '';
                    citySelect.classList.remove('d-none');
                    cityInput.classList.add('d-none');
                } else {
                    if (citySelect.value && !cityInput.value) {
                        cityInput.value = citySelect.value;
                    }

                    cityInput.name = 'teslimat_il';
                    citySelect.name = '';
                    cityInput.classList.remove('d-none');
                    citySelect.classList.add('d-none');
                }
            };

            const renderShippingOptions = (payload) => {
                const options = Array.isArray(payload.options) ? payload.options : [];
                totalEl.dataset.baseTotal = Number(payload.base_total || 0).toFixed(2);

                if (! options.length) {
                    shippingContainer.innerHTML = `
                        <div class="alert alert-warning mb-0">
                            Bu teslimat adresi için aktif kargo yöntemi bulunamadı. Sipariş sonrası ekip sizinle iletişime geçecektir.
                        </div>
                    `;
                    update();
                    return;
                }

                shippingContainer.innerHTML = `
                    <div class="row g-3">
                        ${options.map((option, index) => `
                            <div class="col-md-6">
                                <label class="checkout-shipping-option" data-shipping-option>
                                    <input
                                        type="radio"
                                        name="kargo_yontemi_id"
                                        value="${option.id}"
                                        class="form-check-input me-2"
                                        data-kargo-ucret="${Number(option.price || 0).toFixed(2)}"
                                        data-kargo-label="${option.price_label}"
                                        ${index === 0 ? 'checked required' : 'required'}
                                    >
                                    <span class="checkout-shipping-main">
                                        <span>
                                            <span class="checkout-shipping-name">${option.name}</span>
                                            <span class="checkout-shipping-meta d-block">
                                                ${option.estimated_delivery}${option.supports_international ? ' · Yurt dışı destekli' : ''}
                                            </span>
                                        </span>
                                        <span class="checkout-shipping-price">${option.price_label}</span>
                                    </span>
                                </label>
                            </div>
                        `).join('')}
                    </div>
                `;

                bindShippingEvents();
            };

            const fetchShippingOptions = () => {
                const country = (countrySelect ? countrySelect.value : 'TR').toUpperCase();
                const city = activeCityValue();
                const postal = postalInput ? postalInput.value : '';
                const currentRequest = ++requestCounter;

                shippingContainer.innerHTML = '<div class="checkout-shipping-loading">Kargo seçenekleri güncelleniyor...</div>';

                const params = new URLSearchParams({
                    teslimat_ulke: country,
                    teslimat_il: city,
                    teslimat_posta_kodu: postal,
                });

                fetch(`{{ route('checkout.shipping-options') }}?${params.toString()}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                })
                    .then((response) => response.json())
                    .then((payload) => {
                        if (currentRequest !== requestCounter) {
                            return;
                        }

                        renderShippingOptions(payload);
                    })
                    .catch(() => {
                        if (currentRequest !== requestCounter) {
                            return;
                        }

                        shippingContainer.innerHTML = `
                            <div class="alert alert-warning mb-0">
                                Kargo seçenekleri güncellenirken bir sorun oluştu. Lütfen adres bilgilerini kontrol edip tekrar deneyin.
                            </div>
                        `;
                        update();
                    });
            };

            const scheduleFetch = () => {
                window.clearTimeout(requestTimer);
                syncPostalHint();
                requestTimer = window.setTimeout(fetchShippingOptions, 250);
            };

            if (countrySelect) {
                countrySelect.addEventListener('change', () => {
                    syncRegionField();
                    scheduleFetch();
                });
            }
            if (cityInput) {
                cityInput.addEventListener('input', scheduleFetch);
                cityInput.addEventListener('change', scheduleFetch);
            }
            if (citySelect) {
                citySelect.addEventListener('change', scheduleFetch);
            }
            if (postalInput) {
                postalInput.addEventListener('input', scheduleFetch);
                postalInput.addEventListener('change', scheduleFetch);
            }

            addressOptions.forEach((option) => {
                const input = option.querySelector('input[name="selected_address_id"]');
                if (! input) {
                    return;
                }

                input.addEventListener('change', () => {
                    if (! input.checked) {
                        return;
                    }

                    const setValue = (selector, value) => {
                        const field = document.querySelector(selector);
                        if (field && value !== undefined) {
                            field.value = value || '';
                            field.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    };

                    setValue('[name="musteri_ad_soyad"]', option.dataset.name);
                    setValue('[name="musteri_telefon"]', option.dataset.phone);
                    setValue('[name="teslimat_ulke"]', option.dataset.country || 'TR');
                    syncRegionField();
                    if (citySelect && !citySelect.classList.contains('d-none')) {
                        citySelect.value = option.dataset.city || '';
                        cityInput.value = option.dataset.city || '';
                    } else {
                        cityInput.value = option.dataset.city || '';
                    }
                    setValue('[name="teslimat_ilce"]', option.dataset.district);
                    setValue('[name="teslimat_posta_kodu"]', option.dataset.postal);
                    setValue('[name="teslimat_adresi"]', option.dataset.address);
                    scheduleFetch();
                });

                if (input.checked) {
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
            paymentOptions.forEach((option) => {
                const radio = option.querySelector('input[name="odeme_yontemi_secimi"]');
                radio?.addEventListener('change', syncPaymentPreview);
            });
            bindShippingEvents();
            syncRegionField();
            syncPostalHint();
            syncPaymentPreview();
            if (! addressOptions.length) {
                scheduleFetch();
            }
        })();
    </script>
@endsection

