@extends("front.layouts.app")

@section("content")

<div class="container py-5">

<h1>{{ $category->name ?? __('front.common.category') }}</h1>

<div class="row">

@forelse($posts ?? [] as $post)

<div class="col-lg-4 col-md-6">

@include("front.partials.cards.blog-card",["post"=>$post])

</div>

@empty

<p>{{ __('front.common.no_content_in_category') }}</p>

@endforelse

</div>

</div>

@endsection
