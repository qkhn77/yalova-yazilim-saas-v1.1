<?php

namespace App\Console\Commands;

use App\Models\Firma;
use App\Muhasebe\Servisler\AlacakHatirlatmaGonderimServisi;
use App\Muhasebe\Servisler\AlacakHatirlatmaServisi;
use App\Services\SistemOlayServisi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class MuhasebeVadeHatirlatmaCommand extends Command
{
    protected $signature = 'muhasebe:vade-hatirlatma
        {--firma_id= : Sadece belirtilen firma}
        {--days=7 : Yaklasan vade gun sayisi}
        {--limit=10 : Firma basina listelenecek cari sayisi}
        {--cache : Sonucu panel icin onbellege yaz}
        {--messages : Hatirlatma mesaj loglarini olustur}
        {--channel=whatsapp : Mesaj kanali: whatsapp, sms, email}
        {--send : Mesaj loglarini olusturduktan sonra gondermeyi dene}
        {--template= : Varsayilan yerine kullanilacak mesaj sablonu}
        {--allow-duplicate : Ayni cari/hedef icin gunluk tekrar kaydina izin ver}
        {--dry-run : Sistem olay kaydi ve cache yazmadan sadece raporla}
        {--json : Sonucu JSON olarak yazdir}';

    protected $description = 'Vadesi gelen, geciken ve yaklasan alacaklar icin gunluk hatirlatma ozeti.';

    public function handle(
        AlacakHatirlatmaServisi $servis,
        AlacakHatirlatmaGonderimServisi $gonderimServisi,
        SistemOlayServisi $sistemOlayServisi
    ): int
    {
        $firmaId = $this->option('firma_id') !== null && $this->option('firma_id') !== ''
            ? (int) $this->option('firma_id')
            : null;
        $days = max(1, (int) $this->option('days'));
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $mesajLoguOlustur = (bool) $this->option('messages');
        $gonder = (bool) $this->option('send') && ! $dryRun;
        $kanal = (string) $this->option('channel');
        $sablon = trim((string) ($this->option('template') ?? ''));

        $firmaIdleri = $firmaId
            ? [$firmaId]
            : Firma::query()
                ->where('durum', Firma::DURUM_AKTIF)
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

        $sonuclar = [];
        foreach ($firmaIdleri as $fid) {
            $ozet = $servis->ozet((int) $fid, $days, $limit);
            if ($mesajLoguOlustur && ! $dryRun) {
                $ozet['gonderim'] = $gonderimServisi->gonderimleriOlustur(
                    (int) $fid,
                    $kanal,
                    $days,
                    $limit,
                    $sablon !== '' ? $sablon : null,
                    $gonder,
                    (bool) $this->option('allow-duplicate'),
                );
            } elseif ($mesajLoguOlustur) {
                $ozet['gonderim'] = [
                    'dry_run' => true,
                    'kanal' => $kanal,
                    'gonderim_denendi' => false,
                ];
            }
            $sonuclar[] = $ozet;

            if (! $dryRun && (bool) $this->option('cache')) {
                Cache::put($this->cacheAnahtari((int) $fid), $ozet, now()->addDays(2));
            }

            if (! $dryRun) {
                $kritikAdet = (int) ($ozet['geciken']['adet'] ?? 0) + (int) ($ozet['bugun']['adet'] ?? 0);
                $sistemOlayServisi->olayKaydet(
                    tip: 'muhasebe.vade_hatirlatma',
                    seviye: $kritikAdet > 0 ? 'warning' : 'info',
                    mesaj: 'Vade hatirlatma ozeti olusturuldu.',
                    context: [
                        'firma_id' => (int) $fid,
                        'yaklasan_gun' => $days,
                        'geciken_adet' => (int) ($ozet['geciken']['adet'] ?? 0),
                        'bugun_adet' => (int) ($ozet['bugun']['adet'] ?? 0),
                        'yaklasan_adet' => (int) ($ozet['yaklasan']['adet'] ?? 0),
                        'cache_yazildi' => (bool) $this->option('cache'),
                        'mesaj_logu_olusturuldu' => $mesajLoguOlustur,
                        'mesaj_gonderim_denendi' => $gonder,
                    ]
                );
            }
        }

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($sonuclar, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        foreach ($sonuclar as $ozet) {
            $this->info(sprintf(
                'Firma #%d | Geciken: %d | Bugun: %d | Yaklasan %d gun: %d',
                (int) $ozet['firma_id'],
                (int) ($ozet['geciken']['adet'] ?? 0),
                (int) ($ozet['bugun']['adet'] ?? 0),
                (int) $ozet['yaklasan_gun'],
                (int) ($ozet['yaklasan']['adet'] ?? 0),
            ));

            if (! empty($ozet['satirlar'])) {
                $this->table(
                    ['Cari', 'Para', 'Vade', 'Kalan', 'Geciken', 'Bugun', 'Ilk Vade'],
                    array_map(static fn (array $satir): array => [
                        trim(($satir['cari_kod'] ? $satir['cari_kod'].' - ' : '').$satir['cari_ad']),
                        (string) $satir['para_birimi'],
                        (string) $satir['vade_adedi'],
                        (string) $satir['kalan_toplam'],
                        (string) $satir['geciken_toplam'],
                        (string) $satir['bugun_toplam'],
                        (string) $satir['ilk_vade_tarihi'],
                    ], $ozet['satirlar'])
                );
            }

            if (! empty($ozet['gonderim'])) {
                $gonderim = (array) $ozet['gonderim'];
                $this->line(sprintf(
                    'Gonderim | Kanal: %s | Olusturulan: %d | Gonderilen: %d | Atlanan: %d | Basarisiz: %d',
                    (string) ($gonderim['kanal'] ?? $kanal),
                    (int) ($gonderim['olusturulan'] ?? 0),
                    (int) ($gonderim['gonderilen'] ?? 0),
                    (int) ($gonderim['atlanan'] ?? 0),
                    (int) ($gonderim['basarisiz'] ?? 0),
                ));
            }
        }

        return self::SUCCESS;
    }

    private function cacheAnahtari(int $firmaId): string
    {
        return 'muhasebe:vade_hatirlatma:firma:'.$firmaId;
    }
}
