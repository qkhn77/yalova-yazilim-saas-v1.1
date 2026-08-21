<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    protected $fillable = [
        'parent_id',
        'type',
        'label',
        'title',
        'url',
        'link_id',
        'route_name',
        'target_type',
        'icon',
        'css_class',
        'menu_location',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('sort_order');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeLocation($query, string $location)
    {
        return $query->where('menu_location', $location);
    }

    public function getLabelAttribute(): string
    {
        return (string) ($this->title ?: ($this->attributes['label'] ?? ''));
    }

    public function getHrefAttribute(): string
    {
        if (! empty($this->route_name) && \Illuminate\Support\Facades\Route::has($this->route_name)) {
            return route($this->route_name);
        }

        if (! empty($this->route_name) && (str_starts_with($this->route_name, '/') || str_starts_with($this->route_name, 'http://') || str_starts_with($this->route_name, 'https://'))) {
            return str_starts_with($this->route_name, 'http')
                ? $this->route_name
                : url($this->route_name);
        }

        // Eski veri yapisindan gelen kayitlar icin geriye donuk destek.
        if (! empty($this->type)) {
            return match ($this->type) {
                'home' => route('home'),
                'contact' => route('contact'),
                'page' => $this->resolvePageUrl(),
                'services' => route('services.index'),
                'service_category' => $this->resolveServiceCategoryUrl(),
                'projects' => route('projects.index'),
                'project_category' => $this->resolveProjectCategoryUrl(),
                'blog' => route('blog.index'),
                'post_category' => $this->resolvePostCategoryUrl(),
                default => $this->resolveCustomUrl(),
            };
        }

        return $this->resolveCustomUrl();
    }

    public function getTargetAttribute(): string
    {
        return $this->target_type === 'new_tab' ? '_blank' : '_self';
    }

    public function getShouldUseNoopenerAttribute(): bool
    {
        return $this->target_type === 'new_tab';
    }

    /** Custom URL'yi uygulama base URL ile birleştirir (alt dizinde çalışınca linkler bozulmasın). */
    protected function resolveCustomUrl(): string
    {
        $url = $this->url ?? '';
        if ($url === '' || $url === '#') {
            return '#';
        }
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        return url($url);
    }

    protected function resolvePageUrl(): string
    {
        $page = Page::find($this->link_id);
        return $page ? url('/sayfa/' . $page->slug) : '#';
    }

    protected function resolveServiceCategoryUrl(): string
    {
        $cat = ServiceCategory::find($this->link_id);
        return $cat ? route('services.index.category', ['categorySlug' => $cat->slug]) : route('services.index');
    }

    protected function resolveProjectCategoryUrl(): string
    {
        $cat = ProjectCategory::find($this->link_id);
        return $cat ? route('projects.index.category', ['categorySlug' => $cat->slug]) : route('projects.index');
    }

    protected function resolvePostCategoryUrl(): string
    {
        $cat = PostCategory::find($this->link_id);
        return $cat ? route('blog.index.category', ['categorySlug' => $cat->slug]) : route('blog.index');
    }
}
