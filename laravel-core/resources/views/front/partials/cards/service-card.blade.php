<div class="service-item wow fadeInUp">
    <div class="service-image">
        <a href="{{ route('services.show', $s->slug) }}">
            <figure class="image-anime">
                <img src="{{ $s->image_thumb_url ?? $s->image_url ?? app(\App\Services\FrontThemeService::class)->fallbackImage('theme/yalovakamera/images/service-image-1.jpg') }}" alt="{{ $s->title }}" loading="lazy" decoding="async">
            </figure>
        </a>
    </div>

    <div class="service-body">
        <div class="service-content">

            @if($s->category)
                <span class="badge bg-secondary mb-1">{{ $s->category->name }}</span>
            @endif

            <h3>
                <a href="{{ route('services.show', $s->slug) }}">{{ $s->title }}</a>
            </h3>

            <p>{{ $s->short_description }}</p>

        </div>

        <div class="service-btn">
            <a href="{{ route('services.show', $s->slug) }}" class="readmore-btn">{{ __('front.common.detail') }}</a>
        </div>
    </div>
</div>
