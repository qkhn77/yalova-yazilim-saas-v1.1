@extends('front.layouts.app')

@section('title', isset($category) ? $category->name . ' | ' . __('front.listing.projects') : __('front.listing.projects'))
@section('meta_description', isset($category) && !empty($category->seo_description) ? $category->seo_description : __('front.common.category_suffix', ['name' => __('front.listing.projects')]))

@php
    \App\Helpers\BreadcrumbHelper::clear();
    \App\Helpers\BreadcrumbHelper::add(__('front.listing.home'), '/');
    \App\Helpers\BreadcrumbHelper::add(__('front.listing.projects'), route('projects.index'));
    \App\Helpers\BreadcrumbHelper::add($category->name ?? __('front.common.category'));
@endphp

@section('content')
    <div class="page-header">
        <div class="container">
            <div class="page-header-box">
                <h1 class="wow fadeInUp">{{ $category->name ?? __('front.common.category') }}</h1>
                {!! \App\Helpers\BreadcrumbHelper::render() !!}
            </div>
        </div>
    </div>

    <div class="our-services">
        <div class="container">
            <div class="row">
                @forelse($projects ?? [] as $i => $p)
                    <div class="col-lg-4 col-md-6">
                        @include('front.partials.cards.project-card', ['p' => $p, 'i' => $i])
                    </div>
                @empty
                    <div class="col-12 text-center py-5">
                        <p>{{ __('front.listing.no_projects_in_category') }}</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
