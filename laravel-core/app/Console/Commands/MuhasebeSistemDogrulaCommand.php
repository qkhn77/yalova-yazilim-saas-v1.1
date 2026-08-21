<?php

namespace App\Console\Commands;

use App\Muhasebe\Servisler\MuhasebeSistemDogrulamaServisi;
use Illuminate\Console\Command;

class MuhasebeSistemDogrulaCommand extends Command
{
    protected $signature = 'muhasebe:sistem-dogrula
        {--firma_id= : Sadece belirtilen firma}
        {--dry-run : Log yazmadan sadece rapor}';

    protected $description = 'Fatura, cari, finans ve stok çapraz tutarlılık kontrolü.';

    public function handle(MuhasebeSistemDogrulamaServisi $servis): int
    {
        $firmaId = $this->option('firma_id') !== null && $this->option('firma_id') !== ''
            ? (int) $this->option('firma_id')
            : null;
        $dryRun = (bool) $this->option('dry-run');

        $hatalar = $servis->sistemTutarlilikKontrolu($firmaId, ! $dryRun);

        if ($hatalar === []) {
            $this->info('Tutarsızlık bulunamadı.');

            return self::SUCCESS;
        }

        $satirlar = array_map(fn (array $h) => [
            'kod' => $h['kod'] ?? '',
            'firma_id' => $h['firma_id'] ?? '',
            'kaynak_id' => $h['kaynak_id'] ?? '',
            'detay' => $h['detay'] ?? '',
        ], $hatalar);

        $this->table(['kod', 'firma_id', 'kaynak_id', 'detay'], $satirlar);
        $this->warn(sprintf('Toplam %d tutarsızlık.', count($hatalar)));

        return self::FAILURE;
    }
}
