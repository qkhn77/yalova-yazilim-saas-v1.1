<?php

namespace App\Console\Commands;

use App\Models\Muhasebe\StokHareketi;
use App\Models\Muhasebe\StokKarti;
use App\Muhasebe\Enumlar\StokHareketDurumu;
use App\Muhasebe\Servisler\StokMaliyetHesaplamaServisi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class StokMaliyetYenidenHesaplaCommand extends Command
{
    protected $signature = 'stok:maliyet-yeniden-hesapla
        {--dry-run : Sadece raporlar, yazmaz}
        {--firma_id= : Sadece belirtilen firma}
        {--stok_id= : Sadece belirtilen stok}
        {--sadece-hatali : Sadece maliyet tutarsızı olanları göster}';

    protected $description = 'Stok hareketlerinden ağırlıklı ortalama maliyeti yeniden hesaplar ve tutarsızlıkları raporlar.';

    public function handle(StokMaliyetHesaplamaServisi $servis): int
    {
        if ($this->canliOrtamdaBloklanmali()) {
            $this->error('Canlı ortamda stok maliyet rebuild kapalı. MUHASEBE_STOK_REBUILD_CANLI_IZINLI=true olmadan çalıştırılamaz.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $firmaId = $this->option('firma_id') !== null ? (int) $this->option('firma_id') : null;
        $stokId = $this->option('stok_id') !== null ? (int) $this->option('stok_id') : null;
        $sadeceHatali = (bool) $this->option('sadece-hatali');

        $stoklar = StokKarti::query()
            ->withoutGlobalScopes()
            ->when($firmaId, fn ($q) => $q->where('firma_id', $firmaId))
            ->when($stokId, fn ($q) => $q->whereKey($stokId))
            ->orderBy('id')
            ->get();
        if ($stoklar->isEmpty()) {
            $this->warn('İşlenecek stok kartı bulunamadı.');

            return self::SUCCESS;
        }

        Log::channel((string) config('muhasebe.stok.log_channel', 'muhasebe'))->info('stok.rebuild.basladi', [
            'dry_run' => $dryRun,
            'firma_id' => $firmaId,
            'stok_id' => $stokId,
            'sadece_hatali' => $sadeceHatali,
        ]);

        $satirlar = [];
        $hatali = 0;
        $zincirBozuk = 0;
        foreach ($stoklar as $stok) {
            $ort = '0.00';
            $deger = '0.00';
            $miktar = '0.0000';
            $sonGiris = null;
            $sonTarih = null;

            $hareketler = StokHareketi::query()
                ->withoutGlobalScopes()
                ->where('stok_id', $stok->id)
                ->where('durum', StokHareketDurumu::Aktif)
                ->orderBy('islem_tarihi')
                ->orderBy('id')
                ->get();
            foreach ($hareketler as $h) {
                $hesap = $servis->hareketMaliyetiHesapla([
                    'stok_takip' => (bool) ($stok->stok_takip ?? true),
                    'onceki_miktar' => $miktar,
                    'miktar' => (string) $h->miktar,
                    'birim_maliyet' => (string) ($h->birim_maliyet ?? $h->birim_fiyat ?? 0),
                    'islem_turu' => $h->islem_turu,
                    'mevcut_ortalama' => $ort,
                    'mevcut_stok_degeri' => $deger,
                    'tarih' => $h->islem_tarihi,
                ]);
                $miktar = (string) $h->sonraki_miktar;
                $ort = $hesap['yeni_ortalama'];
                $deger = $hesap['yeni_stok_degeri'];
                $sonGiris = $hesap['son_giris_maliyeti'] ?? $sonGiris;
                $sonTarih = $hesap['son_hareket_tarihi'] ?? $sonTarih;
            }

            $zincir = $servis->stokZincirSaglikKontrolu((int) $stok->id);
            if (! $zincir['saglikli']) {
                $zincirBozuk++;
                Log::channel((string) config('muhasebe.stok.log_channel', 'muhasebe'))->warning('stok.zincir.bozuk', [
                    'stok_id' => (int) $stok->id,
                    'firma_id' => (int) $stok->firma_id,
                    'sorunlar' => $zincir['sorunlar'],
                ]);
                if ((bool) config('muhasebe.stok.zincir_hata_hard_fail', false)) {
                    throw new RuntimeException(sprintf(
                        'Stok zinciri bozuk (stok_id=%d): %s',
                        (int) $stok->id,
                        implode('; ', $zincir['sorunlar'])
                    ));
                }
            }

            $fark = bccomp((string) ($stok->guncel_birim_maliyet ?? 0), $ort, 2) !== 0
                || bccomp((string) ($stok->stok_degeri ?? 0), $deger, 2) !== 0;
            if ($fark) {
                $hatali++;
                Log::channel((string) config('muhasebe.stok.log_channel', 'muhasebe'))->warning('stok.maliyet.tutarsizlik', [
                    'stok_id' => (int) $stok->id,
                    'firma_id' => (int) $stok->firma_id,
                    'ort_mevcut' => (string) $stok->guncel_birim_maliyet,
                    'ort_beklenen' => $ort,
                    'deger_mevcut' => (string) $stok->stok_degeri,
                    'deger_beklenen' => $deger,
                ]);
            }
            if (! $sadeceHatali || $fark) {
                $satirlar[] = [
                    'stok_id' => $stok->id,
                    'firma_id' => $stok->firma_id,
                    'ort_mevcut' => (string) $stok->guncel_birim_maliyet,
                    'ort_beklenen' => $ort,
                    'deger_mevcut' => (string) $stok->stok_degeri,
                    'deger_beklenen' => $deger,
                    'zincir' => $zincir['saglikli'] ? 'OK' : 'BOZUK',
                    'durum' => $fark ? 'TUTARSIZ' : 'OK',
                ];
            }

            if ($fark && ! $dryRun) {
                $stok->update([
                    'guncel_birim_maliyet' => $ort,
                    'stok_degeri' => $deger,
                    'son_giris_maliyeti' => $sonGiris,
                    'son_hareket_tarihi' => $sonTarih,
                ]);
            }
        }

        $this->table(['stok_id', 'firma_id', 'ort_mevcut', 'ort_beklenen', 'deger_mevcut', 'deger_beklenen', 'zincir', 'durum'], $satirlar);
        $this->info(sprintf('Toplam: %d | Tutarsız: %d | Zincir bozuk: %d | dry-run: %s', $stoklar->count(), $hatali, $zincirBozuk, $dryRun ? 'evet' : 'hayır'));
        if ($hatali > 0) {
            Log::channel((string) config('muhasebe.stok.log_channel', 'muhasebe'))->warning('stok.maliyet.yeniden_hesapla.tutarsiz', [
                'toplam' => $stoklar->count(),
                'tutarsiz' => $hatali,
                'firma_id' => $firmaId,
                'stok_id' => $stokId,
                'dry_run' => $dryRun,
            ]);
        }

        Log::channel((string) config('muhasebe.stok.log_channel', 'muhasebe'))->info('stok.rebuild.bitti', [
            'toplam' => $stoklar->count(),
            'tutarsiz' => $hatali,
            'zincir_bozuk' => $zincirBozuk,
            'dry_run' => $dryRun,
            'firma_id' => $firmaId,
            'stok_id' => $stokId,
        ]);

        return ($hatali > 0 || $zincirBozuk > 0) ? self::FAILURE : self::SUCCESS;
    }

    private function canliOrtamdaBloklanmali(): bool
    {
        return app()->environment('production') && ! (bool) config('muhasebe.stok.rebuild_canli_izinli', false);
    }
}
