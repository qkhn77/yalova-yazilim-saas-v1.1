<?php

namespace App\Console\Commands;

use App\Models\Muhasebe\Fatura;
use App\Muhasebe\Servisler\FaturaKapamaDogrulamaServisi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FaturaKapamaDogrulaCommand extends Command
{
    protected $signature = 'fatura:kapama-dogrula
        {--dry-run : Sadece raporlar, düzeltme yapmaz}
        {--firma_id= : Sadece belirtilen firmayı denetler}
        {--fatura_id= : Sadece belirtilen faturayı denetler}
        {--sadece-hatali : Sadece tutarsızları listeler}';

    protected $description = 'Fatura kapama ve ödeme durumu tutarlılığını doğrular.';

    public function handle(FaturaKapamaDogrulamaServisi $servis): int
    {
        $firmaId = $this->option('firma_id') !== null ? (int) $this->option('firma_id') : null;
        $faturaId = $this->option('fatura_id') !== null ? (int) $this->option('fatura_id') : null;
        $sadeceHatali = (bool) $this->option('sadece-hatali');
        $dryRun = (bool) $this->option('dry-run');

        $faturalar = Fatura::query()
            ->select(['id', 'firma_id'])
            ->when($firmaId, fn ($q) => $q->where('firma_id', $firmaId))
            ->when($faturaId, fn ($q) => $q->whereKey($faturaId))
            ->orderBy('id')
            ->get();

        if ($faturalar->isEmpty()) {
            $this->warn('Doğrulanacak fatura bulunamadı.');

            return self::SUCCESS;
        }

        $hataSayisi = 0;
        $satirlar = [];
        foreach ($faturalar as $fatura) {
            $rapor = $servis->faturaKapamaDurumuRaporla((int) $fatura->id);
            if ($rapor['hata'] !== null) {
                $hataSayisi++;
            }
            if (! $sadeceHatali || $rapor['hata'] !== null) {
                $satirlar[] = [
                    'fatura_id' => $rapor['fatura_id'],
                    'firma_id' => (int) $fatura->firma_id,
                    'odenecek' => $rapor['odenecek_tutar'],
                    'odendi' => $rapor['odendi_tutari'],
                    'beklenen_odendi' => $rapor['beklenen_odendi_tutari'],
                    'acik' => $rapor['acik_tutar'],
                    'beklenen_acik' => $rapor['beklenen_acik_tutar'],
                    'durum' => $rapor['hata'] ?? 'OK',
                ];
            }
        }

        $this->table(['fatura_id', 'firma_id', 'odenecek', 'odendi', 'beklenen_odendi', 'acik', 'beklenen_acik', 'durum'], $satirlar);
        $this->info(sprintf('Toplam: %d | Hatalı: %d | dry-run: %s', $faturalar->count(), $hataSayisi, $dryRun ? 'evet' : 'hayır'));

        if ($hataSayisi > 0) {
            Log::channel((string) config('muhasebe.fatura.log_channel', 'muhasebe'))->warning('fatura.kapama.dogrulama.hatali', [
                'toplam' => $faturalar->count(),
                'hatali' => $hataSayisi,
                'firma_id' => $firmaId,
                'fatura_id' => $faturaId,
            ]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
