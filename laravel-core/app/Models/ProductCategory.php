<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ProductCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'seo_title',
        'seo_description',
        'is_active',
        'sort_order',
        'parent_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = ['image_url'];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('sitemap_xml'));
        static::deleted(fn () => Cache::forget('sitemap_xml'));

        static::creating(function (self $category): void {
            if (blank($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });

        static::updating(function (self $category): void {
            if ($category->isDirty('name') && ! $category->isDirty('slug')) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    public function activeProducts(): HasMany
    {
        return $this->products()->where('is_active', true);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image) {
            return null;
        }

        return asset('uploads/' . ltrim($this->image, '/'));
    }

    public function seoTitle(): string
    {
        return $this->seo_title ?: $this->name . ' | Ürün Kategorisi';
    }

    public function seoDescription(): string
    {
        return $this->seo_description ?: ($this->description ?: 'Yalova Kamera ürün kategorisi.');
    }
}

