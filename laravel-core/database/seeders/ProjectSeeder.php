<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Service;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    /**
     * Projeleri servislerden kopyala (demo veri). Servisler sayfasındaki kayıtlar ile aynı içerik.
     */
    public function run(): void
    {
        $services = Service::where('is_active', true)->orderBy('sort_order')->get();

        foreach ($services as $index => $service) {
            Project::firstOrCreate(
                ['slug' => $service->slug],
                [
                    'title' => $service->title,
                    'description' => $service->short_description,
                    'image' => $service->image,
                    'icon' => $service->icon,
                    'sort_order' => $service->sort_order,
                    'is_active' => true,
                ]
            );
        }
    }
}
