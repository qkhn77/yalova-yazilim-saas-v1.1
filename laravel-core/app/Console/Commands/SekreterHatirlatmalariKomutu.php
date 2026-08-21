<?php

namespace App\Console\Commands;

use App\Filament\Clusters\Sekreter\Resources\GorevKaynagi;
use App\Filament\Clusters\Sekreter\Resources\RandevuKaynagi;
use App\Models\Iletisim\KullaniciBildirimi;
use App\Models\SekreterGorevi;
use App\Models\SekreterHatirlatmasi;
use App\Models\SekreterRandevusu;
use App\Services\ModulErisimService;
use App\Services\SekreterHatirlatmaServisi;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class SekreterHatirlatmalariKomutu extends Command
{
    protected $signature = 'sekreter:hatirlatmalari-gonder';
    protected $description = 'Ajanda ve Görevler görev ve randevu hatırlatmalarını uygulama içi bildirim olarak üretir.';

    public function handle(): int
    {
        $modul = app(ModulErisimService::class);
        $hatirlatma = app(SekreterHatirlatmaServisi::class);
        $simdi = now();
        $sayac = 0;

        foreach (SekreterGorevi::query()->whereNotIn('durum', ['tamamlandi', 'iptal'])->where('hatirlatma_tipi', '!=', 'yok')->get() as $gorev) {
            $sayac += $this->bildirimUret($gorev, $gorev->atanan_kullanici_id ?: $gorev->olusturan_kullanici_id, $simdi, $modul, $hatirlatma, 'Görev zamanı yaklaşıyor.', GorevKaynagi::class);
        }

        foreach (SekreterRandevusu::query()->where('hatirlatma_tipi', '!=', 'yok')->get() as $randevu) {
            $sayac += $this->bildirimUret($randevu, $randevu->olusturan_kullanici_id, $simdi, $modul, $hatirlatma, 'Randevu zamanı yaklaşıyor.', RandevuKaynagi::class);
        }

        $this->info($sayac.' hatırlatma bildirimi üretildi.');
        return self::SUCCESS;
    }

    private function bildirimUret(Model $kayit, ?int $kullaniciId, Carbon $simdi, ModulErisimService $modul, SekreterHatirlatmaServisi $hatirlatma, string $mesaj, string $resource): int
    {
        $firmaId = (int) $kayit->firma_id;
        $kaynakTuru = $hatirlatma->kaynakTuru($kayit);
        $kaynakId = (int) $kayit->getKey();
        if (! $kullaniciId || ! $modul->modulErisilebilirMi($firmaId, 'sekreter')) {
            return 0;
        }

        $bildirimZamani = $hatirlatma->hatirlatmaZamani($kayit, $simdi);
        if (! $bildirimZamani) {
            return 0;
        }

        if ($simdi->lt($bildirimZamani) || $simdi->gt($bildirimZamani->copy()->addMinutes(2))) {
            return 0;
        }

        $hatirlatmaKaydi = SekreterHatirlatmasi::query()->firstOrCreate([
            'firma_id' => $firmaId,
            'hatirlanabilir_type' => $hatirlatma->hatirlanabilirModel($kayit),
            'hatirlanabilir_id' => $kaynakId,
            'hatirlatma_tipi' => (string) $kayit->hatirlatma_tipi,
            'hatirlatma_zamani' => $bildirimZamani,
        ]);
        if ($hatirlatmaKaydi->gonderildi_at) {
            return 0;
        }

        KullaniciBildirimi::query()->create([
            'firma_id' => $firmaId,
            'kullanici_id' => $kullaniciId,
            'kaynak_turu' => $kaynakTuru,
            'kaynak_id' => $kaynakId,
            'baslik' => (string) $kayit->baslik,
            'mesaj' => $mesaj,
            'seviye' => 'warning',
            'aksiyon_url' => $resource::getUrl('edit', ['record' => $kayit]),
            'data' => ['hatirlatma_zamani' => $bildirimZamani->toIso8601String()],
        ]);
        $hatirlatmaKaydi->forceFill(['gonderildi_at' => now()])->save();

        return 1;
    }
}
