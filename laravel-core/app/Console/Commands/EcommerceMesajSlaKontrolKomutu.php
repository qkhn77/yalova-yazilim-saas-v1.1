<?php

namespace App\Console\Commands;

use App\Services\EcommerceMesajServisi;
use Illuminate\Console\Command;

class EcommerceMesajSlaKontrolKomutu extends Command
{
    protected $signature = 'ecommerce:mesaj-sla-kontrol {--firma_id= : Belirli firma icin kontrol et}';

    protected $description = 'E-ticaret mesaj konularinda 12 saat SLA ihlal durumlarini gunceller.';

    public function handle(EcommerceMesajServisi $mesajServisi): int
    {
        $firmaId = $this->option('firma_id');
        $guncellenen = $mesajServisi->slaDurumlariniGuncelle(
            is_numeric((string) $firmaId) ? (int) $firmaId : null
        );

        $this->info('SLA kontrol tamamlandi. Guncellenen konu: '.$guncellenen);

        return self::SUCCESS;
    }
}