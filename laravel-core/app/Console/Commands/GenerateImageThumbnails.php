<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\Project;
use App\Models\Service;
use App\Services\ThumbnailService;
use Illuminate\Console\Command;

class GenerateImageThumbnails extends Command
{
    protected $signature = 'thumbnails:generate
                            {--posts : Sadece blog görselleri}
                            {--services : Sadece servis görselleri}
                            {--projects : Sadece proje görselleri}
                            {--force : Var olan thumbnail\'ları yeniden oluştur}';

    protected $description = 'Data table hızlı yükleme için mevcut görsellerden 48px thumbnail üretir.';

    public function handle(ThumbnailService $thumbnail): int
    {
        $posts = $this->option('posts') || (! $this->option('posts') && ! $this->option('services') && ! $this->option('projects'));
        $services = $this->option('services') || (! $this->option('posts') && ! $this->option('services') && ! $this->option('projects'));
        $projects = $this->option('projects') || (! $this->option('posts') && ! $this->option('services') && ! $this->option('projects'));
        $force = $this->option('force');

        $count = 0;

        if ($posts) {
            $this->info('Blog görselleri işleniyor...');
            foreach (Post::whereNotNull('image')->where('image', '!=', '')->get() as $post) {
                $path = str_replace('\\', '/', (string) $post->image);
                if ($force || ! $thumbnail->getThumbPath('posts', $path)) {
                    if ($thumbnail->generate('posts', $path)) {
                        $count++;
                        $this->line("  ✓ {$path}");
                    }
                }
            }
        }

        if ($services) {
            $this->info('Servis görselleri işleniyor...');
            foreach (Service::whereNotNull('image')->where('image', '!=', '')->get() as $service) {
                $path = str_replace('\\', '/', (string) $service->image);
                if ($force || ! $thumbnail->getThumbPath('services', $path)) {
                    if ($thumbnail->generate('services', $path)) {
                        $count++;
                        $this->line("  ✓ {$path}");
                    }
                }
            }
        }

        if ($projects) {
            $this->info('Proje görselleri işleniyor...');
            foreach (Project::whereNotNull('image')->where('image', '!=', '')->get() as $project) {
                $path = str_replace('\\', '/', (string) $project->image);
                if ($force || ! $thumbnail->getThumbPath('projects', $path)) {
                    if ($thumbnail->generate('projects', $path)) {
                        $count++;
                        $this->line("  ✓ {$path}");
                    }
                }
            }
        }

        $this->info("Toplam {$count} thumbnail oluşturuldu.");
        return self::SUCCESS;
    }
}
