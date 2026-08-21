@extends('front.layouts.app')

@section('title', $product->seoTitle())
@section('meta_description', $product->seoDescription())

@php
    $mainImage = $product->image_url ?: app(\App\Services\FrontThemeService::class)->fallbackImage('theme/yalovakamera/images/service-image-1.jpg');
    $gallery = collect($product->gallery ?? [])->filter()->values();
    $galleryImages = collect([$mainImage])->merge(
        $gallery->map(fn ($item) => asset('uploads/' . ltrim($item, '/')))
    )->unique()->values();
    $whatsAppPhone = preg_replace('/\D+/', '', \App\Models\Setting::get('phone', '902263520724'));
    $infoText = rawurlencode(__('front.product.whatsapp_message', ['product' => $product->name]));
    $offerText = rawurlencode(__('front.product.whatsapp_offer_message', ['product' => $product->name]));
    $ecommerceKuralServisi = app(\App\Services\EcommerceModulKuralServisi::class);
    $ecommerceAktifMi = $ecommerceKuralServisi->erisimAcikMi((int) ($product->firma_id ?: $ecommerceKuralServisi->firmaIdBelirle(request())));
    $stoktaVarMi = ($product->stock_badge['class'] ?? '') !== 'danger';
    $fiyatServisi = app(\App\Services\Front\FrontFiyatServisi::class);
    $productParaBirimi = strtoupper((string) ($product->para_birimi ?: 'TRY'));

    \App\Helpers\BreadcrumbHelper::clear();
    \App\Helpers\BreadcrumbHelper::add(__('front.product.home'), '/');
    \App\Helpers\BreadcrumbHelper::add(__('front.product.products_page_title'), route('products.index'));
    if ($product->category) {
        \App\Helpers\BreadcrumbHelper::add($product->category->name, route('products.category', $product->category->slug));
    }
    \App\Helpers\BreadcrumbHelper::add($product->name);
@endphp

@section('content')
    <div class="page-header">
        <div class="container">
            <div class="page-header-box">
                <h1 class="wow fadeInUp">{{ $product->name }}</h1>
                {!! \App\Helpers\BreadcrumbHelper::render() !!}
            </div>
        </div>
    </div>

    <div class="container py-5">
        <div class="row">
            <div class="col-lg-6 mb-4">
                <a href="{{ $galleryImages->first() }}" class="product-gallery-lightbox d-block mb-3" data-gallery="product-gallery">
                    <img id="mainProductImage" src="{{ $galleryImages->first() }}" alt="{{ $product->name }}" class="img-fluid rounded" loading="eager" decoding="async" fetchpriority="high">
                </a>
                @if($galleryImages->count() > 1)
                    <div class="row g-2 product-thumbnails">
                        @foreach($galleryImages as $imageUrl)
                            <div class="col-3">
                                <a href="{{ $imageUrl }}" class="product-gallery-lightbox d-block" data-gallery="product-gallery">
                                    <img
                                        src="{{ $imageUrl }}"
                                        alt="{{ $product->name }}"
                                        class="img-fluid rounded product-thumb {{ $loop->first ? 'is-active' : '' }}"
                                        data-main-src="{{ $imageUrl }}"
                                        loading="lazy"
                                        decoding="async"
                                    >
                                </a>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="col-lg-6">
                @if($product->category)
                    <a href="{{ route('products.category', $product->category->slug) }}" class="badge bg-secondary text-decoration-none mb-2">{{ $product->category->name }}</a>
                @endif

                @if($product->short_description)
                    <p class="lead">{{ $product->short_description }}</p>
                @endif

                <p>
                    @if($product->final_price)
                        <strong class="fs-4">{{ $fiyatServisi->cevirVeFormatla((float) $product->final_price, $productParaBirimi) }}</strong>
                        @if($product->discounted_price && $product->price)
                            <small class="text-muted text-decoration-line-through ms-2">{{ $fiyatServisi->cevirVeFormatla((float) $product->price, $productParaBirimi) }}</small>
                            @if($product->discount_percentage)
                                <span class="badge bg-danger ms-2">{{ __('front.product.discount_badge', ['rate' => $product->discount_percentage]) }}</span>
                            @endif
                        @endif
                    @else
                        <strong class="fs-5">{{ __('front.product.contact_for_price') }}</strong>
                    @endif
                </p>

                <p>
                    <span class="badge bg-{{ $product->stock_badge['class'] }}">{{ $product->stock_badge['label'] }}</span>
                </p>

                <div class="d-flex gap-2 flex-wrap mt-4">
                    @if($ecommerceAktifMi && $stoktaVarMi)
                        <form action="{{ route('cart.add', $product->slug) }}" method="POST" data-cart-add-form>
                            @csrf
                            <input type="hidden" name="miktar" value="1">
                            <button type="submit" class="btn-default">{{ __('front.product.add_to_cart') }}</button>
                        </form>
                    @endif
                    <a href="https://wa.me/{{ $whatsAppPhone }}?text={{ $infoText }}" target="_blank" rel="noopener" class="btn-default">{{ __('front.product.info_button') }}</a>
                    <a href="https://wa.me/{{ $whatsAppPhone }}?text={{ $offerText }}" target="_blank" rel="noopener" class="btn-default">{{ __('front.product.offer_button') }}</a>
                    <a href="https://wa.me/?text={{ rawurlencode(route('products.show', $product->slug)) }}" target="_blank" rel="noopener" class="btn-default">{{ __('front.product.share_button') }}</a>
                </div>
            </div>
        </div>

        <div class="row mt-5">
            <div class="col-12">
                <ul class="nav nav-tabs" id="productTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#aciklama" type="button" role="tab">{{ __('front.product.description_tab') }}</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#ozellikler" type="button" role="tab">{{ __('front.product.specs_tab') }}</button>
                    </li>
                </ul>
                <div class="tab-content border border-top-0 p-4">
                    <div class="tab-pane fade show active" id="aciklama" role="tabpanel">
                        {!! $product->description ?: '<p>' . e(__('front.product.detail_description_missing')) . '</p>' !!}
                    </div>
                    <div class="tab-pane fade" id="ozellikler" role="tabpanel">
                        @if(!empty($product->technical_specs))
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    @foreach($product->technical_specs as $key => $value)
                                        <tr>
                                            <th>{{ $key }}</th>
                                            <td>{{ is_array($value) ? implode(', ', $value) : $value }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            </div>
                        @else
                            <p>{{ __('front.product.specs_missing') }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if($similarProducts->isNotEmpty())
            <div class="row mt-5">
                <div class="col-12 mb-3">
                    <h3>{{ __('front.product.similar_products') }}</h3>
                </div>
                @foreach($similarProducts as $item)
                    <div class="col-lg-3 col-md-6 mb-4">
                        @include('front.products.partials.card', ['product' => $item])
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Product",
      "name": "{{ $product->name }}",
      "description": "{{ strip_tags($product->seoDescription()) }}",
      "image": ["{{ $mainImage }}"]
    }
    </script>
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "BreadcrumbList",
      "itemListElement": [
        {
          "@type": "ListItem",
          "position": 1,
          "name": "{{ __('front.product.home') }}",
          "item": "{{ route('home') }}"
        },
        {
          "@type": "ListItem",
          "position": 2,
          "name": "{{ __('front.product.products_page_title') }}",
          "item": "{{ route('products.index') }}"
        },
        {
          "@type": "ListItem",
          "position": 3,
          "name": "{{ $product->name }}",
          "item": "{{ route('products.show', $product->slug) }}"
        }
      ]
    }
    </script>
    <style>
        .product-thumb {
            cursor: pointer;
            border: 2px solid transparent;
            transition: all .2s ease;
            opacity: .85;
        }
        .product-thumb:hover { opacity: 1; }
        .product-thumb.is-active {
            border-color: #0d6efd;
            opacity: 1;
        }
    </style>
    @push('scripts')
    <script>
        (function () {
            const mainImage = document.getElementById('mainProductImage');
            const thumbs = document.querySelectorAll('.product-thumb');
            if (mainImage && thumbs.length) {
                thumbs.forEach((thumb) => {
                    thumb.addEventListener('click', function () {
                        const src = this.getAttribute('data-main-src');
                        if (!src) return;
                        mainImage.setAttribute('src', src);
                        const mainLink = mainImage.closest('a.product-gallery-lightbox');
                        if (mainLink) mainLink.setAttribute('href', src);
                        thumbs.forEach((t) => t.classList.remove('is-active'));
                        this.classList.add('is-active');
                    });
                });
            }

            if (window.jQuery && jQuery.fn.magnificPopup) {
                jQuery('.product-thumbnails').each(function () {
                    jQuery(this).magnificPopup({
                        delegate: 'a.product-gallery-lightbox',
                        type: 'image',
                        gallery: { enabled: true }
                    });
                });
                jQuery('a.product-gallery-lightbox').first().magnificPopup({
                    type: 'image',
                    gallery: { enabled: true }
                });
            }
        })();
    </script>
    @endpush
@endsection

