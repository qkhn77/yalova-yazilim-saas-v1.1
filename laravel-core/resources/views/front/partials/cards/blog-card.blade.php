<div class="post-item wow fadeInUp">
    <div class="post-featured-image">
        <a href="{{ route('blog.show', $post->slug) }}">
            <figure class="image-anime">
                <img src="{{ $post->image_thumb_url ?? $post->image_url ?? app(\App\Services\FrontThemeService::class)->fallbackImage('theme/yalovakamera/images/post-1.jpg') }}" alt="{{ $post->title }}" loading="lazy" decoding="async">
            </figure>
        </a>
    </div>

    <div class="post-item-body">
        <div class="post-item-content">
            <h3>
                <a href="{{ route('blog.show', $post->slug) }}">{{ $post->title }}</a>
            </h3>

            <p>
                {{ $post->excerpt ? \Illuminate\Support\Str::limit($post->excerpt, 110) : \Illuminate\Support\Str::limit(strip_tags($post->content), 110) }}
            </p>
        </div>

        <div class="post-item-btn">
            <a href="{{ route('blog.show', $post->slug) }}" class="readmore-btn">{{ __('front.common.read_more') }}</a>
        </div>
    </div>
</div>
