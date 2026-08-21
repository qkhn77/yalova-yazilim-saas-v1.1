@extends('front.layouts.app')

@php
    $settings = \App\Models\Setting::class;
    $defaultMetaTitle = $settings::get('projects_index_meta_title', 'Yalova Kamera Kurulumu Projeleri | Güvenlik Sistemi Örnekleri');
    $defaultMetaDescription = $settings::get('projects_index_meta_description', 'IP kamera kurulumu, alarm sistemleri ve montaj süreçlerinde Yalova genelinde tamamladığımız profesyonel projeleri inceleyin.');
    $defaultMetaKeywords = $settings::get('projects_index_meta_keywords', 'yalova kamera kurulumu projeleri, güvenlik kamerası montajı, CCTV projeleri, alarm sistemi örnekleri');
    $headerTitle = $settings::get('projects_index_header_title', __('front.listing.projects'));
    $sectionBadge = $settings::get('projects_index_section_badge', __('front.listing.projects'));
    $sectionHeading = $settings::get('projects_index_section_heading', '<span>Kapsamlı güvenlik</span> ve izleme projeleri');
    $emptyText = $settings::get('projects_index_empty_text', 'Henüz proje eklenmemiş. Admin panelinden ekleyebilirsiniz.');
    $footerCtaText = $settings::get('projects_index_footer_cta_text', '<span>Ücretsiz</span> keşif ve teklif için <a href="' . route('contact') . '">iletişime geç</a>');

    \App\Helpers\BreadcrumbHelper::clear();
    \App\Helpers\BreadcrumbHelper::add(__('front.listing.home'), '/');
    \App\Helpers\BreadcrumbHelper::add(__('front.listing.projects'));
@endphp

@section('title', isset($category) ? (($category->meta_title ?? $category->name) . ' | Yalova Kamera Projeler') : $defaultMetaTitle)
@section('meta_description', isset($category) ? ($category->meta_description ?? $category->description ?? 'Yalova kamera proje kategorisi.') : $defaultMetaDescription)
@section('meta_keywords', isset($category) ? ($category->meta_keywords ?? 'yalova kamera projeleri, ' . ($category->name ?? '')) : $defaultMetaKeywords)

@section('content')
    @php $categories = $categories ?? collect(); @endphp

    <div class="page-header">
        <div class="container">
            <div class="page-header-box">
                <h1 class="wow fadeInUp">{{ isset($category) ? $category->name : $headerTitle }}</h1>
                {!! \App\Helpers\BreadcrumbHelper::render() !!}
                @if($categories->isNotEmpty())
                    <nav class="mt-3">
                        <a href="{{ route('projects.index') }}" class="btn-default btn-sm {{ !isset($category) ? 'active' : '' }}">{{ __('front.common.all') }}</a>
                        @foreach($categories as $cat)
                            <a href="{{ route('projects.index.category', $cat->slug) }}" class="btn-default btn-sm {{ (isset($category) && $category->id === $cat->id) ? 'active' : '' }}">{{ $cat->name }}</a>
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
                @forelse($projects as $i => $p)
                    <div class="col-lg-4 col-md-6">
                        @include('front.partials.cards.project-card', ['p' => $p, 'i' => $i])
                    </div>
                @empty
                    <div class="col-12 text-center py-5">
                        <p>{{ $emptyText }}</p>
                        <a href="{{ route('contact') }}" class="btn-default">{{ __('front.common.contact_us') }}</a>
                    </div>
                @endforelse

                <div class="col-lg-12">
                    <div class="section-footer-text wow fadeInUp" data-wow-delay="0.4s">
                        <p>{!! $footerCtaText !!}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
