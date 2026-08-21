<?php

namespace App\Console\Commands;

use App\Services\PersonelTakip\PersonelDevamsizlikServisi;
use Illuminate\Console\Command;

class PersonelDevamsizlikIsleCommand extends Command
{
    protected $signature = 'personel:devamsizlik-isle {--firma_id=} {--tarih=}';

    protected $description = 'Kapanmış vardiyalarda giriş kaydı olmayan personeller için devamsızlık kaydı oluşturur.';

    public function handle(PersonelDevamsizlikServisi $servis): int
    {
        $firmaId = $this->option('firma_id');
        $tarih = $this->option('tarih') ? (string) $this->option('tarih') : null;

        $ozet = $firmaId
            ? $servis->firmaIcinIsle((int) $firmaId, $tarih)
            : $servis->tumFirmalarIcinIsle($tarih);

        $this->info('İşlenen vardiya: '.(int) ($ozet['islenen_vardiya'] ?? 0));
        $this->info('Oluşturulan devamsızlık: '.(int) ($ozet['olusturulan_devamsizlik'] ?? 0));
        $this->info('Atlanan: '.(int) ($ozet['atlanan'] ?? 0));

        return self::SUCCESS;
    }
}
