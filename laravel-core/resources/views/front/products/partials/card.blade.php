@php
    $image = $product->image_url ?: app(\App\Services\FrontThemeService::class)->fallbackImage('theme/yalovakamera/images/service-image-1.jpg');
    $badge = $product->stock_badge;
    $ecommerceKuralServisi = app(\App\Services\EcommerceModulKuralServisi::class);
    $kartFirmaId = (int) ($product->firma_id ?: $ecommerceKuralServisi->firmaIdBelirle(request()));
    $ecommerceAktifMi = $ecommerceKuralServisi->erisimAcikMi($kartFirmaId);
    $stoktaVarMi = ($product->stock_badge['class'] ?? '') !== 'danger';
    $fiyatServisi = app(\App\Services\Front\FrontFiyatServisi::class);
    $productParaBirimi = strtoupper((string) ($product->para_birimi ?: 'TRY'));
@endphp

<div class="service-item wow fadeInUp h-100">
    <div class="service-image">
        <a href="{{ route('products.show', $product->slug) }}">
            <figure class="image-anime">
                <img
                    src="{{ $image }}"
                    alt="{{ $product->name }}"
                    loading="lazy"
                    decoding="async"
                >
            </figure>
        </a>
        @if($product->is_featured)
            <span class="badge bg-warning text-dark position-absolute m-3">{{ __('front.product.featured') }}</span>
        @endif
    </div>
    <div class="service-content p-3">
        @if($product->category)
            <a href="{{ route('products.category', $product->category->slug) }}" class="badge bg-secondary text-decoration-none mb-2">{{ $product->category->name }}</a>
        @endif
        <h3><a href="{{ route('products.show', $product->slug) }}">{{ $product->name }}</a></h3>
        @if($product->short_description)
            <p>{{ \Illuminate\Support\Str::limit(strip_tags($product->short_description), 110) }}</p>
        @endif
        <div class="d-flex align-items-center justify-content-between mt-3">
            <div>
                @if($product->final_price)
                    <strong>{{ $fiyatServisi->cevirVeFormatla((float) $product->final_price, $productParaBirimi) }}</strong>
                    @if($product->discounted_price && $product->price)
                        <small class="text-muted text-decoration-line-through ms-1">{{ $fiyatServisi->cevirVeFormatla((float) $product->price, $productParaBirimi) }}</small>
                    @endif
                @else
                    <strong>{{ __('front.product.price_on_request') }}</strong>
                @endif
            </div>
            <span class="badge bg-{{ $badge['class'] }}">{{ $badge['label'] }}</span>
        </div>
        <div class="mt-3">
            <a href="{{ route('products.show', $product->slug) }}" class="btn-default btn-sm">{{ __('front.product.detail') }}</a>
            @if($ecommerceAktifMi && $stoktaVarMi)
                <form action="{{ route('cart.add', $product->slug) }}" method="POST" class="d-inline" data-cart-add-form>
                    @csrf
                    <input type="hidden" name="miktar" value="1">
                    <button type="submit" class="btn-default btn-sm">{{ __('front.product.add_to_cart') }}</button>
                </form>
            @endif
        </div>
    </div>
</div>

