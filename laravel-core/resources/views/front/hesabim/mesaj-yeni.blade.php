@extends('front.layouts.app')

@section('title', __('front.account.new_message'))

@section('content')
    <div class="page-header">
        <div class="container">
            <div class="page-header-box">
                <h1 class="wow fadeInUp">{{ __('front.account.new_message') }}</h1>
            </div>
        </div>
    </div>

    <div class="container py-5">
        @include('front.hesabim.partials.nav')

        <div class="card p-3 p-md-4">
            <form method="POST" action="{{ route('account.messages.store') }}">
                @csrf

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="konu_tipi">{{ __('front.account.message_type') }}</label>
                        <select id="konu_tipi" name="konu_tipi" class="form-select">
                            <option value="musteri" @selected(old('konu_tipi', $seciliKonuTipi ?: 'musteri') === 'musteri')>{{ __('front.account.message_type_customer') }}</option>
                            <option value="urun" @selected(old('konu_tipi', $seciliKonuTipi) === 'urun')>{{ __('front.account.message_type_product') }}</option>
                        </select>
                        @error('konu_tipi')
                            <div class="text-danger mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-8">
                        <label class="form-label" for="stok_karti_id">{{ __('front.account.message_product_optional') }}</label>
                        <select id="stok_karti_id" name="stok_karti_id" class="form-select">
                            <option value="">{{ __('front.account.message_product_placeholder') }}</option>
                            @foreach($urunler as $urun)
                                <option value="{{ $urun->id }}" @selected((string) old('stok_karti_id', $seciliUrunId) === (string) $urun->id)>{{ $urun->ad }}</option>
                            @endforeach
                        </select>
                        @error('stok_karti_id')
                            <div class="text-danger mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="siparis_id">{{ __('front.account.message_order_optional') }}</label>
                        <select id="siparis_id" name="siparis_id" class="form-select">
                            <option value="">{{ __('front.account.message_order_placeholder') }}</option>
                            @foreach($siparisler as $siparis)
                                <option value="{{ $siparis->id }}" @selected((string) old('siparis_id') === (string) $siparis->id)>
                                    {{ $siparis->siparis_no }} - {{ optional($siparis->created_at)->format('d.m.Y') }}
                                </option>
                            @endforeach
                        </select>
                        @error('siparis_id')
                            <div class="text-danger mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-12">
                        <label class="form-label" for="baslik">{{ __('front.account.subject') }}</label>
                        <input id="baslik" name="baslik" class="form-control" value="{{ old('baslik', $seciliBaslik) }}" required>
                        @error('baslik')
                            <div class="text-danger mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-12">
                        <label class="form-label" for="icerik">{{ __('front.account.message_body') }}</label>
                        <textarea id="icerik" name="icerik" rows="6" class="form-control" required>{{ old('icerik') }}</textarea>
                        @error('icerik')
                            <div class="text-danger mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mt-4 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">{{ __('front.account.send_message') }}</button>
                    <a href="{{ route('account.messages') }}" class="btn btn-outline-secondary">{{ __('front.account.back_to_messages') }}</a>
                </div>
            </form>
        </div>
    </div>
@endsection
