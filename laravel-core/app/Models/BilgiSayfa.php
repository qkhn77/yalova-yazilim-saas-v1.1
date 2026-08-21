<?php

namespace App\Models;

use App\Support\FrontIcerikCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class BilgiSayfa extends Model
{
    protected $table = 'info_pages';

    protected $fillable = [
        'title',
        'slug',
        'content',
        'featured_image',
        'author_name',
        'tags',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'published_at',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saved(static function (): void {
            Cache::forget('sitemap_xml');
            Cache::forget('front.footer.info_pages');
            Cache::forget('front.footer.policy_pages');
            FrontIcerikCache::temizle('information');
        });
        static::deleted(static function (): void {
            Cache::forget('sitemap_xml');
            Cache::forget('front.footer.info_pages');
            Cache::forget('front.footer.policy_pages');
            FrontIcerikCache::temizle('information');
        });
        static::creating(function (BilgiSayfa $page) {
            if (empty($page->slug)) {
                $page->slug = Str::slug($page->title);
            }
        });
        static::updating(function (BilgiSayfa $page) {
            if ($page->isDirty('title') && ! $page->isDirty('slug')) {
                $page->slug = Str::slug($page->title);
            }
        });
    }
}
