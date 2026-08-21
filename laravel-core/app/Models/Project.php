<?php

namespace App\Models;

use App\Services\ThumbnailService;
use App\Support\FrontIcerikCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Project extends Model
{
    protected $fillable = [
        'project_category_id',
        'title',
        'slug',
        'short_description',
        'meta_keywords',
        'description',
        'content',
        'image',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = ['image_url', 'image_thumb_url', 'icon_url'];

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image) {
            return null;
        }

        $path = $this->normalizeProjectAssetPath($this->image);

        if ($path === null) {
            return null;
        }

        if (Storage::disk('public')->exists($path)) {
            return asset('uploads/'.$path);
        }

        $themeFallback = 'theme/yalovakamera/images/'.basename($path);

        if (is_file(public_path($themeFallback))) {
            return asset($themeFallback);
        }

        return null;
    }

    public function getIconUrlAttribute(): ?string
    {
        if (! $this->icon) {
            return null;
        }

        $path = str_replace('\\', '/', (string) $this->icon);
        $path = ltrim($path, '/');

        if (str_contains($path, '/') || str_starts_with($path, 'projects/')) {
            if (! str_starts_with($path, 'projects/')) {
                $path = 'projects/'.$path;
            }

            return asset('uploads/'.$path);
        }

        return asset('theme/yalovakamera/images/'.$path);
    }

    public function getImageThumbUrlAttribute(): ?string
    {
        if (! $this->image) {
            return null;
        }

        $path = $this->normalizeProjectAssetPath($this->image);

        if ($path === null) {
            return null;
        }

        $thumbPath = app(ThumbnailService::class)->getThumbPath('projects', $path);

        if ($thumbPath) {
            return asset('uploads/'.ltrim($thumbPath, '/'));
        }

        return $this->image_url;
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::frontCacheTemizle());
        static::deleted(fn () => self::frontCacheTemizle());

        static::creating(function (Project $project): void {
            if (empty($project->slug)) {
                $project->slug = Str::slug($project->title);
            }
        });

        static::updating(function (Project $project): void {
            if ($project->isDirty('title') && ! $project->isDirty('slug')) {
                $project->slug = Str::slug($project->title);
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProjectCategory::class, 'project_category_id');
    }

    private static function frontCacheTemizle(): void
    {
        Cache::forget('sitemap_xml');
        FrontIcerikCache::temizle('projects');
        FrontIcerikCache::temizle('home');
    }

    protected function normalizeProjectAssetPath(?string $path): ?string
    {
        $path = str_replace('\\', '/', (string) $path);
        $path = ltrim($path, '/');

        if ($path === '') {
            return null;
        }

        if (! str_starts_with($path, 'projects/')) {
            $path = 'projects/'.$path;
        }

        return $path;
    }
}
