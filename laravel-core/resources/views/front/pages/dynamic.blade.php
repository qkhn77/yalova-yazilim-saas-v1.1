@extends('front.layouts.app')

@section('title', $page->title . ' | ' . (config('app.name')))
@section('meta_description', $page->meta_description)
@section('meta_keywords', $page->title . ', yalova kamera kurulumu, yalova güvenlik kamerası, güvenlik sistemi çözümleri')

@section('content')
    <div class="page-header">
        <div class="container">
            <div class="page-header-box">
                <h1 class="wow fadeInUp">{{ $page->title }}</h1>
            </div>
        </div>
    </div>
    <div class="container py-5">
        <div class="page-content">
            {!! $page->content !!}
        </div>
    </div>
@endsection
