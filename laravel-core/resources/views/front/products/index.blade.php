@extends('front.layouts.app')

@section('title', __('front.product.products_page_title') . ' | Yalova Kamera')
@section('meta_description', 'Kamera sistemleri, kayıt cihazları, güvenlik ekipmanları ve aksesuar ürünleri.')

@php
    \App\Helpers\BreadcrumbHelper::clear();
    \App\Helpers\BreadcrumbHelper::add(__('front.listing.home'), '/');
    \App\Helpers\BreadcrumbHelper::add(__('front.product.products_page_title'));
@endphp

@section('content')
    <div class="page-header">
        <div class="container">
            <div class="page-header-box">
                <h1 class="wow fadeInUp">{{ __('front.product.products_page_title') }}</h1>
                {!! \App\Helpers\BreadcrumbHelper::render() !!}
            </div>
        </div>
    </div>

    <div class="our-services">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-12 col-xl-10 col-xxl-9">
                    @if($featuredProducts->isNotEmpty())
                        <div class="row mb-4">
                            <div class="col-12">
                                <h3 class="mb-3">{{ __('front.product.featured_products') }}</h3>
                            </div>
                            @foreach($featuredProducts as $product)
                                <div class="col-lg-3 col-md-6 mb-3">
                                    @include('front.products.partials.card', ['product' => $product])
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @include('front.products.partials.filters', [
                        'action' => route('products.index'),
                        'categories' => $categories,
                        'brands' => $brands,
                    ])

                    <div class="row">
                        @forelse($products as $product)
                            <div class="col-lg-4 col-md-6 mb-4">
                                @include('front.products.partials.card', ['product' => $product])
                            </div>
                        @empty
                            <div class="col-12 text-center py-5">
                                <h4>{{ __('front.product.no_results_title') }}</h4>
                                <p>{{ __('front.product.no_results_desc') }}</p>
                            </div>
                        @endforelse
                    </div>

                    <div class="mt-4">
                        {{ $products->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    {
      "@type": "ListItem",
      "position": 1,
      "name": "{{ __('front.listing.home') }}",
      "item": "{{ route('home') }}"
    },
    {
      "@type": "ListItem",
      "position": 2,
      "name": "{{ __('front.product.products_page_title') }}",
      "item": "{{ route('products.index') }}"
    }
  ]
}
</script>
@endpush

