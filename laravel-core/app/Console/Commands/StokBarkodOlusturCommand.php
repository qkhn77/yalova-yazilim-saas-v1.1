<?php

namespace App\Console\Commands;

use App\Models\Muhasebe\StokKarti;
use App\Muhasebe\Servisler\StokBarkodServisi;
use Illuminate\Console\Command;

class StokBarkodOlusturCommand extends Command
{
    protected $signature = 'stok:barkod-olustur
        {--firma_id= : Sadece belirtilen firmayi isler}
        {--stok_id= : Sadece belirtilen stok kartini isler}
        {--force : Mevcut barkodu olan kayitlari da yeniden uretir}
        {--dry-run : Yalnizca raporlar, veritabanina yazmaz}';

    protected $description = 'Stok kartlari icin eksik barkodlari otomatik uretir ve varsayilan barkod kayitlarini senkronize eder.';

    public function handle(StokBarkodServisi $servis): int
    {
        $firmaId = $this->option('firma_id') !== null ? (int) $this->option('firma_id') : null;
        $stokId = $this->option('stok_id') !== null ? (int) $this->option('stok_id') : null;
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        $stoklar = StokKarti::tenantScopeOlmadan(function () use ($firmaId, $stokId) {
            return StokKarti::query()
                ->select(['id', 'firma_id', 'kod', 'ad', 'barkod'])
                ->when($firmaId, fn ($query) => $query->where('firma_id', $firmaId))
                ->when($stokId, fn ($query) => $query->whereKey($stokId))
                ->orderBy('id')
                ->get();
        });

        if ($stoklar->isEmpty()) {
            $this->warn('İşlenecek stok kartı bulunamadı.');

            return self::SUCCESS;
        }

        $rows = [];
        $olusturulan = 0;
        $senkronizeEdilen = 0;
        $korunan = 0;

        foreach ($stoklar as $stok) {
            $mevcut = trim((string) ($stok->barkod ?? ''));
            $hedef = $servis->barkodBelirle($stok, $force);

            if ($dryRun) {
                $durum = $mevcut === '' ? 'OLUSTURULACAK' : ($force ? 'YENIDEN_URETILECEK' : 'SENKRONIZE_EDILECEK');
            } else {
                $servis->barkodUygula($stok, $hedef);
                if ($mevcut === '') {
                    $durum = 'OLUSTURULDU';
                    $olusturulan++;
                } elseif ($force && $mevcut !== $hedef) {
                    $durum = 'YENIDEN_URETILDI';
                    $olusturulan++;
                } else {
                    $durum = 'SENKRONIZE_EDILDI';
                    $senkronizeEdilen++;
                }
            }

            if ($mevcut !== '' && ! $force) {
                $korunan++;
            }

            $rows[] = [
                'stok_id' => (int) $stok->id,
                'firma_id' => (int) $stok->firma_id,
                'kod' => (string) $stok->kod,
                'eski_barkod' => $mevcut !== '' ? $mevcut : '-',
                'yeni_barkod' => $hedef,
                'durum' => $durum,
            ];
        }

        $this->table(['stok_id', 'firma_id', 'kod', 'eski_barkod', 'yeni_barkod', 'durum'], $rows);

        $this->info(sprintf(
            'Toplam: %d | Oluşturulan: %d | Senkronize edilen: %d | Korunan mevcut barkod: %d | Dry-run: %s',
            $stoklar->count(),
            $olusturulan,
            $senkronizeEdilen,
            $korunan,
            $dryRun ? 'evet' : 'hayır'
        ));

        return self::SUCCESS;
    }
}
