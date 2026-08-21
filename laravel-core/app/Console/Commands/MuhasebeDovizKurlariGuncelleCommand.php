<?php

namespace App\Console\Commands;

use App\Muhasebe\Servisler\DovizKurServisi;
use Illuminate\Console\Command;

class MuhasebeDovizKurlariGuncelleCommand extends Command
{
    protected $signature = 'muhasebe:doviz-kurlari-guncelle
        {--firma_id= : Tek bir firma ID}
        {--tarih= : Kur tarihi (Y-m-d)}
        {--baslangic= : Toplu cekim baslangic tarihi (Y-m-d)}
        {--bitis= : Toplu cekim bitis tarihi (Y-m-d)}
        {--kaynak= : Kaynak para birimi (ornek USD)}
        {--hedef= : Hedef para birimi (ornek TRY)}
        {--eksik-rapor : Eksik kur raporu uretir}
        {--manuel-ez : Manuel kayit varsa da otomatikle guncelle}';

    protected $description = 'Muhasebe doviz kurlarini otomatik gunceller.';

    public function handle(DovizKurServisi $servis): int
    {
        $firmaId = (int) ($this->option('firma_id') ?? 0);
        $tarih = $this->option('tarih') ? (string) $this->option('tarih') : null;
        $baslangic = $this->option('baslangic') ? (string) $this->option('baslangic') : null;
        $bitis = $this->option('bitis') ? (string) $this->option('bitis') : null;
        $kaynak = $this->option('kaynak') ? strtoupper((string) $this->option('kaynak')) : null;
        $hedef = $this->option('hedef') ? strtoupper((string) $this->option('hedef')) : null;
        $eksikRapor = (bool) $this->option('eksik-rapor');

        if (($kaynak && ! $hedef) || (! $kaynak && $hedef)) {
            $this->error('--kaynak ve --hedef birlikte verilmelidir.');

            return self::FAILURE;
        }
        if (($baslangic && ! $bitis) || (! $baslangic && $bitis)) {
            $this->error('--baslangic ve --bitis birlikte verilmelidir.');

            return self::FAILURE;
        }
        if ($eksikRapor && $firmaId < 1) {
            $this->error('--eksik-rapor icin --firma_id zorunludur.');

            return self::FAILURE;
        }
        if ($eksikRapor && (! $baslangic || ! $bitis)) {
            $this->error('--eksik-rapor icin --baslangic ve --bitis zorunludur.');

            return self::FAILURE;
        }

        if ($kaynak && $hedef) {
            if ($firmaId < 1) {
                $this->error('Tek parite guncellemede --firma_id zorunludur.');

                return self::FAILURE;
            }

            try {
                $sonuc = $servis->otomatikKurKaydet($firmaId, $kaynak, $hedef, $tarih);
                $this->info('Guncellendi: '.$kaynak.'->'.$hedef.' '.$sonuc['kur'].' ('.$sonuc['tarih'].')');

                return self::SUCCESS;
            } catch (\Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
        }

        $manuelEz = (bool) $this->option('manuel-ez');
        if ($eksikRapor) {
            $rapor = $servis->eksikKurRaporu($firmaId, $baslangic, $bitis);
            $this->info('Beklenen: '.$rapor['beklenen'].' | Mevcut: '.$rapor['mevcut'].' | Eksik: '.$rapor['eksik']);
            foreach ($rapor['satirlar'] as $satir) {
                $this->line($satir['tarih'].' '.$satir['kaynak'].'->'.$satir['hedef']);
            }
            if ($rapor['eksik'] > count($rapor['satirlar'])) {
                $this->line('... liste siniri nedeniyle kismi gosterim');
            }

            return $rapor['eksik'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        if ($firmaId > 0 && $baslangic && $bitis) {
            $rapor = $servis->firmaIcinTarihAraligindaBazParitelereOtomatikKurYukle($firmaId, $baslangic, $bitis, $manuelEz);
            $this->info('Tamamlandi. Gun: '.$rapor['gun'].' | Basarili: '.$rapor['ok'].' | Hatali: '.$rapor['hata']);

            return $rapor['hata'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        $rapor = $firmaId > 0
            ? $servis->firmaIcinBazParitelereOtomatikKurYukle($firmaId, $tarih, $manuelEz)
            : $servis->tumFirmalarIcinBazParitelereOtomatikKurYukle($tarih, $manuelEz);

        $this->info('Tamamlandi. Basarili: '.$rapor['ok'].' | Hatali: '.$rapor['hata']);

        return $rapor['hata'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
