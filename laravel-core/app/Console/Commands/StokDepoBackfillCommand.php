<?php

namespace App\Console\Commands;

use App\Models\Muhasebe\Depo;
use App\Models\Muhasebe\StokDepoBakiyesi;
use App\Models\Muhasebe\StokKarti;
use App\Services\FirmaAyarDeposu;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class StokDepoBackfillCommand extends Command
{
    protected $signature = 'stok:depo-backfill
        {--dry-run : Yalnızca aktarılabilecek kayıtları raporlar}
        {--apply : Uygun kayıtlar için depo bakiyesi oluşturur}
        {--firma_id= : Sadece belirtilen firmayı işler}
        {--stok_id= : Sadece belirtilen stok kartını işler}';

    protected $description = 'Legacy stok kartlarını güvenli biçimde depo bakiyelerine aktarır.';

    public function handle(FirmaAyarDeposu $ayarDeposu): int
    {
        $apply = (bool) $this->option('apply');
        $dryRun = (bool) $this->option('dry-run') || ! $apply;
        $firmaId = $this->option('firma_id') !== null ? (int) $this->option('firma_id') : null;
        $stokId = $this->option('stok_id') !== null ? (int) $this->option('stok_id') : null;

        $stoklar = StokKarti::query()
            ->select(['id', 'firma_id', 'kod', 'ad', 'stok_miktari', 'depo_id'])
            ->when($firmaId, fn ($query) => $query->where('firma_id', $firmaId))
            ->when($stokId, fn ($query) => $query->whereKey($stokId))
            ->orderBy('id')
            ->get();

        $sayac = ['incelendi' => 0, 'aktarilabilir' => 0, 'aktarildi' => 0, 'atlandi' => 0, 'depo_yok' => 0, 'bakiye_var' => 0];
        $satirlar = [];

        foreach ($stoklar as $stok) {
            $sayac['incelendi']++;
            $depoId = $this->depoIdBul($stok, $ayarDeposu);
            $neden = null;

            if ($depoId === null) {
                $neden = 'Depo modülü kapalı veya geçerli varsayılan depo yok';
                $sayac['depo_yok']++;
            } elseif (StokDepoBakiyesi::query()
                ->where('firma_id', (int) $stok->firma_id)
                ->where('stok_id', (int) $stok->id)
                ->exists()) {
                $neden = 'Bu stok için depo bakiyesi zaten var';
                $sayac['bakiye_var']++;
            }

            if ($neden !== null) {
                $sayac['atlandi']++;
                if ($dryRun && str_contains($neden, 'zaten')) {
                    $satirlar[] = $this->satir($stok, $depoId, 'ATLANDI', $neden);
                }

                continue;
            }

            $sayac['aktarilabilir']++;
            $satirlar[] = $this->satir($stok, $depoId, $dryRun ? 'AKTARILABILIR' : 'AKTARILDI', null);

            if (! $dryRun) {
                DB::transaction(function () use ($stok, $depoId): void {
                    StokDepoBakiyesi::query()->firstOrCreate(
                        [
                            'firma_id' => (int) $stok->firma_id,
                            'depo_id' => $depoId,
                            'stok_id' => (int) $stok->id,
                        ],
                        [
                            'miktar' => (string) ($stok->stok_miktari ?? 0),
                            'rezerve_miktar' => '0',
                        ],
                    );
                });
                $sayac['aktarildi']++;
            }
        }

        if ($satirlar !== []) {
            $this->table(['stok_id', 'firma_id', 'kod', 'depo_id', 'miktar', 'durum', 'not'], $satirlar);
        }

        $this->info(sprintf(
            'Tamamlandı. İncelendi: %d | Aktarılabilir: %d | Aktarıldı: %d | Atlandı: %d | Depo yok: %d | Bakiye var: %d | Mod: %s',
            $sayac['incelendi'],
            $sayac['aktarilabilir'],
            $sayac['aktarildi'],
            $sayac['atlandi'],
            $sayac['depo_yok'],
            $sayac['bakiye_var'],
            $dryRun ? 'dry-run' : 'apply',
        ));

        return self::SUCCESS;
    }

    private function depoIdBul(StokKarti $stok, FirmaAyarDeposu $ayarDeposu): ?int
    {
        if (! (bool) $ayarDeposu->oku((int) $stok->firma_id, 'stok_depo_modulu_aktif_mi', false)) {
            return null;
        }

        $adaylar = [
            (int) ($stok->depo_id ?? 0),
            (int) ($ayarDeposu->oku((int) $stok->firma_id, 'stok_varsayilan_depo_id', 0) ?? 0),
        ];

        foreach ($adaylar as $depoId) {
            if ($depoId > 0 && Depo::query()
                ->where('firma_id', (int) $stok->firma_id)
                ->whereKey($depoId)
                ->where('aktif_mi', true)
                ->exists()) {
                return $depoId;
            }
        }

        return Depo::query()
            ->where('firma_id', (int) $stok->firma_id)
            ->where('aktif_mi', true)
            ->where('varsayilan_mi', true)
            ->value('id');
    }

    /** @return array<string, int|string|null> */
    private function satir(StokKarti $stok, ?int $depoId, string $durum, ?string $not): array
    {
        return [
            'stok_id' => (int) $stok->id,
            'firma_id' => (int) $stok->firma_id,
            'kod' => (string) $stok->kod,
            'depo_id' => $depoId,
            'miktar' => (string) ($stok->stok_miktari ?? 0),
            'durum' => $durum,
            'not' => $not,
        ];
    }
}
