<?php

namespace App\Console\Commands;

use App\Services\EcommercePazaryeriSiparisCekmeServisi;
use Illuminate\Console\Command;

class EcommercePazaryeriSiparisCekmeKomutu extends Command
{
    protected $signature = 'ecommerce:pazaryeri-siparis-cek
                            {--firma_id= : Belirli firma icin calistir}
                            {--pazaryeri= : Belirli pazaryeri kodu icin calistir}';

    protected $description = 'Pazaryeri entegrasyonlari icin siparis cekme ve retry surecini calistirir.';

    public function handle(EcommercePazaryeriSiparisCekmeServisi $servis): int
    {
        $firmaId = $this->option('firma_id');
        $pazaryeri = $this->option('pazaryeri');

        $ozet = $servis->calistir(
            firmaId: is_numeric((string) $firmaId) ? (int) $firmaId : null,
            pazaryeriKodu: is_string($pazaryeri) ? trim($pazaryeri) : null,
        );

        $this->info('Pazaryeri siparis cekme tamamlandi.');
        $this->line('Islenen: '.(int) $ozet['islenen']);
        $this->line('Basarili: '.(int) $ozet['basarili']);
        $this->line('Hatali: '.(int) $ozet['hatali']);
        $this->line('Atlanan: '.(int) $ozet['atlanan']);
        $this->line('Import edilen: '.(int) $ozet['import_edilen']);

        return self::SUCCESS;
    }
}