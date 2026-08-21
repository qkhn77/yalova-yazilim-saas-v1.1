@extends('front.layouts.app')

@section('title', __('front.account.addresses_title'))

@section('content')
    @php
        $acikForm = old('form_context');
        $adresEkleAcik = $acikForm === 'new-address';
    @endphp

    <div class="page-header">
        <div class="container">
            <div class="page-header-box">
                <h1 class="wow fadeInUp">{{ __('front.account.addresses_title') }}</h1>
            </div>
        </div>
    </div>

    <div class="container py-5">
        <div class="account-page-shell">
            <div class="account-page-frame">
                @include('front.hesabim.partials.nav')

                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                @if ($errors->any() && ! $acikForm)
                    <div class="alert alert-danger">
                        <strong>Adres bilgilerini kontrol edin.</strong>
                        <ul class="mb-0 mt-2 ps-3">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="card account-inner-card p-3 p-md-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                        <div>
                            <h5 class="mb-1">Kayıtlı Adreslerim</h5>
                            <div class="text-muted small">Adreslerinizi tablodan yönetebilir, varsayılan teslimat veya fatura adresi olarak seçebilirsiniz.</div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#adres-ekle-alani" aria-expanded="false" aria-controls="adres-ekle-alani">
                                Adres Ekle
                            </button>
                            <form id="adres-toplu-sil-form" method="POST" action="{{ route('account.addresses.bulk-destroy') }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Seçili adresleri silmek istiyor musunuz?')">
                                    Seçili Adresleri Sil
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="collapse {{ $adresEkleAcik ? 'show' : '' }} mb-4" id="adres-ekle-alani">
                        <div class="border rounded-4 p-3 p-md-4 bg-white">
                            <h6 class="mb-3">Yeni Adres Ekle</h6>
                            <form method="POST" action="{{ route('account.addresses.store') }}">
                                @csrf
                                <input type="hidden" name="form_context" value="new-address">
                                @if($adresEkleAcik && $errors->any())
                                    <div class="alert alert-danger">
                                        <strong>Adresi kaydedebilmek için eksik bilgileri tamamlayın.</strong>
                                        <ul class="mb-0 mt-2 ps-3">
                                            @foreach($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                                @include('front.hesabim.partials.adres-form', [
                                    'formId' => 'new-delivery-address',
                                    'adresTipi' => \App\Models\Ecommerce\EcommerceKullaniciAdresi::TIP_TESLIMAT,
                                    'teslimatVarsayilanGoster' => true,
                                    'faturaBilgileriGoster' => false,
                                ])
                                <div class="mt-3">
                                    <button type="submit" class="btn btn-primary">Adresi Kaydet</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    @if($faturaCari && trim((string) ($faturaCari->adres ?? '')) !== '')
                        <div class="alert alert-light border mb-3">
                            <strong>Fatura adresi:</strong>
                            {{ trim(implode(' ', array_filter([
                                (string) ($faturaCari->adres ?? ''),
                                (string) ($faturaCari->ilce ?? ''),
                                (string) ($faturaCari->il ?? ''),
                                (string) ($faturaCari->posta_kodu ?? ''),
                            ]))) }}
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                            <tr>
                                <th style="width: 42px;">
                                    <input class="form-check-input" type="checkbox" data-adres-secim-tumu aria-label="Tüm adresleri seç">
                                </th>
                                <th>Başlık</th>
                                <th>Alıcı</th>
                                <th>Adres</th>
                                <th>Durum</th>
                                <th class="text-end">İşlemler</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($teslimatAdresleri as $adres)
                                <tr>
                                    <td>
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            name="adres_ids[]"
                                            value="{{ $adres->id }}"
                                            form="adres-toplu-sil-form"
                                            data-adres-secim
                                            aria-label="{{ $adres->baslik }} adresini seç"
                                        >
                                    </td>
                                    <td>
                                        <strong>{{ $adres->baslik }}</strong>
                                        <div class="small text-muted">{{ $adres->ulke_kodu ?: 'TR' }}</div>
                                    </td>
                                    <td>
                                        {{ $adres->ad_soyad }}
                                        <div class="small text-muted">{{ $adres->telefon }}</div>
                                    </td>
                                    <td>
                                        <div>{{ $adres->ilce }} / {{ $adres->sehir }}</div>
                                        <div class="small text-muted">
                                            {{ trim(implode(' ', array_filter([
                                                (string) ($adres->mahalle ?? ''),
                                                (string) ($adres->acik_adres ?? ''),
                                                (string) ($adres->posta_kodu ?? ''),
                                            ]))) }}
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            @if($adres->varsayilan_teslimat_mi)
                                                <span class="badge bg-primary">{{ __('front.account.default_delivery') }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex flex-wrap justify-content-end gap-2">
                                            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#adres-duzenle-{{ $adres->id }}" aria-expanded="false" aria-controls="adres-duzenle-{{ $adres->id }}">
                                                Düzenle
                                            </button>

                                            @unless($adres->varsayilan_teslimat_mi)
                                                <form method="POST" action="{{ route('account.addresses.default-delivery', ['adres' => $adres->id]) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary">Varsayılan Teslimat</button>
                                                </form>
                                            @endunless

                                            <form method="POST" action="{{ route('account.addresses.make-invoice', ['adres' => $adres->id]) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">Fatura Adresi Yap</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                @php
                                    $duzenlemeFormuAcik = $acikForm === 'edit-address-'.$adres->id;
                                @endphp
                                <tr class="collapse {{ $duzenlemeFormuAcik ? 'show' : '' }}" id="adres-duzenle-{{ $adres->id }}">
                                    <td colspan="6" class="bg-light">
                                        <div class="p-3 p-md-4">
                                            <h6 class="mb-3">{{ $adres->baslik }} adresini düzenle</h6>
                                            <form method="POST" action="{{ route('account.addresses.update', ['adres' => $adres->id]) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="form_context" value="edit-address-{{ $adres->id }}">
                                                @if($duzenlemeFormuAcik && $errors->any())
                                                    <div class="alert alert-danger">
                                                        <strong>Adresi güncelleyebilmek için eksik bilgileri tamamlayın.</strong>
                                                        <ul class="mb-0 mt-2 ps-3">
                                                            @foreach($errors->all() as $error)
                                                                <li>{{ $error }}</li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                @endif
                                                @include('front.hesabim.partials.adres-form', [
                                                    'adres' => $adres,
                                                    'formId' => 'delivery-address-'.$adres->id,
                                                    'adresTipi' => \App\Models\Ecommerce\EcommerceKullaniciAdresi::TIP_TESLIMAT,
                                                    'teslimatVarsayilanGoster' => true,
                                                    'faturaBilgileriGoster' => false,
                                                ])
                                                <div class="mt-3 d-flex flex-wrap gap-2">
                                                    <button type="submit" class="btn btn-primary">{{ __('front.account.update_address') }}</button>
                                                    <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#adres-duzenle-{{ $adres->id }}">
                                                        Kapat
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">
                                        <div class="alert alert-info mb-0">{{ __('front.account.no_addresses') }}</div>
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const all = document.querySelector('[data-adres-secim-tumu]');
            const items = document.querySelectorAll('[data-adres-secim]');

            if (! all || ! items.length) {
                return;
            }

            all.addEventListener('change', function () {
                items.forEach(function (item) {
                    item.checked = all.checked;
                });
            });
        });
    </script>
@endsection
