@extends('front.layouts.app')

@section('title', __('front.product.products_page_title') . ' | Yalova Kamera')
@section('meta_description', __('front.product.products_page_title'))

@section('content')

    <div class="page-header">
        <div class="container">
            <div class="page-header-box">
                <h1 class="wow fadeInUp">{{ __('front.product.products_page_title') }}</h1>
            </div>
        </div>
    </div>

    <div class="urun-shell">
        <div class="urun-layout">
            <aside class="urun-sidebar d-none d-lg-block">
                <h4>{{ __('front.product.categories') }}</h4>

                @if(!empty($kategoriAgaci))
                    @include('front.urunler.partials.kategori-agaci', [
                        'nodes' => $kategoriAgaci,
                        'selectedSlug' => $seciliKategoriSlug,
                        'level' => 1,
                    ])
                @else
                    <p class="mb-0">{{ __('front.product.no_active_category') }}</p>
                @endif
            </aside>

            <section class="urun-content">
                <div class="urun-content-head">
                    <h2>{{ __('front.product.all_products') }}</h2>
                    <span class="urun-count">{{ __('front.product.products_found', ['count' => $urunler->total()]) }}</span>
                </div>

                @include('front.urunler.partials.filtre-siralama', [
                    'action' => route('products.index'),
                    'filtreler' => $filtreler ?? [],
                    'offcanvasId' => 'urunFiltreOffcanvasIndex',
                ])

                @if($urunler->isNotEmpty())
                    <div class="urun-grid js-urun-grid" data-cols="3">
                        @foreach($urunler as $urun)
                            @include('front.urunler.partials.urun-karti', ['urun' => $urun])
                        @endforeach
                    </div>
                    <div class="urun-skeleton-grid js-urun-skeleton-grid">
                        @for($i = 0; $i < 8; $i++)
                            <div class="urun-skeleton-item"></div>
                        @endfor
                    </div>
                @else
                    <div class="text-center py-5">
                        <p class="mb-0">{{ __('front.product.no_publishable_product') }}</p>
                    </div>
                @endif

                <div class="mt-4">
                    {{ $urunler->appends(request()->query())->withPath(route('products.index'))->links('pagination::bootstrap-5') }}
                </div>
            </section>
        </div>
    </div>

    <div class="offcanvas offcanvas-start" tabindex="-1" id="urunFiltreOffcanvasIndex" aria-labelledby="urunFiltreOffcanvasIndexLabel">
        <div class="offcanvas-header">
            <h5 id="urunFiltreOffcanvasIndexLabel" class="offcanvas-title">{{ __('front.product.categories') }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ __('front.product.close') }}"></button>
        </div>
        <div class="offcanvas-body">
            @if(!empty($kategoriAgaci))
                @include('front.urunler.partials.kategori-agaci', [
                    'nodes' => $kategoriAgaci,
                    'selectedSlug' => $seciliKategoriSlug,
                    'level' => 1,
                ])
            @else
                <p class="mb-0">{{ __('front.product.no_active_category') }}</p>
            @endif
        </div>
    </div>
@endsection


