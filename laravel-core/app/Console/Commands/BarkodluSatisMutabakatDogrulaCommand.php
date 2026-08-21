<?php

namespace App\Console\Commands;

use App\BarkodluSatis\Mutabakat\BarkodluSatisMuhasebeMutabakatServisi;
use App\Services\SistemOlayServisi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class BarkodluSatisMutabakatDogrulaCommand extends Command
{
    protected $signature = 'barkodlu-satis:mutabakat-dogrula
        {--firma_id= : Sadece belirtilen firma}
        {--days=30 : Geriye donuk kac gun kontrol edilsin}
        {--limit=1000 : En fazla kac satis kaydi kontrol edilsin}
        {--critical-only : Sadece kritik sorunlari listele}
        {--json : Sonucu JSON olarak yazdir}';

    protected $description = 'Barkodlu satis-finans mutabakat kontrolu (salt okunur).';

    public function handle(
        BarkodluSatisMuhasebeMutabakatServisi $mutabakatServisi,
        SistemOlayServisi $sistemOlayServisi
    ): int {
        $days = max(1, (int) $this->option('days'));
        $limit = max(1, (int) $this->option('limit'));
        $criticalOnly = (bool) $this->option('critical-only');
        $firmaId = $this->option('firma_id') !== null ? (int) $this->option('firma_id') : null;

        $bitis = now()->endOfDay();
        $baslangic = now()->subDays($days)->startOfDay();

        $sonuc = $mutabakatServisi->raporla(
            firmaId: $firmaId,
            baslangic: $baslangic,
            bitis: $bitis,
            limit: $limit,
            sadeceKritik: $criticalOnly
        );

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($sonuc, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $this->info(sprintf(
                'Kontrol: %d | Sorunlu Kayit: %d | Toplam Sorun: %d | Aralik: %s - %s',
                (int) $sonuc['kontrol_edilen'],
                (int) $sonuc['sorunlu_kayit'],
                (int) $sonuc['toplam_sorun'],
                $baslangic->format('Y-m-d'),
                $bitis->format('Y-m-d')
            ));

            if (! empty($sonuc['sorunlar'])) {
                $this->table(
                    ['Kod', 'Seviye', 'Referans', 'Iade No', 'Firma', 'Satis No', 'Tarih', 'Cari', 'Durum', 'Beklenen', 'Aktif Finans', 'Adet'],
                    array_map(static fn (array $sorun): array => [
                        (string) ($sorun['kod'] ?? ''),
                        (string) ($sorun['seviye'] ?? ''),
                        (string) ($sorun['referans_turu'] ?? 'barkodlu_satis'),
                        (string) ($sorun['iade_no'] ?? ''),
                        (string) ($sorun['firma_id'] ?? ''),
                        (string) ($sorun['satis_no'] ?? ''),
                        (string) ($sorun['satis_tarihi'] ?? ''),
                        (string) ($sorun['cari'] ?? ''),
                        (string) ($sorun['durum'] ?? ''),
                        (string) ($sorun['beklenen_tutar'] ?? ''),
                        (string) ($sorun['aktif_finans_toplami'] ?? ''),
                        (string) ($sorun['aktif_finans_adedi'] ?? ''),
                    ], $sonuc['sorunlar'])
                );
            }
        }

        $seviye = (int) $sonuc['toplam_sorun'] > 0 ? 'warning' : 'info';
        $sistemOlayServisi->olayKaydet(
            tip: 'barkodlu_satis.mutabakat_kontrolu',
            seviye: $seviye,
            mesaj: 'Barkodlu satis mutabakat kontrolu tamamlandi.',
            context: [
                'firma_id' => $firmaId,
                'days' => $days,
                'limit' => $limit,
                'critical_only' => $criticalOnly,
                'kontrol_edilen' => (int) $sonuc['kontrol_edilen'],
                'sorunlu_kayit' => (int) $sonuc['sorunlu_kayit'],
                'toplam_sorun' => (int) $sonuc['toplam_sorun'],
                'kod_dagilimi' => $sonuc['kod_dagilimi'] ?? [],
            ]
        );
        $this->mutabakatOzetiniOnbellegeYaz(
            firmaId: $firmaId,
            days: $days,
            limit: $limit,
            criticalOnly: $criticalOnly,
            sonuc: $sonuc
        );

        return (int) $sonuc['toplam_sorun'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param array<string,mixed> $sonuc
     */
    private function mutabakatOzetiniOnbellegeYaz(
        ?int $firmaId,
        int $days,
        int $limit,
        bool $criticalOnly,
        array $sonuc
    ): void {
        $payload = [
            'firma_id' => $firmaId,
            'days' => $days,
            'limit' => $limit,
            'critical_only' => $criticalOnly,
            'kontrol_edilen' => (int) ($sonuc['kontrol_edilen'] ?? 0),
            'sorunlu_kayit' => (int) ($sonuc['sorunlu_kayit'] ?? 0),
            'toplam_sorun' => (int) ($sonuc['toplam_sorun'] ?? 0),
            'kritik_sorun' => (int) collect((array) ($sonuc['sorunlar'] ?? []))
                ->filter(fn (array $s): bool => (string) ($s['seviye'] ?? '') === 'critical')
                ->count(),
            'kod_dagilimi' => (array) ($sonuc['kod_dagilimi'] ?? []),
            'updated_at' => now()->toDateTimeString(),
        ];

        if ($firmaId !== null) {
            Cache::put($this->firmaOzetAnahtari($firmaId), $payload, now()->addDays(3));
            Cache::put('barkodlu_satis:mutabakat:sonuc:son', $payload, now()->addDays(3));

            return;
        }

        Cache::put('barkodlu_satis:mutabakat:sonuc:global', $payload, now()->addDays(3));
        Cache::put('barkodlu_satis:mutabakat:sonuc:son', $payload, now()->addDays(3));

        $firmaSorunSayilari = [];
        foreach ((array) ($sonuc['sorunlar'] ?? []) as $sorun) {
            $sid = (int) ($sorun['firma_id'] ?? 0);
            if ($sid < 1) {
                continue;
            }
            $firmaSorunSayilari[$sid] = (int) ($firmaSorunSayilari[$sid] ?? 0) + 1;
        }

        $kontrolEdilenFirmaIdleri = (array) ($sonuc['kontrol_edilen_firma_idleri'] ?? []);
        foreach ($kontrolEdilenFirmaIdleri as $sid) {
            $sid = (int) $sid;
            if ($sid < 1) {
                continue;
            }

            $firmaPayload = $payload;
            $firmaPayload['firma_id'] = $sid;
            $firmaPayload['toplam_sorun'] = (int) ($firmaSorunSayilari[$sid] ?? 0);
            $firmaPayload['kritik_sorun'] = (int) collect((array) ($sonuc['sorunlar'] ?? []))
                ->filter(fn (array $s): bool => (int) ($s['firma_id'] ?? 0) === $sid && (string) ($s['seviye'] ?? '') === 'critical')
                ->count();
            Cache::put($this->firmaOzetAnahtari($sid), $firmaPayload, now()->addDays(3));
        }
    }

    private function firmaOzetAnahtari(int $firmaId): string
    {
        return 'barkodlu_satis:mutabakat:sonuc:firma:'.$firmaId;
    }
}
