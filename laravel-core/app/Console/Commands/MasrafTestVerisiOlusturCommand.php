<?php

namespace App\Console\Commands;

use App\Models\Firma;
use App\Models\Muhasebe\Masraf;
use App\Models\Muhasebe\MasrafKategorisi;
use Illuminate\Console\Command;

class MasrafTestVerisiOlusturCommand extends Command
{
    protected $signature = 'masraf:test-verisi
        {--firma= : Yalnızca belirtilen firma ID için kayıt oluşturur}
        {--adet=3 : Her masraf türü için oluşturulacak kayıt sayısı}
        {--force : Üretim ortamında da çalıştırmaya izin verir}';

    protected $description = 'Her aktif masraf türü için idempotent test masrafları oluşturur.';

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Üretim ortamında test verisi için --force kullanmalısınız.');
            return self::FAILURE;
        }

        $adet = max(1, min(20, (int) $this->option('adet')));
        $firmaId = $this->option('firma');
        $firmalar = Firma::query()
            ->where('durum', Firma::DURUM_AKTIF)
            ->when($firmaId !== null && $firmaId !== '', fn ($query) => $query->whereKey((int) $firmaId))
            ->get(['id', 'ad']);

        if ($firmalar->isEmpty()) {
            $this->warn('Aktif firma bulunamadı.');
            return self::SUCCESS;
        }

        $olusturulan = 0;
        foreach ($firmalar as $firma) {
            MasrafKategorisi::varsayilanlariHazirla((int) $firma->id);
            $kategoriler = MasrafKategorisi::query()
                ->where('firma_id', $firma->id)
                ->aktif()
                ->where('secilir_mi', true)
                ->orderBy('sira')
                ->orderBy('id')
                ->get(['id', 'ad']);

            foreach ($kategoriler as $kategoriIndex => $kategori) {
                for ($sira = 1; $sira <= $adet; $sira++) {
                    $key = sprintf('masraf-test-verisi:%d:%d:%d', $firma->id, $kategori->id, $sira);
                    $tutar = number_format(100 + (($kategoriIndex + 1) * 10) + ($sira * 5), 2, '.', '');

                    $masraf = Masraf::query()->firstOrCreate(
                        ['firma_id' => $firma->id, 'idempotency_key' => $key],
                        [
                            'masraf_kategorisi_id' => $kategori->id,
                            'tarih' => now()->subDays((($kategoriIndex * $adet) + $sira) % 28)->toDateString(),
                            'tutar' => $tutar,
                            'para_birimi' => 'TRY',
                            'aciklama' => $kategori->ad.' test masrafı '.$sira,
                            'notlar' => 'Sistem test verisi',
                            'durum' => Masraf::DURUM_AKTIF,
                        ],
                    );

                    if ($masraf->wasRecentlyCreated) {
                        $olusturulan++;
                    }
                }
            }

            $this->line(sprintf('%s: %d masraf türü için %d kayıt kontrol edildi.', $firma->ad, $kategoriler->count(), $kategoriler->count() * $adet));
        }

        $this->info($olusturulan.' yeni test masrafı oluşturuldu.');
        return self::SUCCESS;
    }
}
