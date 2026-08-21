@extends('front.layouts.app')

@section('title', 'Bilgi Merkezi | Yalova Kamera Sistemleri')
@section('meta_description', 'Gizlilik politikası, iade koşulları ve kullanım şartları gibi bilgilendirici sayfaları buradan inceleyin.')
@section('meta_keywords', 'bilgi merkezi, gizlilik politikası, iade politikası, kullanım şartları')

@php
    \App\Helpers\BreadcrumbHelper::clear();
    \App\Helpers\BreadcrumbHelper::add('Anasayfa', route('home'));
    \App\Helpers\BreadcrumbHelper::add('Bilgi Merkezi');
@endphp

@section('content')
    <div class="page-header">
        <div class="container">
            <div class="page-header-box">
                <h1 class="wow fadeInUp">Bilgi Merkezi</h1>
                {!! \App\Helpers\BreadcrumbHelper::render() !!}
            </div>
        </div>
    </div>

    <div class="page-blog">
        <div class="container">
            <div class="row">
                @forelse($bilgiSayfalari as $sayfa)
                    @php
                        $imageUrl = $sayfa->featured_image
                            ? asset('uploads/' . ltrim(str_replace('\\', '/', $sayfa->featured_image), '/'))
                            : asset('theme/yalovakamera/images/post-1.jpg');
                        $summary = $sayfa->meta_description ?: \Illuminate\Support\Str::limit(strip_tags($sayfa->content), 140);
                    @endphp
                    <div class="col-lg-4 col-md-6">
                        <div class="post-item wow fadeInUp">
                            <div class="post-featured-image">
                                <a href="{{ route('information.show', $sayfa->slug) }}" data-cursor-text="Oku">
                                    <figure>
                                        <img src="{{ $imageUrl }}" alt="{{ $sayfa->title }}">
                                    </figure>
                                </a>
                            </div>
                            <div class="post-item-body">
                                <div class="post-item-content">
                                    @if($sayfa->published_at)
                                        <p>{{ $sayfa->published_at->format('d M, Y') }}</p>
                                    @endif
                                    <h2><a href="{{ route('information.show', $sayfa->slug) }}">{{ $sayfa->title }}</a></h2>
                                </div>
                                <div class="post-item-btn">
                                    <a href="{{ route('information.show', $sayfa->slug) }}" class="readmore-btn">Devamını Oku</a>
                                </div>
                            </div>
                            @if($summary)
                                <div class="px-3 pb-3 text-muted" style="font-size: 14px;">
                                    {{ $summary }}
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="col-lg-12">
                        <div class="section-title section-title-center">
                            <h3>Bilgi Merkezi</h3>
                            <p>Henüz yayınlanmış bilgi sayfası bulunmuyor.</p>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endsection

