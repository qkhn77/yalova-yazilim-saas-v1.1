@extends('front.layouts.app')

@section('title', $project->title . ' | ' . __('front.listing.projects'))
@section('meta_description', $project->short_description ?: \Illuminate\Support\Str::limit($project->description ?? $project->content, 160))
@section('meta_keywords', $project->meta_keywords ?: trim(($project->category?->name ? $project->category->name . ', ' : '') . $project->title . ', yalova kamera kurulumu projeleri, güvenlik kamerası montajı, CCTV proje örnekleri', ', '))

@section('content')
    <div class="page-header parallaxie">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-12">
                    <div class="page-header-box">
                        <h1 class="wow fadeInUp" data-cursor="-opaque">{{ $project->title }}</h1>
                        <nav class="wow fadeInUp" data-wow-delay="0.2s">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('front.listing.home') }}</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('projects.index') }}">{{ __('front.listing.projects') }}</a></li>
                                @if($project->category)
                                    <li class="breadcrumb-item"><a href="{{ route('projects.index.category', $project->category->slug) }}">{{ $project->category->name }}</a></li>
                                @endif
                                <li class="breadcrumb-item active" aria-current="page">{{ $project->title }}</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container py-5">
        @if($project->category)
            <p><a href="{{ route('projects.index.category', $project->category->slug) }}" class="badge bg-secondary text-decoration-none">{{ $project->category->name }}</a></p>
        @endif
        @if($project->image_url)
            <figure class="mb-4">
                <img src="{{ $project->image_url }}" alt="{{ $project->title }}" class="img-fluid rounded" width="1200" height="675" loading="eager" decoding="async" fetchpriority="high">
            </figure>
        @endif
        @if($project->short_description)
            <p class="lead">{{ $project->short_description }}</p>
        @endif
        @if($project->content)
            <div class="content mb-4">{!! $project->content !!}</div>
        @elseif($project->description)
            <div class="content mb-4">{!! nl2br(e($project->description)) !!}</div>
        @endif
        <a href="{{ route('contact') }}" class="btn-default">{{ __('front.common.get_offer') }}</a>
    </div>
@endsection
