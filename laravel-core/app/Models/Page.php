<?php

namespace App\Models;

use App\Support\FrontIcerikCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Page extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'content',
        'meta_description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saved(static function (): void {
            Cache::forget('sitemap_xml');
            FrontIcerikCache::temizle('pages');
        });
        static::deleted(static function (): void {
            Cache::forget('sitemap_xml');
            FrontIcerikCache::temizle('pages');
        });
        static::creating(function (Page $page) {
            if (empty($page->slug)) {
                $page->slug = Str::slug($page->title);
            }
        });
        static::updating(function (Page $page) {
            if ($page->isDirty('title') && ! $page->isDirty('slug')) {
                $page->slug = Str::slug($page->title);
            }
        });
    }
}
