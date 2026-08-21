@extends('front.layouts.app')

@php
    $settings = \App\Models\Setting::class;
    $defaultMetaTitle = $settings::get('services_index_meta_title', __('front.listing.services') . ' | Yalova Kamera Sistemleri');
    $defaultMetaDescription = $settings::get('services_index_meta_description', 'IP kamera kurulumu, alarm sistemleri, montaj, teknik servis ve bakım hizmetleri. Yalova genelinde profesyonel çözümler.');
    $defaultMetaKeywords = $settings::get('services_index_meta_keywords', 'yalova kamera servisi, kamera montaj servisi, alarm sistemi servisi, IP kamera kurulumu yalova, güvenlik sistemi teknik servis, kamera bakım onarım');
    $headerTitle = $settings::get('services_index_header_title', __('front.listing.services'));
    $sectionBadge = $settings::get('services_index_section_badge', __('front.listing.services'));
    $sectionHeading = $settings::get('services_index_section_heading', '<span>Kapsamlı güvenlik</span> ve izleme çözümleri');
    $emptyText = $settings::get('services_index_empty_text', 'Henüz servis eklenmemiş. Admin panelinden ekleyebilirsiniz.');
    $footerCtaText = $settings::get('services_index_footer_cta_text', '<span>Ücretsiz</span> keşif ve teklif için <a href="' . route('contact') . '">iletişime geç</a>');

    \App\Helpers\BreadcrumbHelper::clear();
    \App\Helpers\BreadcrumbHelper::add(__('front.listing.home'), '/');
    \App\Helpers\BreadcrumbHelper::add(__('front.listing.services'));
@endphp

@section('title', isset($category) ? (($category->meta_title ?? $category->name) . ' | Yalova Kamera Servisler') : $defaultMetaTitle)
@section('meta_description', isset($category) ? ($category->meta_description ?? $category->description ?? 'Yalova kamera servis kategorisi.') : $defaultMetaDescription)
@section('meta_keywords', isset($category) ? ($category->meta_keywords ?? 'yalova kamera servisi, ' . ($category->name ?? '')) : $defaultMetaKeywords)

@section('content')
    @php $categories = $categories ?? collect(); @endphp

    <div class="page-header">
        <div class="container">
            <div class="page-header-box">
                <h1 class="wow fadeInUp">{{ isset($category) ? $category->name : $headerTitle }}</h1>
                {!! \App\Helpers\BreadcrumbHelper::render() !!}
                @if($categories->isNotEmpty())
                    <nav class="mt-3">
                        <a href="{{ route('services.index') }}" class="btn-default btn-sm {{ !isset($category) ? 'active' : '' }}">{{ __('front.common.all') }}</a>
                        @foreach($categories as $cat)
                            <a href="{{ route('services.index.category', $cat->slug) }}" class="btn-default btn-sm {{ (isset($category) && $category->id === $cat->id) ? 'active' : '' }}">{{ $cat->name }}</a>
                        @endforeach
                    </nav>
                @endif
            </div>
        </div>
    </div>

    <div class="our-services">
        <div class="container">
            <div class="row section-row align-items-center">
                <div class="col-lg-12">
                    <div class="section-title section-title-center">
                        <h3 class="wow fadeInUp">{{ isset($category) ? $category->name : $sectionBadge }}</h3>
                        <h2 class="wow fadeInUp" data-wow-delay="0.2s" data-cursor="-opaque">
                            @if(isset($category))
                                {{ __('front.common.category_suffix', ['name' => $category->name]) }}
                            @else
                                {!! $sectionHeading !!}
                            @endif
                        </h2>
                    </div>
                </div>
            </div>

            <div class="row">
                @forelse($services as $i => $s)
                    <div class="col-lg-4 col-md-6">
                        @include('front.partials.cards.service-card', ['s' => $s, 'i' => $i])
                    </div>
                @empty
                    <div class="col-12 text-center py-5">
                        <p>{{ $emptyText }}</p>
                        <a href="{{ route('contact') }}" class="btn-default">{{ __('front.common.contact_us') }}</a>
                    </div>
                @endforelse

                @if(method_exists($services, 'links') && $services->hasPages())
                    <div class="col-12">
                        <div class="mt-4">
                            {{ $services->withPath(url()->current())->appends(request()->except('page'))->links('pagination::bootstrap-5') }}
                        </div>
                    </div>
                @endif

                <div class="col-lg-12">
                    <div class="section-footer-text wow fadeInUp" data-wow-delay="0.4s">
                        <p>{!! $footerCtaText !!}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
