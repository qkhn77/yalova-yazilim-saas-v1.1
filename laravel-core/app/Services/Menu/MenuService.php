<?php

namespace App\Services\Menu;

use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class MenuService
{
    public function getMenuTree(string $location = 'primary'): Collection
    {
        return Cache::remember("menu.tree.{$location}", 3600, function () use ($location): Collection {
            if (! Schema::hasTable('menu_items')) {
                return new Collection;
            }

            return MenuItem::query()
                ->active()
                ->root()
                ->location($location)
                ->with([
                    'children' => fn ($query) => $query
                        ->active()
                        ->location($location)
                        ->orderBy('sort_order')
                        ->orderBy('id'),
                ])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        });
    }
}

