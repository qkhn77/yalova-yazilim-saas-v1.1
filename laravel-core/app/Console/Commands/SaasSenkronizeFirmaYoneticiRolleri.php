<?php

namespace App\Console\Commands;

use App\Models\Rol;
use Illuminate\Console\Command;

/**
 * Kanonik `firma_yoneticisi` rolündeki yetkileri, kopya rollere (kod: firma_yoneticisi_*) kopyalar.
 * Pivot boş kalan "Firma Yöneticisi" kopyalarında sidebar/policy tutarsızlığını giderir.
 */
class SaasSenkronizeFirmaYoneticiRolleri extends Command
{
    protected $signature = 'saas:senkronize-firma-yonetici-rolleri';

    protected $description = 'firma_yoneticisi_* rollerinin yetkilerini kanonik firma_yoneticisi ile eşitler';

    public function handle(): int
    {
        $kaynak = Rol::query()->where('kod', 'firma_yoneticisi')->first();
        if (! $kaynak) {
            $this->error('Kanonik rol bulunamadı: kod = firma_yoneticisi');

            return self::FAILURE;
        }

        $yetkiIdleri = $kaynak->yetkiler()->pluck('yetkiler.id')->all();
        if ($yetkiIdleri === []) {
            $this->warn('Kanonik firma_yoneticisi rolünde yetki yok. Önce SaasRolePermissionMatrixSeeder çalıştırın.');

            return self::FAILURE;
        }

        $hedefler = Rol::query()
            ->where('id', '!=', $kaynak->id)
            ->where('sistem_rolu_mu', true)
            ->get()
            ->filter(fn (Rol $r): bool => str_starts_with((string) $r->kod, 'firma_yoneticisi_'));

        if ($hedefler->isEmpty()) {
            $this->info('Senkronize edilecek firma_yoneticisi_* rolü yok.');

            return self::SUCCESS;
        }

        foreach ($hedefler as $rol) {
            $rol->yetkiler()->sync($yetkiIdleri);
            $this->line("Güncellendi: {$rol->ad} ({$rol->kod}) — ".count($yetkiIdleri).' yetki');
        }

        $this->info('Tamamlandı.');

        return self::SUCCESS;
    }
}
