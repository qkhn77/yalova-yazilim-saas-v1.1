<?php

namespace App\Console\Commands;

use App\Models\Muhasebe\StokKarti;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StokYenidenHesaplaCommand extends Command
{
    protected $signature = 'stok:yeniden-hesapla
        {--dry-run : Yalnızca farkları raporlar, veritabanına yazmaz}
        {--firma_id= : Sadece belirtilen firmayı işler}
        {--stok_id= : Sadece belirtilen stok kartını işler}
        {--sadece-farkli : Çıktıda yalnızca farklı kayıtları gösterir}
        {--riskli-duzelt : İncelenmeli kayıtları da günceller (önerilmez)}';

    protected $description = 'Stok kartı miktarlarını stok hareketlerinden yeniden hesaplar ve gerekirse düzeltir.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $firmaId = $this->option('firma_id') !== null ? (int) $this->option('firma_id') : null;
        $stokId = $this->option('stok_id') !== null ? (int) $this->option('stok_id') : null;
        $sadeceFarkli = (bool) $this->option('sadece-farkli');
        $riskliDuzelt = (bool) $this->option('riskli-duzelt');
        $batch = max(100, (int) config('muhasebe.stok.yeniden_hesaplama_batch', 500));

        $stokSorgu = StokKarti::query()
            ->select(['id', 'firma_id', 'kod', 'ad', 'stok_miktari'])
            ->when($firmaId, fn ($q) => $q->where('firma_id', $firmaId))
            ->when($stokId, fn ($q) => $q->whereKey($stokId));

        $stoklar = $stokSorgu->orderBy('id')->get();
        if ($stoklar->isEmpty()) {
            $this->warn('İşlenecek stok kartı bulunamadı.');

            return self::SUCCESS;
        }

        $hareketOzeti = $this->hareketOzetiniGetir($firmaId, $stokId);
        $farkli = 0;
        $guncellenen = 0;
        $incelenmeli = 0;
        $atlanan = 0;
        $toplam = $stoklar->count();

        $this->info(sprintf('Stok yeniden hesaplama başladı. Toplam kart: %d | dry-run: %s', $toplam, $dryRun ? 'evet' : 'hayır'));

        $satirlar = [];
        foreach ($stoklar as $stok) {
            $ozet = $hareketOzeti[(int) $stok->id] ?? ['net' => '0', 'aktif_sayi' => 0, 'toplam_sayi' => 0];
            $beklenen = (string) ($ozet['net'] ?? '0');
            $mevcut = (string) ($stok->stok_miktari ?? '0');
            $esit = bccomp($mevcut, $beklenen, 4) === 0;
            $riskDurumu = $this->riskDurumuBelirle($mevcut, $beklenen, (int) $ozet['aktif_sayi'], (int) $ozet['toplam_sayi']);

            if (! $esit) {
                $farkli++;
                if ($riskDurumu !== null) {
                    $incelenmeli++;
                }
                if (! $dryRun && ($riskDurumu === null || $riskliDuzelt)) {
                    StokKarti::query()->whereKey($stok->id)->update(['stok_miktari' => $beklenen]);
                    $guncellenen++;
                } elseif (! $dryRun && $riskDurumu !== null) {
                    $atlanan++;
                }
            }

            if (! $sadeceFarkli || ! $esit) {
                $durum = $esit ? 'OK' : ($dryRun ? 'FARK' : 'GUNCELLENDI');
                if (! $esit && $riskDurumu !== null) {
                    $durum = $dryRun
                        ? 'INCELENMELI_'.$riskDurumu
                        : ($riskliDuzelt ? 'RISKLI_GUNCELLENDI_'.$riskDurumu : 'ATLANDI_'.$riskDurumu);
                }
                $satirlar[] = [
                    'stok_id' => (int) $stok->id,
                    'firma_id' => (int) $stok->firma_id,
                    'kod' => (string) $stok->kod,
                    'mevcut' => $mevcut,
                    'beklenen' => $beklenen,
                    'durum' => $durum,
                ];
            }
        }

        foreach (array_chunk($satirlar, $batch) as $parca) {
            $this->table(['stok_id', 'firma_id', 'kod', 'mevcut', 'beklenen', 'durum'], $parca);
        }

        $ozet = [
            'toplam' => $toplam,
            'farkli' => $farkli,
            'guncellenen' => $guncellenen,
            'incelenmeli' => $incelenmeli,
            'atlanan' => $atlanan,
            'dry_run' => $dryRun,
            'firma_id' => $firmaId,
            'stok_id' => $stokId,
            'riskli_duzelt' => $riskliDuzelt,
        ];

        if ($incelenmeli > 0) {
            $this->logWarning('stok.yeniden_hesapla.incelenmeli', $ozet);
            $this->warn('İncelenmeli kayıtlar bulundu. Önce dry-run çıktısını doğrulayın; gerekirse --riskli-duzelt kullanın.');
        }

        $this->logInfo('stok.yeniden_hesapla', $ozet);
        $this->info(sprintf(
            'Tamamlandı. Toplam: %d | Farklı: %d | Güncellenen: %d | İncelenmeli: %d | Atlanan: %d',
            $toplam,
            $farkli,
            $guncellenen,
            $incelenmeli,
            $atlanan
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{net:string,aktif_sayi:int,toplam_sayi:int}>
     */
    private function hareketOzetiniGetir(?int $firmaId, ?int $stokId): array
    {
        return DB::table('stok_hareketleri')
            ->selectRaw("
                stok_id,
                SUM(
                    CASE
                        WHEN durum = 'aktif' AND islem_turu IN ('acilis', 'alis', 'iade', 'satis_iadesi', 'transfer_giris') THEN miktar
                        WHEN durum = 'aktif' AND islem_turu IN ('acilis_iptali', 'satis', 'alis_iadesi', 'transfer_cikis') THEN -miktar
                        ELSE 0
                    END
                ) AS net_miktar
                ,SUM(CASE WHEN durum = 'aktif' THEN 1 ELSE 0 END) AS aktif_sayi
                ,COUNT(*) AS toplam_sayi
            ")
            ->when($firmaId, fn ($q) => $q->where('firma_id', $firmaId))
            ->when($stokId, fn ($q) => $q->where('stok_id', $stokId))
            ->groupBy('stok_id')
            ->get()
            ->mapWithKeys(fn ($row) => [
                (int) $row->stok_id => [
                    'net' => (string) $row->net_miktar,
                    'aktif_sayi' => (int) $row->aktif_sayi,
                    'toplam_sayi' => (int) $row->toplam_sayi,
                ],
            ])
            ->all();
    }

    private function riskDurumuBelirle(string $mevcut, string $beklenen, int $aktifSayi, int $toplamSayi): ?string
    {
        if ($aktifSayi === 0 && bccomp($mevcut, '0', 4) !== 0) {
            return 'LEGACY_ACILIS_STOK';
        }

        if ($aktifSayi === 0 && $toplamSayi > 0 && bccomp($mevcut, $beklenen, 4) !== 0) {
            return 'SADECE_IPTAL_HAREKET';
        }

        if (! (bool) config('muhasebe.stok.negatif_stok_izinli', false) && bccomp($beklenen, '0', 4) < 0) {
            return 'NEGATIF_BEKLENEN';
        }

        return null;
    }

    private function logInfo(string $mesaj, array $baglam): void
    {
        Log::channel((string) config('muhasebe.stok.log_channel', 'muhasebe'))->info($mesaj, $baglam);
    }

    private function logWarning(string $mesaj, array $baglam): void
    {
        Log::channel((string) config('muhasebe.stok.log_channel', 'muhasebe'))->warning($mesaj, $baglam);
    }
}
