<?php

namespace App\Console\Commands;

use App\Services\MuhasebeDisaAktarimServisi;
use App\Support\DenetimYardimcisi;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MuhasebeMinimumExportCommand extends Command
{
    protected $signature = 'muhasebe:export-minimum
        {--firma_id= : Firma ID}
        {--from= : Baslangic (Y-m-d)}
        {--to= : Bitis (Y-m-d)}
        {--para_birimi=TRY : Cari ekstre para birimi}
        {--cari_id= : Tek cari icin ekstre}';

    protected $description = 'Canli minimum muhasebe disa aktarim seti (CSV): fatura/acik/cari/kdv/gelir-gider.';

    public function handle(MuhasebeDisaAktarimServisi $servis): int
    {
        $firmaId = (int) ($this->option('firma_id') ?? 0);
        if ($firmaId <= 0) {
            $this->error('--firma_id zorunlu.');

            return self::INVALID;
        }

        $from = (string) ($this->option('from') ?? now()->startOfMonth()->format('Y-m-d'));
        $to = (string) ($this->option('to') ?? now()->format('Y-m-d'));
        $para = strtoupper((string) ($this->option('para_birimi') ?? 'TRY'));
        $bas = Carbon::parse($from)->startOfDay();
        $bit = Carbon::parse($to)->endOfDay();
        if ($bas->gt($bit)) {
            $this->error('from > to olamaz.');

            return self::INVALID;
        }

        $ts = now()->format('Ymd_His');
        $root = "exports/muhasebe/firma_{$firmaId}/{$ts}";
        Storage::makeDirectory($root);

        $this->yazCsv("{$root}/fatura_listesi.csv", $servis->faturaListesi($firmaId, $bas, $bit));
        $this->exportAuditKaydi($firmaId, 'muhasebe_minimum_fatura_listesi', $root, $from, $to, $para, true);
        $this->yazCsv("{$root}/acik_faturalar.csv", $servis->acikFaturalar($firmaId));
        $this->exportAuditKaydi($firmaId, 'muhasebe_acik_fatura_export', $root, $from, $to, $para, true);
        $this->yazCsv("{$root}/gelir_gider_ozeti.csv", $servis->gelirGiderOzeti($firmaId, $bas, $bit));
        $this->exportAuditKaydi($firmaId, 'muhasebe_gelir_gider_export', $root, $from, $to, $para, true);
        $this->yazCsv("{$root}/kdv_ozeti.csv", $servis->kdvOzeti($firmaId, $bas, $bit));
        $this->exportAuditKaydi($firmaId, 'muhasebe_kdv_ozeti_export', $root, $from, $to, $para, true);

        $tekCariId = (int) ($this->option('cari_id') ?? 0);
        $cariIdler = $tekCariId > 0 ? [$tekCariId] : $servis->firmaCariIdleri($firmaId);
        foreach ($cariIdler as $cariId) {
            $rows = $servis->cariEkstre($firmaId, $cariId, $para, $bas, $bit);
            $this->yazCsv("{$root}/cari_ekstre_{$cariId}_{$para}.csv", $rows);
            $this->exportAuditKaydi($firmaId, 'muhasebe_cari_ekstre_export', $root, $from, $to, $para, true, $cariId);
        }

        $this->info("Export tamamlandi: storage/app/{$root}");

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, scalar|null>>  $rows
     */
    private function yazCsv(string $path, array $rows): void
    {
        if ($rows === []) {
            Storage::put($path, "kayit_bulunamadi\n");

            return;
        }

        $headers = array_keys($rows[0]);
        $lines = [];
        $lines[] = implode(';', $headers);

        foreach ($rows as $row) {
            $cells = [];
            foreach ($headers as $h) {
                $value = (string) ($row[$h] ?? '');
                $value = str_replace(["\r", "\n", ';'], [' ', ' ', ','], $value);
                $cells[] = $value;
            }
            $lines[] = implode(';', $cells);
        }

        Storage::put($path, implode("\n", $lines)."\n");
    }

    private function exportAuditKaydi(
        int $firmaId,
        string $tip,
        string $root,
        string $from,
        string $to,
        string $paraBirimi,
        bool $dosyaUretildi,
        ?int $cariId = null
    ): void {
        DenetimYardimcisi::kaydet(
            olay: 'export.olusturuldu',
            konuTipi: self::class,
            konuId: null,
            firmaId: $firmaId,
            eskiVeri: null,
            yeniVeri: [
                'export_tipi' => $tip,
                'filtreler' => [
                    'from' => $from,
                    'to' => $to,
                    'para_birimi' => $paraBirimi,
                    'cari_id' => $cariId,
                ],
                'dosya_uretildi' => $dosyaUretildi,
                'dizin' => $root,
                'zaman' => now()->toIso8601String(),
            ]
        );
    }
}
