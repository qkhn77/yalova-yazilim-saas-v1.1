@php
    $adres = $adres ?? null;
    $adresTipi = $adresTipi ?? ($adres->adres_tipi ?? \App\Models\Ecommerce\EcommerceKullaniciAdresi::TIP_TESLIMAT);
    $teslimatVarsayilanGoster = $teslimatVarsayilanGoster ?? $adresTipi === \App\Models\Ecommerce\EcommerceKullaniciAdresi::TIP_TESLIMAT;
    $faturaBilgileriGoster = $faturaBilgileriGoster ?? $adresTipi === \App\Models\Ecommerce\EcommerceKullaniciAdresi::TIP_FATURA;
@endphp

<input type="hidden" name="adres_tipi" value="{{ $adresTipi }}">

<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label" for="baslik-{{ $formId }}">{{ __('front.account.address_title') }}</label>
        <input id="baslik-{{ $formId }}" name="baslik" class="form-control @error('baslik') is-invalid @enderror" value="{{ old('baslik', $adres->baslik ?? '') }}" required>
        @error('baslik')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label" for="ad-soyad-{{ $formId }}">{{ __('front.account.recipient_name') }}</label>
        <input id="ad-soyad-{{ $formId }}" name="ad_soyad" class="form-control @error('ad_soyad') is-invalid @enderror" value="{{ old('ad_soyad', $adres->ad_soyad ?? '') }}" required>
        @error('ad_soyad')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label" for="telefon-{{ $formId }}">{{ __('front.account.phone') }}</label>
        <input id="telefon-{{ $formId }}" name="telefon" class="form-control @error('telefon') is-invalid @enderror" value="{{ old('telefon', $adres->telefon ?? '') }}" required>
        @error('telefon')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    @if($faturaBilgileriGoster)
        <div class="col-md-6">
            <label class="form-label" for="vergi-dairesi-{{ $formId }}">Vergi Dairesi</label>
            <input id="vergi-dairesi-{{ $formId }}" name="vergi_dairesi" class="form-control @error('vergi_dairesi') is-invalid @enderror" value="{{ old('vergi_dairesi', $adres->vergi_dairesi ?? '') }}">
            @error('vergi_dairesi')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <label class="form-label" for="vergi-no-{{ $formId }}">Vergi Numarası</label>
            <input id="vergi-no-{{ $formId }}" name="vergi_no" class="form-control @error('vergi_no') is-invalid @enderror" value="{{ old('vergi_no', $adres->vergi_no ?? '') }}">
            @error('vergi_no')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    @endif

    <div class="col-md-2">
        <label class="form-label" for="ulke-kodu-{{ $formId }}">{{ __('front.account.country') }}</label>
        <select id="ulke-kodu-{{ $formId }}" name="ulke_kodu" class="form-control @error('ulke_kodu') is-invalid @enderror" required>
            @foreach(($ulkeSecenekleri ?? ['TR' => 'Türkiye']) as $kod => $ad)
                <option value="{{ $kod }}" @selected(old('ulke_kodu', $adres->ulke_kodu ?? 'TR') === $kod)>{{ $ad }}</option>
            @endforeach
        </select>
        @error('ulke_kodu')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label" for="sehir-{{ $formId }}">{{ __('front.account.city') }}</label>
        <input id="sehir-{{ $formId }}" name="sehir" class="form-control @error('sehir') is-invalid @enderror" value="{{ old('sehir', $adres->sehir ?? '') }}" required>
        @error('sehir')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label" for="ilce-{{ $formId }}">{{ __('front.account.district') }}</label>
        <input id="ilce-{{ $formId }}" name="ilce" class="form-control @error('ilce') is-invalid @enderror" value="{{ old('ilce', $adres->ilce ?? '') }}" required>
        @error('ilce')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-2">
        <label class="form-label" for="posta-kodu-{{ $formId }}">{{ __('front.account.postal_code') }}</label>
        <input id="posta-kodu-{{ $formId }}" name="posta_kodu" class="form-control @error('posta_kodu') is-invalid @enderror" value="{{ old('posta_kodu', $adres->posta_kodu ?? '') }}">
        @error('posta_kodu')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-12">
        <label class="form-label" for="mahalle-{{ $formId }}">{{ __('front.account.neighborhood') }}</label>
        <input id="mahalle-{{ $formId }}" name="mahalle" class="form-control @error('mahalle') is-invalid @enderror" value="{{ old('mahalle', $adres->mahalle ?? '') }}">
        @error('mahalle')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-12">
        <label class="form-label" for="acik-adres-{{ $formId }}">{{ __('front.account.full_address') }}</label>
        <textarea id="acik-adres-{{ $formId }}" name="acik_adres" rows="3" class="form-control @error('acik_adres') is-invalid @enderror" required>{{ old('acik_adres', $adres->acik_adres ?? '') }}</textarea>
        @error('acik_adres')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-12">
        <label class="form-label" for="adres-notu-{{ $formId }}">{{ __('front.account.address_note') }}</label>
        <textarea id="adres-notu-{{ $formId }}" name="adres_notu" rows="2" class="form-control @error('adres_notu') is-invalid @enderror">{{ old('adres_notu', $adres->adres_notu ?? '') }}</textarea>
        @error('adres_notu')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    @if($teslimatVarsayilanGoster)
        <div class="col-md-12 d-flex flex-wrap gap-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="varsayilan-teslimat-{{ $formId }}" name="varsayilan_teslimat_mi" value="1" @checked((int) old('varsayilan_teslimat_mi', (int) ($adres->varsayilan_teslimat_mi ?? 0)) === 1)>
                <label class="form-check-label" for="varsayilan-teslimat-{{ $formId }}">{{ __('front.account.set_default_delivery') }}</label>
            </div>
        </div>
    @endif
</div>
