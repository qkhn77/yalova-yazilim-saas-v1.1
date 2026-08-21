<?php

namespace App\Models;

use App\Support\FrontIcerikCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PostCategory extends Model
{
    protected $fillable = [
        'name',
        'meta_title',
        'slug',
        'description',
        'meta_description',
        'meta_keywords',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => self::frontCacheTemizle());
        static::deleted(fn () => self::frontCacheTemizle());
        static::creating(function (PostCategory $category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
        static::updating(function (PostCategory $category) {
            if ($category->isDirty('name') && ! $category->isDirty('slug')) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'post_category_id');
    }

    private static function frontCacheTemizle(): void
    {
        Cache::forget('sitemap_xml');
        FrontIcerikCache::temizle('blog');
    }
}
