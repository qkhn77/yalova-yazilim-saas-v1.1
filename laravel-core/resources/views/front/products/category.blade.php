@extends('front.layouts.app')

@section('title', $category->seoTitle())
@section('meta_description', $category->seoDescription())

@php
    \App\Helpers\BreadcrumbHelper::clear();
    \App\Helpers\BreadcrumbHelper::add(__('front.listing.home'), '/');
    \App\Helpers\BreadcrumbHelper::add(__('front.product.products_page_title'), route('products.index'));
    \App\Helpers\BreadcrumbHelper::add($category->name);
@endphp

@section('content')
    <div class="page-header">
        <div class="container">
            <div class="page-header-box">
                <h1 class="wow fadeInUp">{{ $category->name }}</h1>
                {!! \App\Helpers\BreadcrumbHelper::render() !!}
            </div>
        </div>
    </div>

    <div class="our-services">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-12 col-xl-10 col-xxl-9">
                    @if($category->description)
                        <div class="mb-4">
                            <p>{{ $category->description }}</p>
                        </div>
                    @endif

                    @include('front.products.partials.filters', [
                        'action' => route('products.category', $category->slug),
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
                                <h4>{{ __('front.product.no_products_in_category_legacy') }}</h4>
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
    },
    {
      "@type": "ListItem",
      "position": 3,
      "name": "{{ $category->name }}",
      "item": "{{ route('products.category', $category->slug) }}"
    }
  ]
}
</script>
@endpush

