@extends('front.layouts.app')

@section('title', $post->title . ' | Yalova Kamera Kurulumu Blog')
@section('meta_description', $post->excerpt ?? \Illuminate\Support\Str::limit(strip_tags($post->content), 160))
@section('meta_keywords', $post->meta_keywords ?? 'yalova kamera kurulumu, güvenlik kamerası' . ($post->category ? ', ' . $post->category->name : ''))
@section('meta_robots', $post->meta_robots ?? 'index,follow')
@section('og_title', $post->og_title ?? $post->title)
@section('og_description', $post->og_description ?? $post->excerpt ?? \Illuminate\Support\Str::limit(strip_tags($post->content), 160))
@section('og_image', $post->og_image_url ?: asset('theme/yalovakamera/images/yalova_kamera.png'))

@php
    \App\Helpers\BreadcrumbHelper::clear();
    \App\Helpers\BreadcrumbHelper::add(__('front.listing.home'), '/');
    \App\Helpers\BreadcrumbHelper::add(__('front.listing.blog'), route('blog.index'));
    if ($post->category) {
        \App\Helpers\BreadcrumbHelper::add($post->category->name, route('blog.index.category', $post->category->slug));
    }
    \App\Helpers\BreadcrumbHelper::add($post->title);
@endphp

@section('content')
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-12">
                    <div class="page-header-box">
                        <h1 class="wow fadeInUp">{{ $post->title }}</h1>
                        {!! \App\Helpers\BreadcrumbHelper::render() !!}
                        @if($post->published_at)
                            <p class="text-muted mb-0">{{ $post->published_at->format('d.m.Y') }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container py-5">
        @if($post->category)
            <p><a href="{{ route('blog.index.category', $post->category->slug) }}" class="badge bg-secondary text-decoration-none">{{ $post->category->name }}</a></p>
        @endif
        @if($post->image_url)
            <figure class="mb-4">
                <img src="{{ $post->image_url }}" alt="{{ $post->title }}" class="img-fluid rounded" width="1200" height="675" loading="eager" decoding="async" fetchpriority="high">
            </figure>
        @endif
        @if($post->excerpt)
            <p class="lead">{{ $post->excerpt }}</p>
        @endif
        <div class="content">{!! $post->content !!}</div>
    </div>
@endsection
