<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Product extends Model
{
    /**
     * @deprecated Legacy model. Yeni e-ticaret akışı stok_kartlari tabanlıdır.
     */
    public const STOCK_IN_STOCK = 'in_stock';

    public const STOCK_OUT_OF_STOCK = 'out_of_stock';

    public const STOCK_PREORDER = 'preorder';

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'short_description',
        'description',
        'sku',
        'brand',
        'para_birimi',
        'price',
        'discounted_price',
        'stock_status',
        'image',
        'gallery',
        'technical_specs',
        'is_active',
        'is_featured',
        'sort_order',
        'seo_title',
        'seo_description',
    ];

    protected $casts = [
        'gallery' => 'array',
        'technical_specs' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'price' => 'decimal:2',
        'discounted_price' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    protected $appends = [
        'image_url',
        'final_price',
        'discount_percentage',
        'stock_badge',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('sitemap_xml'));
        static::deleted(fn () => Cache::forget('sitemap_xml'));

        static::creating(function (self $product): void {
            if (blank($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
        });

        static::updating(function (self $product): void {
            if ($product->isDirty('name') && ! $product->isDirty('slug')) {
                $product->slug = Str::slug($product->name);
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image) {
            return null;
        }

        return asset('uploads/'.ltrim($this->image, '/'));
    }

    public function getFinalPriceAttribute(): ?float
    {
        if ($this->discounted_price !== null && (float) $this->discounted_price > 0) {
            return (float) $this->discounted_price;
        }

        if ($this->price !== null) {
            return (float) $this->price;
        }

        return null;
    }

    public function getDiscountPercentageAttribute(): ?int
    {
        if ($this->price === null || $this->discounted_price === null) {
            return null;
        }

        $price = (float) $this->price;
        $discounted = (float) $this->discounted_price;

        if ($price <= 0 || $discounted >= $price) {
            return null;
        }

        return (int) round((($price - $discounted) / $price) * 100);
    }

    public function getStockBadgeAttribute(): array
    {
        return match ($this->stock_status) {
            self::STOCK_IN_STOCK => ['label' => 'Stokta', 'class' => 'success'],
            self::STOCK_OUT_OF_STOCK => ['label' => 'Tükendi', 'class' => 'danger'],
            self::STOCK_PREORDER => ['label' => 'Ön Sipariş', 'class' => 'warning'],
            default => ['label' => 'Sorunuz', 'class' => 'secondary'],
        };
    }

    public function seoTitle(): string
    {
        return $this->seo_title ?: ($this->name.' | Ürün Detayı');
    }

    public function seoDescription(): string
    {
        return $this->seo_description ?: ($this->short_description ?: 'Yalova Kamera ürün detayı.');
    }
}
