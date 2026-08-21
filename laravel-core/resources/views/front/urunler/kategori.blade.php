@extends('front.layouts.app')

@section('title', ($kategori->ad ?: __('front.product.category')) . ' | ' . __('front.product.products_page_title') . ' | Yalova Kamera')
@section('meta_description', ($kategori->aciklama ?: __('front.product.category_products_title', ['category' => $kategori->ad])))

@section('content')

    <div class="page-header">
        <div class="container">
            <div class="page-header-box">
                <h1 class="wow fadeInUp">{{ __('front.product.category_products_title', ['category' => $kategori->ad]) }}</h1>
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

            <section class="urun-content urun-content--category">
                <div class="urun-content-head">
                    <h2>{{ $kategori->ad }}</h2>
                    <span class="urun-count">{{ __('front.product.products_count', ['count' => $urunler->count()]) }}</span>
                </div>

                @if(!blank($kategori->aciklama))
                    <p class="kategori-aciklama">{{ $kategori->aciklama }}</p>
                @endif

                @include('front.urunler.partials.filtre-siralama', [
                    'action' => route('products.category', $kategori->slug),
                    'filtreler' => $filtreler ?? [],
                    'offcanvasId' => 'urunFiltreOffcanvasKategori',
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
                        <p class="mb-0">{{ __('front.product.no_products_in_category') }}</p>
                    </div>
                @endif

                <div class="mt-4">
                    {{ $urunler->appends(request()->query())->withPath(route('products.category', ['slug' => $seciliKategoriSlug]))->links('pagination::bootstrap-5') }}
                </div>
            </section>
        </div>
    </div>

    <div class="offcanvas offcanvas-start" tabindex="-1" id="urunFiltreOffcanvasKategori" aria-labelledby="urunFiltreOffcanvasKategoriLabel">
        <div class="offcanvas-header">
            <h5 id="urunFiltreOffcanvasKategoriLabel" class="offcanvas-title">{{ __('front.product.categories') }}</h5>
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


