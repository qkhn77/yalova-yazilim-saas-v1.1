@php
    $fallbackImg = asset('theme/yalovakamera/images/service-image-1.jpg');
    $kapakYol = (string) ($urun->kapak_gorsel_yolu ?? '');
    $img = $kapakYol !== ''
        ? asset('uploads/' . ltrim($kapakYol, '/'))
        : $fallbackImg;

    $galeri = collect($urun->galeri_gorsel_yollari ?? [])
        ->filter(fn ($path) => is_string($path) && trim($path) !== '')
        ->values();

    $ikincilYol = $galeri
        ->first(fn ($path) => (string) $path !== $kapakYol);

    $secondImg = is_string($ikincilYol) && $ikincilYol !== ''
        ? asset('uploads/' . ltrim($ikincilYol, '/'))
        : null;

    $extraGorselSayisi = max(0, $galeri->count() - 1);

    $indirimli = $urun->indirimli_fiyat !== null && (float) $urun->indirimli_fiyat > 0;
    $fiyatVar = (float) ($urun->satis_fiyati ?? 0) > 0 || (float) ($urun->indirimli_fiyat ?? 0) > 0;
    $stoktaVarMi = ! (bool) ($urun->stok_takip ?? false) || (float) ($urun->stok_miktari ?? 0) > 0;
    $ecommerceKuralServisi = app(\App\Services\EcommerceModulKuralServisi::class);
    $kartFirmaId = (int) ($urun->firma_id ?: $ecommerceKuralServisi->firmaIdBelirle(request()));
    $ecommerceAktifMi = $ecommerceKuralServisi->erisimAcikMi($kartFirmaId);
    $fiyatServisi = app(\App\Services\Front\FrontFiyatServisi::class);
    $urunParaBirimi = strtoupper((string) ($urun->para_birimi ?: 'TRY'));
    $urunKdvOrani = (float) ($urun->kdv_orani ?? 0);
@endphp

<article class="urun-card{{ $secondImg ? ' has-secondary-image' : '' }}">
    <a href="{{ route('products.show', $urun->slug) }}" class="urun-card-media" aria-label="{{ $urun->ad }}">
        <span class="urun-card-media-frame">
            <img
                src="{{ $img }}"
                class="urun-card-thumb urun-card-thumb-primary"
                alt="{{ $urun->ad }}"
                loading="lazy"
                decoding="async"
                width="640"
                height="640"
            >
            @if($secondImg)
                <img
                    src="{{ $secondImg }}"
                    class="urun-card-thumb urun-card-thumb-secondary"
                    alt="{{ $urun->ad }} ek görsel"
                    loading="lazy"
                    decoding="async"
                    width="640"
                    height="640"
                >
            @endif
            @if($extraGorselSayisi > 0)
                <span class="urun-card-gallery-badge">+{{ $extraGorselSayisi }}</span>
            @endif
        </span>
    </a>

    <div class="urun-card-body">
        @if($urun->kategori?->ad)
            <span class="urun-card-cat">{{ $urun->kategori->ad }}</span>
        @endif

        <h3 class="urun-card-title">
            <a href="{{ route('products.show', $urun->slug) }}">{{ $urun->ad }}</a>
        </h3>

        <div class="urun-card-price">
            @if(! $fiyatVar)
                <strong>{{ __('Fiyat sorunuz') }}</strong>
            @elseif($indirimli)
                <span class="old">{{ $fiyatServisi->satisFiyatiFormatla((float) $urun->satis_fiyati, $urunKdvOrani, $urunParaBirimi) }}</span>
                <strong>{{ $fiyatServisi->satisFiyatiFormatla((float) $urun->indirimli_fiyat, $urunKdvOrani, $urunParaBirimi) }}</strong>
            @else
                <strong>{{ $fiyatServisi->satisFiyatiFormatla((float) $urun->satis_fiyati, $urunKdvOrani, $urunParaBirimi) }}</strong>
            @endif
        </div>

        <div class="d-flex gap-2 flex-wrap mt-1">
            <a class="urun-card-link" href="{{ route('products.show', $urun->slug) }}">{{ __('front.product.review_detail') }}</a>
            @if($ecommerceAktifMi)
                @if($stoktaVarMi)
                    <form action="{{ route('cart.add', $urun->slug) }}" method="POST" class="d-inline-flex" data-cart-add-form>
                        @csrf
                        <input type="hidden" name="miktar" value="1">
                        <button type="submit" class="urun-card-link border-0 bg-transparent p-0">{{ __('front.product.add_to_cart') }}</button>
                    </form>
                @else
                    <span class="urun-card-link text-muted">{{ __('front.product.out_of_stock') }}</span>
                @endif
            @endif
        </div>
    </div>
</article>
