<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\AlacakHatirlatmaLogu;
use App\Services\SistemOlayServisi;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AlacakHatirlatmaGonderimServisi
{
    public function __construct(
        private readonly AlacakHatirlatmaMesajServisi $mesajServisi,
        private readonly SistemOlayServisi $sistemOlayServisi,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function gonderimleriOlustur(
        int $firmaId,
        string $kanal = 'whatsapp',
        int $yaklasanGun = 7,
        int $limit = 50,
        ?string $sablon = null,
        bool $gonder = false,
        bool $tekrarIzinli = false,
    ): array {
        $kanal = $this->kanal($kanal);
        $mesajlar = $this->mesajServisi->mesajlar($firmaId, $kanal, $yaklasanGun, $limit, $sablon);
        $olusturulan = 0;
        $atlanan = 0;
        $gonderilen = 0;
        $basarisiz = 0;
        $hedefYok = 0;
        $logIdleri = [];

        foreach ($mesajlar as $mesaj) {
            $hedef = trim((string) ($mesaj['hedef'] ?? ''));
            $durum = $hedef === '' ? AlacakHatirlatmaLogu::DURUM_HEDEF_YOK : AlacakHatirlatmaLogu::DURUM_KUYRUKTA;
            if ($hedef === '') {
                $hedefYok++;
            }

            if (! $tekrarIzinli && $hedef !== '' && $this->gunlukTekrarVarMi($firmaId, (int) ($mesaj['cari_id'] ?? 0), $kanal, $hedef)) {
                $atlanan++;
                continue;
            }

            $log = AlacakHatirlatmaLogu::query()->create([
                'firma_id' => $firmaId,
                'cari_id' => (int) ($mesaj['cari_id'] ?? 0) ?: null,
                'kanal' => $kanal,
                'saglayici' => $this->saglayiciAdi($kanal),
                'hedef' => $hedef !== '' ? $hedef : null,
                'baslik' => (string) ($mesaj['baslik'] ?? 'Vade hatirlatmasi'),
                'mesaj' => (string) ($mesaj['mesaj'] ?? ''),
                'durum' => $durum,
                'deneme_sayisi' => 0,
                'payload' => $mesaj,
                'metadata' => [
                    'yaklasan_gun' => $yaklasanGun,
                    'vade_adedi' => (int) ($mesaj['vade_adedi'] ?? 0),
                    'kalan_toplam' => (string) ($mesaj['kalan_toplam'] ?? '0'),
                    'geciken_toplam' => (string) ($mesaj['geciken_toplam'] ?? '0'),
                    'bugun_toplam' => (string) ($mesaj['bugun_toplam'] ?? '0'),
                    'whatsapp_url' => $mesaj['whatsapp_url'] ?? null,
                ],
            ]);

            $olusturulan++;
            $logIdleri[] = (int) $log->getKey();

            if ($gonder && $durum === AlacakHatirlatmaLogu::DURUM_KUYRUKTA) {
                $log = $this->logKaydiGonder($log);
                if ((string) $log->durum === AlacakHatirlatmaLogu::DURUM_GONDERILDI) {
                    $gonderilen++;
                } elseif ((string) $log->durum === AlacakHatirlatmaLogu::DURUM_BASARISIZ) {
                    $basarisiz++;
                }
            }
        }

        $this->sistemOlayServisi->olayKaydet(
            'muhasebe.alacak_hatirlatma_gonderim',
            $basarisiz > 0 ? 'warning' : 'info',
            'Alacak hatirlatma gonderim loglari olusturuldu.',
            [
                'firma_id' => $firmaId,
                'kanal' => $kanal,
                'olusturulan' => $olusturulan,
                'atlanan' => $atlanan,
                'hedef_yok' => $hedefYok,
                'gonderilen' => $gonderilen,
                'basarisiz' => $basarisiz,
                'gonderim_denendi' => $gonder,
            ]
        );

        return [
            'firma_id' => $firmaId,
            'kanal' => $kanal,
            'olusturulan' => $olusturulan,
            'atlanan' => $atlanan,
            'hedef_yok' => $hedefYok,
            'gonderilen' => $gonderilen,
            'basarisiz' => $basarisiz,
            'log_idleri' => $logIdleri,
        ];
    }

    public function logKaydiGonder(AlacakHatirlatmaLogu $log): AlacakHatirlatmaLogu
    {
        $log->increment('deneme_sayisi');
        $log->update([
            'son_deneme_at' => now(),
            'hata' => null,
        ]);

        if (trim((string) ($log->hedef ?? '')) === '') {
            $log->update([
                'durum' => AlacakHatirlatmaLogu::DURUM_HEDEF_YOK,
                'hata' => 'hedef_yok',
            ]);

            return $log->fresh() ?? $log;
        }

        return match ((string) $log->kanal) {
            'email' => $this->emailGonder($log),
            'sms', 'whatsapp' => $this->webhookGonder($log),
            default => $this->basarisizYap($log, 'gecersiz_kanal'),
        };
    }

    private function emailGonder(AlacakHatirlatmaLogu $log): AlacakHatirlatmaLogu
    {
        $hedef = trim((string) $log->hedef);
        if (! filter_var($hedef, FILTER_VALIDATE_EMAIL)) {
            return $this->basarisizYap($log, 'gecersiz_hedef_eposta');
        }

        try {
            Mail::raw((string) ($log->mesaj ?? ''), function ($message) use ($hedef, $log): void {
                $message->to($hedef)->subject((string) ($log->baslik ?? 'Vade hatirlatmasi'));
            });

            return $this->gonderildiYap($log);
        } catch (Throwable $e) {
            return $this->basarisizYap($log, $e->getMessage());
        }
    }

    private function webhookGonder(AlacakHatirlatmaLogu $log): AlacakHatirlatmaLogu
    {
        $kanal = (string) $log->kanal;
        $url = trim((string) config('services.muhasebe_hatirlatma.'.$kanal.'.webhook_url', ''));
        if ($url === '') {
            $log->update([
                'durum' => AlacakHatirlatmaLogu::DURUM_KUYRUKTA,
                'hata' => 'kanal_entegrasyonu_yok',
            ]);

            return $log->fresh() ?? $log;
        }

        $token = trim((string) config('services.muhasebe_hatirlatma.'.$kanal.'.token', ''));
        $payload = [
            'log_id' => (int) $log->getKey(),
            'firma_id' => (int) $log->firma_id,
            'cari_id' => $log->cari_id ? (int) $log->cari_id : null,
            'kanal' => $kanal,
            'hedef' => (string) $log->hedef,
            'baslik' => (string) ($log->baslik ?? ''),
            'mesaj' => (string) ($log->mesaj ?? ''),
            'metadata' => $log->metadata ?? [],
        ];

        try {
            $request = Http::timeout(10)->acceptJson();
            if ($token !== '') {
                $request = $request->withToken($token);
            }

            $response = $request->post($url, $payload);
            if ($response->successful()) {
                $log->update([
                    'payload' => array_merge((array) ($log->payload ?? []), [
                        'provider_response' => $response->json() ?? $response->body(),
                    ]),
                ]);

                return $this->gonderildiYap($log);
            }

            return $this->basarisizYap($log, 'webhook_http_'.$response->status().': '.$response->body());
        } catch (Throwable $e) {
            return $this->basarisizYap($log, $e->getMessage());
        }
    }

    private function gonderildiYap(AlacakHatirlatmaLogu $log): AlacakHatirlatmaLogu
    {
        $log->update([
            'durum' => AlacakHatirlatmaLogu::DURUM_GONDERILDI,
            'gonderildi_at' => now(),
            'hata' => null,
        ]);

        return $log->fresh() ?? $log;
    }

    private function basarisizYap(AlacakHatirlatmaLogu $log, string $hata): AlacakHatirlatmaLogu
    {
        $log->update([
            'durum' => AlacakHatirlatmaLogu::DURUM_BASARISIZ,
            'hata' => mb_substr($hata, 0, 1000),
        ]);

        return $log->fresh() ?? $log;
    }

    private function gunlukTekrarVarMi(int $firmaId, int $cariId, string $kanal, string $hedef): bool
    {
        if ($cariId < 1) {
            return false;
        }

        return AlacakHatirlatmaLogu::query()
            ->where('firma_id', $firmaId)
            ->where('cari_id', $cariId)
            ->where('kanal', $kanal)
            ->where('hedef', $hedef)
            ->whereIn('durum', [AlacakHatirlatmaLogu::DURUM_KUYRUKTA, AlacakHatirlatmaLogu::DURUM_GONDERILDI])
            ->where('created_at', '>=', now()->startOfDay())
            ->exists();
    }

    private function saglayiciAdi(string $kanal): ?string
    {
        return match ($kanal) {
            'whatsapp', 'sms' => (string) config('services.muhasebe_hatirlatma.'.$kanal.'.provider', 'webhook'),
            'email' => 'mail',
            default => null,
        };
    }

    private function kanal(string $kanal): string
    {
        return in_array($kanal, ['whatsapp', 'sms', 'email'], true) ? $kanal : 'whatsapp';
    }
}
