<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class ThemePublishCommand extends Command
{
    protected $signature = 'theme:publish {--force : Önce public/theme silinir, sonra tam kopya alınır}';

    protected $description = 'Proje kökündeki theme/ klasörünü public/theme altına kopyalar (CSS, JS, görseller).';

    public function handle(Filesystem $files): int
    {
        $from = base_path('theme');
        $to = public_path('theme');

        if (! is_dir($from)) {
            $this->error("Kaynak bulunamadı: {$from}");

            return self::FAILURE;
        }

        if ($this->option('force') && is_dir($to)) {
            $files->deleteDirectory($to);
        }

        $files->copyDirectory($from, $to);

        $this->info("Tema güncellendi: {$from} → {$to}");

        return self::SUCCESS;
    }
}
