<?php

namespace App\Console\Commands;

use App\Services\ReconciliationBakimServisi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class StokRezervDogrulaCommand extends Command
{
    protected $signature = 'stok:rezerv-dogrula
        {--firma_id= : Sadece belirtilen firma}
        {--dry-run : Sadece raporla, veri degistirme}
        {--fix : Rezerv tutarsizliklarini duzelt}
        {--force : Production ortaminda fix onayi bypass}';

    protected $description = 'Stok rezerv/miktar tutarliligini denetler (default dry-run).';

    public function handle(ReconciliationBakimServisi $servis): int
    {
        $fix = (bool) $this->option('fix');
        if ((bool) $this->option('dry-run')) {
            $fix = false;
        }
        $firmaId = $this->option('firma_id') !== null ? (int) $this->option('firma_id') : null;

        if ($fix && app()->environment('production') && ! (bool) $this->option('force')) {
            if (! $this->confirm('Production ortaminda FIX calisacak. Devam edilsin mi?')) {
                $this->warn('Islem iptal edildi.');

                return self::INVALID;
            }
        }

        $lock = Cache::lock('reconcile:stok:rezerv:fix', 600);
        if ($fix && ! $lock->get()) {
            $this->error('Baska bir stok rezerv fix islemi calisiyor.');

            return self::FAILURE;
        }

        try {
            $sonuc = $servis->stokRezervReconcile($firmaId, $fix);
        } finally {
            if ($fix) {
                optional($lock)->release();
            }
        }

        $this->info(sprintf(
            'Stok rezerv dogrulama tamamlandi | kontrol:%d bulunan:%d duzeltilen:%d mod:%s',
            $sonuc['kontrol_edilen'],
            $sonuc['bulunan'],
            $sonuc['duzeltilen'],
            $fix ? 'fix' : 'dry-run'
        ));

        if (! empty($sonuc['sorunlar'])) {
            $this->table(['kod', 'firma_id', 'stok_id', 'detay', 'duzeltilebilir'], array_map(
                static fn (array $s): array => [
                    $s['kod'] ?? '',
                    $s['firma_id'] ?? '',
                    $s['stok_id'] ?? '',
                    $s['detay'] ?? '',
                    ($s['duzeltilebilir'] ?? false) ? 'evet' : 'hayir',
                ],
                $sonuc['sorunlar']
            ));
        }

        return $sonuc['bulunan'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
