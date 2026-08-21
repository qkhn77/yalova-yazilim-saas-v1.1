@extends('front.layouts.app')

@php
    $settings = \App\Models\Setting::class;
    $defaultMetaTitle = $settings::get('blog_index_meta_title', 'Yalova Kamera Kurulumu Blog | Güvenlik Sistemi İpuçları');
    $defaultMetaDescription = $settings::get('blog_index_meta_description', 'Yalova kamera kurulumu, güvenlik sistemi ipuçları ve CCTV rehberleri. Profesyonel kamera montajı, alarm sistemi kurulumu hakkında detaylı bilgiler.');
    $defaultMetaKeywords = $settings::get('blog_index_meta_keywords', 'yalova kamera kurulumu, güvenlik kamerası kurulumu, CCTV montajı, alarm sistemi, güvenlik ipuçları');
    $headerTitle = $settings::get('blog_index_header_title', __('front.listing.blog'));
    $sectionBadge = $settings::get('blog_index_section_badge', __('front.listing.blog'));
    $sectionHeading = $settings::get('blog_index_section_heading', '<span>İpuçları ve</span> rehber içerikler');
    $emptyText = $settings::get('blog_index_empty_text', 'Henüz blog yazısı eklenmemiş.');

    \App\Helpers\BreadcrumbHelper::clear();
    \App\Helpers\BreadcrumbHelper::add(__('front.listing.home'), '/');
    \App\Helpers\BreadcrumbHelper::add(__('front.listing.blog'));
@endphp

@section('title', isset($category) ? (($category->meta_title ?? $category->name) . ' | Yalova Kamera Blog') : $defaultMetaTitle)
@section('meta_description', isset($category) ? ($category->meta_description ?? $category->description ?? 'Yalova kamera kurulumu blog kategorisi.') : $defaultMetaDescription)
@section('meta_keywords', isset($category) ? ($category->meta_keywords ?? 'yalova kamera kurulumu, blog, ' . ($category->name ?? '')) : $defaultMetaKeywords)

@section('content')
    @php $categories = $categories ?? collect(); @endphp

    <div class="page-header">
        <div class="container">
            <div class="page-header-box">
                <h1 class="wow fadeInUp">{{ isset($category) ? $category->name : $headerTitle }}</h1>
                {!! \App\Helpers\BreadcrumbHelper::render() !!}
                @if($categories->isNotEmpty())
                    <nav class="mt-3">
                        <a href="{{ route('blog.index') }}" class="btn-default btn-sm {{ !isset($category) ? 'active' : '' }}">{{ __('front.common.all') }}</a>
                        @foreach($categories as $cat)
                            <a href="{{ route('blog.index.category', $cat->slug) }}" class="btn-default btn-sm {{ (isset($category) && $category->id === $cat->id) ? 'active' : '' }}">{{ $cat->name }}</a>
                        @endforeach
                    </nav>
                @endif
            </div>
        </div>
    </div>

    <div class="our-blog">
        <div class="container">
            <div class="row section-row">
                <div class="col-lg-12">
                    <div class="section-title section-title-center">
                        <h3 class="wow fadeInUp">{{ isset($category) ? $category->name : $sectionBadge }}</h3>
                        <h2 class="wow fadeInUp" data-wow-delay="0.2s">
                            @if(isset($category))
                                {{ __('front.common.category_suffix', ['name' => $category->name]) }}
                            @elseif($posts->isNotEmpty())
                                {!! $sectionHeading !!}
                            @else
                                {{ __('front.common.coming_soon_content') }}
                            @endif
                        </h2>
                    </div>
                </div>
            </div>

            <div class="row">
                @forelse($posts as $i => $post)
                    <div class="col-lg-4 col-md-6">
                        @include('front.partials.cards.blog-card', ['post' => $post, 'i' => $i])
                    </div>
                @empty
                    <div class="col-12 text-center py-5">
                        <p>{{ $emptyText }}</p>
                    </div>
                @endforelse

                @if(method_exists($posts, 'links') && $posts->hasPages())
                    <div class="col-12">
                        <div class="mt-4">
                            {{ $posts->withPath(url()->current())->appends(request()->except('page'))->links('pagination::bootstrap-5') }}
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
