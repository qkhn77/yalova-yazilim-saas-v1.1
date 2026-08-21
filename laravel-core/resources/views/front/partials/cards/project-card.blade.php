<div class="service-item wow fadeInUp">
    <div class="service-image">
        <a href="{{ route('projects.show', $p->slug) }}">
            <figure class="image-anime">
                <img src="{{ $p->image_thumb_url ?? $p->image_url ?? app(\App\Services\FrontThemeService::class)->fallbackImage('theme/yalovakamera/images/service-image-1.jpg') }}" alt="{{ $p->title }}" loading="lazy" decoding="async">
            </figure>
        </a>
    </div>

    <div class="service-body">

        <div class="service-content">

            @if($p->category)
                <span class="badge bg-secondary mb-1">{{ $p->category->name }}</span>
            @endif

            <h3>
                <a href="{{ route('projects.show', $p->slug) }}">{{ $p->title }}</a>
            </h3>

            <p>{{ \Illuminate\Support\Str::limit($p->description ?? '', 80) }}</p>

        </div>

        <div class="service-btn">
            <a href="{{ route('projects.show', $p->slug) }}" class="readmore-btn">{{ __('front.common.detail') }}</a>
        </div>

    </div>
</div>
