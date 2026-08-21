<?php

namespace App\Console\Commands;

use App\Muhasebe\Servisler\AlacakPlanDogrulamaServisi;
use App\Services\SistemOlayServisi;
use Illuminate\Console\Command;

class MuhasebeAlacakPlanDogrulaCommand extends Command
{
    protected $signature = 'muhasebe:alacak-plan-dogrula
        {--firma_id= : Sadece belirtilen firma}
        {--limit=5000 : En fazla kac plan/sorun taransin}
        {--dry-run : Sistem olay kaydi yazmadan sadece raporla}
        {--json : Sonucu JSON olarak yazdir}';

    protected $description = 'Alacak planlari, taksitleri ve tahsilat eslesmeleri icin salt okunur tutarlilik kontrolu.';

    public function handle(AlacakPlanDogrulamaServisi $servis, SistemOlayServisi $sistemOlayServisi): int
    {
        $firmaId = $this->option('firma_id') !== null && $this->option('firma_id') !== ''
            ? (int) $this->option('firma_id')
            : null;
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        $sonuc = $servis->kontrolEt($firmaId, $limit);

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($sonuc, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $this->info(sprintf(
                'Kontrol edilen plan: %d | Toplam sorun: %d',
                (int) ($sonuc['kontrol_edilen_plan'] ?? 0),
                (int) ($sonuc['toplam_sorun'] ?? 0)
            ));

            if (! empty($sonuc['sorunlar'])) {
                $this->table(
                    ['Kod', 'Firma', 'Plan', 'Kaynak', 'Detay'],
                    array_map(static fn (array $sorun): array => [
                        (string) ($sorun['kod'] ?? ''),
                        (string) ($sorun['firma_id'] ?? ''),
                        (string) ($sorun['plan_id'] ?? ''),
                        (string) ($sorun['kaynak_id'] ?? ''),
                        (string) ($sorun['detay'] ?? ''),
                    ], (array) $sonuc['sorunlar'])
                );
            }
        }

        if (! $dryRun) {
            $sistemOlayServisi->olayKaydet(
                tip: 'muhasebe.alacak_plan_dogrulama',
                seviye: (int) ($sonuc['toplam_sorun'] ?? 0) > 0 ? 'warning' : 'info',
                mesaj: 'Alacak plan dogrulama kontrolu tamamlandi.',
                context: [
                    'firma_id' => $firmaId,
                    'limit' => $limit,
                    'kontrol_edilen_plan' => (int) ($sonuc['kontrol_edilen_plan'] ?? 0),
                    'toplam_sorun' => (int) ($sonuc['toplam_sorun'] ?? 0),
                ]
            );
        }

        return (int) ($sonuc['toplam_sorun'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
