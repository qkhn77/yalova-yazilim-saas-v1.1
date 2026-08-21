<?php

namespace App\Console\Commands;

use App\Models\Firma;
use App\Models\Proje\IsletmeProjesi;
use Illuminate\Console\Command;

class ProjeTestVerisiOlusturCommand extends Command
{
    protected $signature = 'proje:test-verisi
        {--firma= : Yalnızca belirtilen firma ID için kayıt oluşturur}
        {--force : Üretim ortamında da çalıştırmaya izin verir}';

    protected $description = 'Firma için üç örnek proje oluşturur.';

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Üretim ortamında test verisi için --force kullanmalısınız.');
            return self::FAILURE;
        }

        $firmalar = Firma::query()
            ->where('durum', Firma::DURUM_AKTIF)
            ->when($this->option('firma'), fn ($query, $firmaId) => $query->whereKey((int) $firmaId))
            ->get(['id', 'ad']);

        $projeler = [
            ['kod' => 'TEST-PRJ-001', 'ad' => 'Merkez Ofis Kamera Projesi', 'butce_tutari' => '150000.00'],
            ['kod' => 'TEST-PRJ-002', 'ad' => 'Şantiye Güvenlik Sistemi Projesi', 'butce_tutari' => '275000.00'],
            ['kod' => 'TEST-PRJ-003', 'ad' => 'Depo ve Çevre İzleme Projesi', 'butce_tutari' => '95000.00'],
        ];

        $olusturulan = 0;
        foreach ($firmalar as $firma) {
            foreach ($projeler as $proje) {
                $kayit = IsletmeProjesi::query()->firstOrCreate(
                    ['firma_id' => $firma->id, 'kod' => $proje['kod']],
                    [
                        'ad' => $proje['ad'],
                        'durum' => IsletmeProjesi::DURUM_AKTIF,
                        'butce_tutari' => $proje['butce_tutari'],
                        'para_birimi' => 'TRY',
                        'baslangic_tarihi' => now()->startOfYear()->toDateString(),
                        'aciklama' => 'Sistem test verisi',
                    ],
                );

                if ($kayit->wasRecentlyCreated) {
                    $olusturulan++;
                }
            }

            $this->line($firma->ad.': 3 test projesi kontrol edildi.');
        }

        $this->info($olusturulan.' yeni test projesi oluşturuldu.');
        return self::SUCCESS;
    }
}
