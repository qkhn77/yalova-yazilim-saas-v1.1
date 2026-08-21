@extends('front.layouts.app')

@section('title', $urun->seo_title ?: ($urun->ad ?: __('front.product.detail')))
@section('meta_description', $urun->seo_description ?: '')
@section('meta_keywords', $urun->seo_keywords ?: '')
@section('canonical_url', route('products.show', $urun->slug))

@section('og_title', $urun->seo_title ?: ($urun->ad ?: __('front.product.detail')))
@section('og_description', $urun->seo_description ?: '')
@section(
    'og_image',
    !blank($urun->og_gorsel)
        ? asset('uploads/' . ltrim($urun->og_gorsel, '/'))
        : (!blank($urun->kapak_gorsel_yolu) ? asset('uploads/' . ltrim($urun->kapak_gorsel_yolu, '/')) : asset('theme/yalovakamera/images/yalova_kamera.png'))
)

@section('content')
    @php
        $mainImg = !blank($urun->kapak_gorsel_yolu)
            ? asset('uploads/' . ltrim($urun->kapak_gorsel_yolu, '/'))
            : asset('theme/yalovakamera/images/service-image-1.jpg');

        $galeri = collect($urun->galeri_gorsel_yollari ?? [])
            ->filter(fn ($item) => $item && $item !== $urun->kapak_gorsel_yolu)
            ->values();

        $indirimliVar = $urun->indirimli_fiyat !== null && (float) $urun->indirimli_fiyat > 0;
        $fiyatVar = (float) ($urun->satis_fiyati ?? 0) > 0 || (float) ($urun->indirimli_fiyat ?? 0) > 0;
        $fiyatServisi = app(\App\Services\Front\FrontFiyatServisi::class);
        $urunParaBirimi = strtoupper((string) ($urun->para_birimi ?: 'TRY'));
        $urunKdvOrani = (float) ($urun->kdv_orani ?? 0);
        $schemaPrice = $fiyatVar
            ? number_format(
                $fiyatServisi->gosterimTutari((float) ($urun->indirimli_fiyat ?: $urun->satis_fiyati), $urunKdvOrani),
                2,
                '.',
                ''
            )
            : null;

        $stoktaVarMi = ! (bool) $urun->stok_takip || (float) $urun->stok_miktari > 0;
        $whatsappUrl = 'https://wa.me/?text=' . rawurlencode(__('front.product.whatsapp_message', ['product' => $urun->ad]));
        $ecommerceKuralServisi = app(\App\Services\EcommerceModulKuralServisi::class);
        $ecommerceAktifMi = $ecommerceKuralServisi->erisimAcikMi((int) ($urun->firma_id ?? 0));
    @endphp

    <div class="page-header">
        <div class="container">
            <div class="page-header-box">
                <p class="wow fadeInUp mb-0">{{ __('front.product.detail_page') }}</p>
            </div>
        </div>
    </div>

    <div class="product-detail-shell">
        <div class="product-detail-intro">
            <nav class="product-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ url('/') }}">{{ __('front.product.home') }}</a>
                <span class="product-breadcrumb-sep">></span>
                <a href="{{ route('products.index') }}">{{ __('front.product.products_page_title') }}</a>
                @if($urun->kategori?->ad && $urun->kategori?->slug)
                    <span class="product-breadcrumb-sep">></span>
                    <a href="{{ route('products.category', $urun->kategori->slug) }}">{{ $urun->kategori->ad }}</a>
                @endif
                <span class="product-breadcrumb-sep">></span>
                <span title="{{ $urun->ad }}">{{ \Illuminate\Support\Str::limit($urun->ad, 45, '...') }}</span>
            </nav>

            <h1>{{ $urun->ad }}</h1>
            <div class="product-detail-intro-meta">
                {{ $urun->marka?->ad ?: ($urun->marka_uretici ?: 'Marka') }}
                @if($urun->model?->ad || $urun->kisa_ad)
                    · {{ $urun->model?->ad ?: $urun->kisa_ad }}
                @endif
            </div>
        </div>

        <div class="product-detail-wrap">
            <div class="row g-4 align-items-start">
                <div class="col-lg-6">
                    <img id="productMainImage" src="{{ $mainImg }}" alt="{{ $urun->ad }}" class="product-media-main" width="960" height="960" loading="eager" decoding="async" fetchpriority="high">

                    @if($galeri->isNotEmpty())
                        <div class="product-thumbs">
                            <button type="button" class="product-thumb-btn is-active" data-src="{{ $mainImg }}" aria-label="{{ __('front.product.main_image') }}">
                                <img src="{{ $mainImg }}" alt="{{ $urun->ad }} {{ __('front.product.main_image') }}" width="160" height="160" loading="lazy" decoding="async">
                            </button>
                            @foreach($galeri as $item)
                                @php $thumbSrc = asset('uploads/' . ltrim($item, '/')); @endphp
                                <button type="button" class="product-thumb-btn" data-src="{{ $thumbSrc }}">
                                    <img src="{{ $thumbSrc }}" alt="{{ $urun->ad }}" width="160" height="160" loading="lazy" decoding="async">
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="col-lg-6">
                    <div class="product-info-card">
                        <div class="mb-3">
                            @if(! $fiyatVar)
                                <div class="product-price-main">{{ __('Fiyat sorunuz') }}</div>
                            @elseif($indirimliVar)
                                <div class="product-price-main">
                                    {{ $fiyatServisi->satisFiyatiFormatla((float) $urun->indirimli_fiyat, $urunKdvOrani, $urunParaBirimi) }}
                                </div>
                                <div class="product-price-old mt-1">
                                    {{ $fiyatServisi->satisFiyatiFormatla((float) $urun->satis_fiyati, $urunKdvOrani, $urunParaBirimi) }}
                                </div>
                            @else
                                <div class="product-price-main">
                                    {{ $fiyatServisi->satisFiyatiFormatla((float) $urun->satis_fiyati, $urunKdvOrani, $urunParaBirimi) }}
                                </div>
                            @endif
                        </div>

                        @if ($errors->has('stok'))
                            <div class="alert alert-danger">{{ $errors->first('stok') }}</div>
                        @endif
                        @if (session('success'))
                            <div class="alert alert-success">{{ session('success') }}</div>
                        @endif

                        <div class="d-flex gap-2 flex-wrap align-items-center mb-3">
                            @if($stoktaVarMi)
                                <span class="badge bg-success">{{ __('front.product.in_stock') }}</span>
                            @else
                                <span class="badge bg-danger">{{ __('front.product.out_of_stock') }}</span>
                            @endif
                            @if($urun->kategori?->ad)
                                <a href="{{ route('products.category', $urun->kategori->slug) }}" class="badge bg-secondary text-decoration-none">{{ $urun->kategori->ad }}</a>
                            @endif
                        </div>

                        <div class="mb-3 d-flex flex-wrap gap-2">
                            @if($ecommerceAktifMi)
                    <form action="{{ route('cart.add', $urun->slug) }}" method="POST" class="d-flex gap-2" data-cart-add-form>
                                    @csrf
                                    <input type="number" name="miktar" value="1" min="1" step="1" class="form-control" style="max-width: 120px;">
                                    <button type="submit" class="btn-default">{{ __('front.product.add_to_cart') }}</button>
                                </form>
                            @endif
                            <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="btn btn-outline-success">{{ __('front.product.ask_whatsapp') }}</a>
                        </div>

                        <div class="mb-4">
                            <h5 class="mb-2">{{ __('front.product.description') }}</h5>
                            <p class="mb-0 text-muted">{{ $urun->aciklama ?: __('front.product.description_missing') }}</p>
                        </div>

                        <div class="product-meta-grid">
                            <div class="product-meta-item">
                                <strong>{{ __('front.product.brand') }}</strong>
                                {{ $urun->marka?->ad ?: ($urun->marka_uretici ?: '-') }}
                            </div>
                            <div class="product-meta-item">
                                <strong>{{ __('front.product.model') }}</strong>
                                {{ $urun->model?->ad ?: ($urun->kisa_ad ?: '-') }}
                            </div>
                            <div class="product-meta-item">
                                <strong>{{ __('front.product.variant') }}</strong>
                                {{ $urun->varyant?->ad ?: '-' }}
                            </div>
                            <div class="product-meta-item">
                                <strong>{{ __('front.product.currency') }}</strong>
                                {{ $fiyatServisi->aktifParaBirimi() }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="product-quick-highlights">
                <div class="product-highlight-item">
                    <i class="fa-solid fa-truck-fast" aria-hidden="true"></i>
                    <span>{{ __('front.product.feature_fast_shipping') }}</span>
                    <small>{{ __('front.product.feature_fast_shipping_desc') }}</small>
                </div>
                <div class="product-highlight-item">
                    <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                    <span>{{ __('front.product.feature_secure_shopping') }}</span>
                    <small>{{ __('front.product.feature_secure_shopping_desc') }}</small>
                </div>
                <div class="product-highlight-item">
                    <i class="fa-solid fa-headset" aria-hidden="true"></i>
                    <span>{{ __('front.product.feature_support') }}</span>
                    <small>{{ __('front.product.feature_support_desc') }}</small>
                </div>
                <div class="product-highlight-item">
                    <i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i>
                    <span>{{ __('front.product.feature_easy_return') }}</span>
                    <small>{{ __('front.product.feature_easy_return_desc') }}</small>
                </div>
            </div>

            <section class="product-detail-section">
                <h3>{{ __('front.product.technical_info') }}</h3>
                <div class="table-responsive">
                    <table class="table product-spec-table mb-0">
                        <tbody>
                            <tr>
                                <th>{{ __('front.product.product_code') }}</th>
                                <td>{{ $urun->kod ?: '-' }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('front.product.category') }}</th>
                                <td>{{ $urun->kategori?->ad ?: '-' }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('front.product.brand') }}</th>
                                <td>{{ $urun->marka?->ad ?: ($urun->marka_uretici ?: '-') }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('front.product.model') }}</th>
                                <td>{{ $urun->model?->ad ?: ($urun->kisa_ad ?: '-') }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('front.product.stock_status') }}</th>
                                <td>{{ $stoktaVarMi ? __('front.product.in_stock') : __('front.product.out_of_stock') }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('front.product.vat_rate') }}</th>
                                <td>%{{ number_format((float) ($urun->kdv_orani ?? 0), 0, ',', '.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="product-detail-section">
                <h3>{{ __('front.product.purchase_info') }}</h3>
                <div class="accordion product-detail-accordion" id="productInfoAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingDelivery">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseDelivery" aria-expanded="true" aria-controls="collapseDelivery">
                                {{ __('front.product.delivery_shipping') }}
                            </button>
                        </h2>
                        <div id="collapseDelivery" class="accordion-collapse collapse show" aria-labelledby="headingDelivery" data-bs-parent="#productInfoAccordion">
                            <div class="accordion-body">
                                {{ __('front.product.delivery_shipping_desc') }}
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingSupport">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSupport" aria-expanded="false" aria-controls="collapseSupport">
                                {{ __('front.product.installation_support') }}
                            </button>
                        </h2>
                        <div id="collapseSupport" class="accordion-collapse collapse" aria-labelledby="headingSupport" data-bs-parent="#productInfoAccordion">
                            <div class="accordion-body">
                                {{ __('front.product.installation_support_desc') }}
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingWarranty">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseWarranty" aria-expanded="false" aria-controls="collapseWarranty">
                                {{ __('front.product.warranty_return') }}
                            </button>
                        </h2>
                        <div id="collapseWarranty" class="accordion-collapse collapse" aria-labelledby="headingWarranty" data-bs-parent="#productInfoAccordion">
                            <div class="accordion-body">
                                {{ __('front.product.warranty_return_desc') }}
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="product-detail-section">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <h3 class="mb-0">{{ __('front.product.messages_title') }}</h3>
                    @auth
                        <a href="{{ route('account.messages.new', ['konu_tipi' => 'urun', 'stok_karti_id' => $urun->id, 'baslik' => $urun->ad]) }}" class="btn btn-outline-primary btn-sm">
                            {{ __('front.product.ask_question') }}
                        </a>
                    @else
                        <a href="{{ route('buyer.login') }}" class="btn btn-outline-primary btn-sm">
                            {{ __('front.product.login_to_ask') }}
                        </a>
                    @endauth
                </div>

                @if(($urunMesajlari ?? collect())->isEmpty())
                    <div class="text-muted">{{ __('front.product.no_messages') }}</div>
                @else
                    <div class="product-message-list">
                        @foreach($urunMesajlari as $konu)
                            <div class="product-message-card">
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                    <div>
                                        <strong>{{ $konu->baslik }}</strong>
                                        <div class="small text-muted">{{ optional($konu->created_at)->format('d.m.Y') }}</div>
                                    </div>
                                </div>
                                <div class="product-message-thread">
                                    @foreach($konu->mesajlar as $mesaj)
                                        <div class="product-message-bubble {{ $mesaj->gonderen_tipi === 'admin' ? 'admin' : '' }}">
                                            <div><strong>{{ $mesaj->gonderen_tipi === 'admin' ? __('front.product.support') : __('front.product.customer') }}</strong></div>
                                            <div>{{ $mesaj->icerik }}</div>
                                            <div class="product-message-meta">{{ optional($mesaj->created_at)->format('d.m.Y H:i') }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            @if(($benzerUrunler ?? collect())->isNotEmpty())
                <section class="product-detail-section">
                    <h3>{{ __('front.product.similar_products') }}</h3>
                    <div class="product-similar-grid">
                        @foreach($benzerUrunler as $item)
                            @include('front.urunler.partials.urun-karti', ['urun' => $item])
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Product",
  "name": @json($urun->ad),
  "url": @json(route('products.show', $urun->slug)),
  "offers": {
    "@type": "Offer",
    @if($schemaPrice !== null)
    "priceCurrency": @json(strtoupper((string) ($urun->para_birimi ?: 'TRY'))),
    "price": @json($schemaPrice),
    @endif
    "availability": @json((! (bool) $urun->stok_takip || (float) $urun->stok_miktari > 0) ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock')
  }
}
</script>


@endpush


