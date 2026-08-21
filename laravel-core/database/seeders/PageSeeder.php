<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            ['title' => 'İletişim', 'slug' => 'iletisim', 'content' => '', 'sort_order' => 1],
            ['title' => 'Hakkımızda', 'slug' => 'hakkimizda', 'content' => '', 'sort_order' => 2],
        ];

        foreach ($pages as $item) {
            Page::firstOrCreate(
                ['slug' => $item['slug']],
                array_merge($item, ['is_active' => true])
            );
        }
    }
}
