<?php

namespace App\Console\Commands;

use App\Models\Ecommerce\Siparis;
use App\Modules\Urun\Servisler\SiparisOdemeServisi;
use Illuminate\Console\Command;

class SiparisOdemeZamanAsimiKomutu extends Command
{
    protected $signature = 'siparis:odeme-zaman-asimi-isle {--firma_id= : Belirli firmaya ait siparişleri işle (opsiyonel).}';

    protected $description = 'Ödeme süresi dolmuş onay bekleyen siparişleri "Başarısız Ödeme" durumuna çeker (rezerv iade).';

    public function handle(SiparisOdemeServisi $siparisOdemeServisi): int
    {
        $firmaId = $this->option('firma_id');

        $ids = Siparis::query()
            ->whereIn('durum', [Siparis::DURUM_ONAY_BEKLIYOR, Siparis::DURUM_ODEME_BEKLENIYOR])
            ->whereNotNull('odeme_suresi_bitis_at')
            ->where('odeme_suresi_bitis_at', '<', now())
            ->when(is_numeric($firmaId) && (int) $firmaId > 0, fn ($q) => $q->where('firma_id', (int) $firmaId))
            ->pluck('id');

        $say = 0;
        foreach ($ids as $id) {
            $siparis = Siparis::query()->find($id);
            if ($siparis instanceof Siparis) {
                $siparisOdemeServisi->siparisZamanAsimindaIptal($siparis);
                $say++;
            }
        }

        if ($say > 0) {
            $firmaText = is_numeric($firmaId) && (int) $firmaId > 0 ? ' (firma_id: '.(int) $firmaId.')' : '';
            $this->info("Başarısız ödeme / süre dolumu işlendi: {$say} sipariş.{$firmaText}");
        }

        return self::SUCCESS;
    }
}

