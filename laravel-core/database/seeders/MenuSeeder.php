<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use App\Models\PostCategory;
use App\Models\ProjectCategory;
use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    /**
     * Uygulamanın ana menüsünü başlangıçta oluşturur.
     * Eğer menu_items tablosunda kayıt varsa dokunmaz.
     */
    public function run(): void
    {
        if (MenuItem::query()->exists()) {
            return;
        }

        $sort = 0;

        // Anasayfa
        MenuItem::create([
            'parent_id' => null,
            'type' => 'home',
            'label' => 'Anasayfa',
            'url' => null,
            'link_id' => null,
            'sort_order' => $sort++,
            'is_active' => true,
        ]);

        // Hakkımızda
        MenuItem::create([
            'parent_id' => null,
            'type' => 'custom',
            'label' => 'Hakkımızda',
            'url' => '/hakkimizda',
            'link_id' => null,
            'sort_order' => $sort++,
            'is_active' => true,
        ]);

        // Servisler + kategorileri
        $services = MenuItem::create([
            'parent_id' => null,
            'type' => 'services',
            'label' => 'Servisler',
            'url' => null,
            'link_id' => null,
            'sort_order' => $sort++,
            'is_active' => true,
        ]);

        $childSort = 0;
        ServiceCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->each(function (ServiceCategory $category) use ($services, &$childSort) {
                MenuItem::create([
                    'parent_id' => $services->id,
                    'type' => 'service_category',
                    'label' => $category->name,
                    'url' => null,
                    'link_id' => $category->id,
                    'sort_order' => $childSort++,
                    'is_active' => true,
                ]);
            });

        // WebProje + kategorileri
        $projects = MenuItem::create([
            'parent_id' => null,
            'type' => 'projects',
            'label' => 'WebProje',
            'url' => null,
            'link_id' => null,
            'sort_order' => $sort++,
            'is_active' => true,
        ]);

        $childSort = 0;
        ProjectCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->each(function (ProjectCategory $category) use ($projects, &$childSort) {
                MenuItem::create([
                    'parent_id' => $projects->id,
                    'type' => 'project_category',
                    'label' => $category->name,
                    'url' => null,
                    'link_id' => $category->id,
                    'sort_order' => $childSort++,
                    'is_active' => true,
                ]);
            });

        // Bloglar + kategorileri
        $blog = MenuItem::create([
            'parent_id' => null,
            'type' => 'blog',
            'label' => 'Bloglar',
            'url' => null,
            'link_id' => null,
            'sort_order' => $sort++,
            'is_active' => true,
        ]);

        $childSort = 0;
        PostCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->each(function (PostCategory $category) use ($blog, &$childSort) {
                MenuItem::create([
                    'parent_id' => $blog->id,
                    'type' => 'post_category',
                    'label' => $category->name,
                    'url' => null,
                    'link_id' => $category->id,
                    'sort_order' => $childSort++,
                    'is_active' => true,
                ]);
            });

        // İletişim
        MenuItem::create([
            'parent_id' => null,
            'type' => 'custom',
            'label' => 'İletişim',
            'url' => '/iletisim',
            'link_id' => null,
            'sort_order' => $sort++,
            'is_active' => true,
        ]);
    }
}

