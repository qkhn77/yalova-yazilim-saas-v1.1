<?php

namespace App\Console\Commands;

use App\Models\Ecommerce\Odeme;
use App\Models\SistemOlayi;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SistemRetentionTemizleCommand extends Command
{
    protected $signature = 'sistem:retention-temizle
        {--days-olay=30 : Sistem olaylari saklama gunu}
        {--days-odeme=90 : Basarisiz/iptal odeme saklama gunu}
        {--apply : Gercek silme uygula}
        {--force : Production ortaminda apply onayi bypass}';

    protected $description = 'Sistem olaylari ve eski odeme denemeleri icin retention temizligi (default dry-run).';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $daysOlay = max(1, (int) $this->option('days-olay'));
        $daysOdeme = max(1, (int) $this->option('days-odeme'));

        if ($apply && app()->environment('production') && ! (bool) $this->option('force')) {
            if (! $this->confirm('Production ortaminda retention temizligi uygulanacak. Devam edilsin mi?')) {
                $this->warn('Islem iptal edildi.');

                return self::INVALID;
            }
        }

        $lock = Cache::lock('sistem:retention:temizle', 600);
        if ($apply && ! $lock->get()) {
            $this->error('Baska bir retention temizligi calisiyor.');

            return self::FAILURE;
        }

        try {
            $olayCutoff = Carbon::now()->subDays($daysOlay);
            $odemeCutoff = Carbon::now()->subDays($daysOdeme);

            if (! Schema::hasTable('sistem_olaylari')) {
                $this->warn('sistem_olaylari tablosu bulunamadi; olay retention adimi atlandi.');

                return self::SUCCESS;
            }
            if (! Schema::hasTable('odemeler')) {
                $this->warn('odemeler tablosu bulunamadi; odeme retention adimi atlandi.');

                return self::SUCCESS;
            }

            $olaySorgu = SistemOlayi::query()->withoutGlobalScopes()->where('created_at', '<', $olayCutoff);
            $odemeSorgu = Odeme::query()
                ->withoutGlobalScopes()
                ->whereIn('durum', [Odeme::DURUM_BASARISIZ, Odeme::DURUM_IPTAL])
                ->where('created_at', '<', $odemeCutoff);

            $olayAdet = (clone $olaySorgu)->count();
            $odemeAdet = (clone $odemeSorgu)->count();

            if ($apply) {
                $silinenOlay = $olaySorgu->delete();
                $silinenOdeme = $odemeSorgu->delete();
                $this->info(sprintf('Retention temizligi tamamlandi | olay:%d odeme:%d', $silinenOlay, $silinenOdeme));
            } else {
                $this->info(sprintf('Dry-run | silinebilir olay:%d silinebilir odeme:%d', $olayAdet, $odemeAdet));
            }
        } finally {
            if ($apply) {
                optional($lock)->release();
            }
        }

        return self::SUCCESS;
    }
}
