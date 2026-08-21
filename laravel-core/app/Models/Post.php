<?php

namespace App\Models;

use App\Services\ThumbnailService;
use App\Support\FrontIcerikCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Post extends Model
{
    protected $fillable = [
        'post_category_id',
        'title',
        'slug',
        'excerpt',
        'meta_keywords',
        'content',
        'image',
        'og_title',
        'og_description',
        'og_image',
        'meta_robots',
        'published_at',
        'is_published',
        'sort_order',
    ];

    protected $attributes = [
        'meta_robots' => 'index,follow',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_published' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = ['image_url', 'image_thumb_url', 'og_image_url'];

    public function getImageUrlAttribute(): ?string
    {
        return $this->resolveImageUrl($this->image, 'post-1.jpg');
    }

    public function getImageThumbUrlAttribute(): ?string
    {
        if (! $this->image) {
            return asset('theme/yalovakamera/images/post-1.jpg');
        }

        $path = $this->normalizePostPath($this->image);
        $thumbPath = app(ThumbnailService::class)->getThumbPath('posts', $path);

        if ($thumbPath) {
            return asset('uploads/'.ltrim($thumbPath, '/'));
        }

        return $this->image_url;
    }

    public function getOgImageUrlAttribute(): ?string
    {
        return $this->resolveImageUrl($this->og_image ?: $this->image, 'post-1.jpg');
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::frontCacheTemizle());
        static::deleted(fn () => self::frontCacheTemizle());

        static::creating(function (Post $post): void {
            if (empty($post->slug)) {
                $post->slug = Str::slug($post->title);
            }
        });

        static::updating(function (Post $post): void {
            if ($post->isDirty('title') && ! $post->isDirty('slug')) {
                $post->slug = Str::slug($post->title);
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PostCategory::class, 'post_category_id');
    }

    private static function frontCacheTemizle(): void
    {
        Cache::forget('sitemap_xml');
        FrontIcerikCache::temizle('blog');
    }

    protected function resolveImageUrl(?string $value, string $fallbackThemeImage): ?string
    {
        if (! $value) {
            return asset('theme/yalovakamera/images/'.$fallbackThemeImage);
        }

        $path = $this->normalizePostPath($value);

        if (Storage::disk('public')->exists($path)) {
            return asset('uploads/'.$path);
        }

        $themeCandidate = public_path('theme/yalovakamera/images/'.basename($path));
        if (is_file($themeCandidate)) {
            return asset('theme/yalovakamera/images/'.basename($path));
        }

        return asset('theme/yalovakamera/images/'.$fallbackThemeImage);
    }

    protected function normalizePostPath(string $value): string
    {
        $path = str_replace('\\', '/', $value);
        $path = ltrim($path, '/');

        if (! str_starts_with($path, 'posts/')) {
            $path = 'posts/'.$path;
        }

        return $path;
    }
}
