@php
    \App\Helpers\BreadcrumbHelper::clear();
    \App\Helpers\BreadcrumbHelper::add('Anasayfa', '/');
    \App\Helpers\BreadcrumbHelper::add($title ?? '');
@endphp

<div class="page-header parallaxie">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-12">

                <div class="page-header-box">

                    <h1 class="wow fadeInUp">
                        {{ $title ?? '' }}
                    </h1>

                    {!! \App\Helpers\BreadcrumbHelper::render() !!}

                </div>

            </div>
        </div>
    </div>
</div>
