@php
    $img = fn ($path) => $path
        ? asset('uploads/' . ltrim(str_replace('\\', '/', $path), '/'))
        : null;
@endphp
@extends('front.layouts.app')

@section('title', ($bilgiSayfa->meta_title ?: $bilgiSayfa->title) . ' | Yalova Kamera Sistemleri')
@section('meta_description', $bilgiSayfa->meta_description ?? \Illuminate\Support\Str::limit(strip_tags($bilgiSayfa->content), 160))
@section('meta_keywords', $bilgiSayfa->meta_keywords)

@php
    \App\Helpers\BreadcrumbHelper::clear();
    \App\Helpers\BreadcrumbHelper::add('Anasayfa', route('home'));
    \App\Helpers\BreadcrumbHelper::add($bilgiSayfa->title);
@endphp

@section('content')

    <!-- Page Header Start -->
    <div class="page-header parallaxie">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-12">
                    <div class="page-header-box">
                        <h1 class="wow fadeInUp" data-cursor="-opaque">{{ $bilgiSayfa->title }}</h1>
                        @if($bilgiSayfa->author_name || $bilgiSayfa->published_at)
                        <div class="post-single-meta wow fadeInUp">
                            <ol class="breadcrumb">
                                @if($bilgiSayfa->author_name)<li><i class="fa-regular fa-user"></i> {{ $bilgiSayfa->author_name }}</li>@endif
                                @if($bilgiSayfa->published_at)<li><i class="fa-regular fa-clock"></i> {{ $bilgiSayfa->published_at->format('d M Y') }}</li>@endif
                            </ol>
                        </div>
                        @endif
                        {!! \App\Helpers\BreadcrumbHelper::render() !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Page Header End -->

    <!-- Page Single Post Start -->
    <div class="page-single-post">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    @if($bilgiSayfa->featured_image)
                    <div class="post-image">
                        <figure class="image-anime reveal">
                            <img src="{{ $img($bilgiSayfa->featured_image) }}" alt="{{ $bilgiSayfa->title }}">
                        </figure>
                    </div>
                    @endif

                    <div class="post-content">
                        <div class="post-entry">
                            {!! $bilgiSayfa->content !!}
                        </div>

                        @if($bilgiSayfa->tags)
                        <div class="post-tag-links">
                            <div class="row align-items-center">
                                <div class="col-lg-8">
                                    <div class="post-tags wow fadeInUp" data-wow-delay="0.5s">
                                        <span class="tag-links">
                                            Etiketler:
                                            @foreach(array_map('trim', explode(',', $bilgiSayfa->tags)) as $tag)
                                                @if($tag)<a href="{{ route('blog.index') }}">{{ $tag }}</a>@endif
                                            @endforeach
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Page Single Post End -->

@endsection

