<?php

namespace App\Services;

use App\Models\Muhasebe\BarkodluSatis;
use App\Models\Muhasebe\BarkodluSatisIade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BarkodluSatisTelegramBildirimServisi
{
    public const OLAY_SATIS_TAMAMLANDI = 'satis_tamamlandi';

    public const OLAY_SATIS_IPTAL_EDILDI = 'satis_iptal_edildi';

    public const OLAY_IADE_OLUSTURULDU = 'iade_olusturuldu';

    public function __construct(
        private readonly FirmaAyarDeposu $depo,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function ayarlariGetir(int $firmaId): array
    {
        return [
            'barkodlu_satis_telegram_aktif_mi' => (bool) $this->depo->oku($firmaId, 'barkodlu_satis_telegram_aktif_mi', false),
            'barkodlu_satis_telegram_satis_aktif_mi' => (bool) $this->depo->oku($firmaId, 'barkodlu_satis_telegram_satis_aktif_mi', true),
            'barkodlu_satis_telegram_iptal_aktif_mi' => (bool) $this->depo->oku($firmaId, 'barkodlu_satis_telegram_iptal_aktif_mi', true),
            'barkodlu_satis_telegram_iade_aktif_mi' => (bool) $this->depo->oku($firmaId, 'barkodlu_satis_telegram_iade_aktif_mi', true),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function kaydetAyarlar(int $firmaId, array $data): void
    {
        if ($firmaId <= 0) {
            return;
        }

        $this->depo->yaz($firmaId, 'barkodlu_satis_telegram_aktif_mi', (bool) ($data['barkodlu_satis_telegram_aktif_mi'] ?? false));
        $this->depo->yaz($firmaId, 'barkodlu_satis_telegram_satis_aktif_mi', (bool) ($data['barkodlu_satis_telegram_satis_aktif_mi'] ?? true));
        $this->depo->yaz($firmaId, 'barkodlu_satis_telegram_iptal_aktif_mi', (bool) ($data['barkodlu_satis_telegram_iptal_aktif_mi'] ?? true));
        $this->depo->yaz($firmaId, 'barkodlu_satis_telegram_iade_aktif_mi', (bool) ($data['barkodlu_satis_telegram_iade_aktif_mi'] ?? true));
    }

    public function testMesajiGonder(int $firmaId): bool
    {
        $mesaj = "Barkodlu satış Telegram entegrasyon testi\n\nBildirim ayarları başarıyla okundu.\nTarih: ".now()->format('d.m.Y H:i');

        return $this->telegramaGonder($firmaId, 'telegram_test', $mesaj);
    }

    public function satisTamamlandi(BarkodluSatis $satis): bool
    {
        return $this->gonder($satis, self::OLAY_SATIS_TAMAMLANDI);
    }

    public function satisIptalEdildi(BarkodluSatis $satis): bool
    {
        return $this->gonder($satis, self::OLAY_SATIS_IPTAL_EDILDI);
    }

    public function iadeOlusturuldu(BarkodluSatisIade $iade): bool
    {
        $iade->loadMissing(['satis.cari', 'kalemler.satisKalemi.stok', 'olusturan']);
        $satis = $iade->satis;

        if (! $satis || ! $this->olayAktifMi((int) $iade->firma_id, self::OLAY_IADE_OLUSTURULDU)) {
            return false;
        }

        $satirlar = [
            'Barkodlu Satış İade Bildirimi',
            '',
            'İade No: '.($iade->iade_no ?: '-'),
            'Satış No: '.($satis->satis_no ?: '-'),
            'Müşteri: '.$this->musteriAdi($satis),
            'Toplam İade: '.$this->para((float) $iade->toplam_iade_tutari, (string) $satis->para_birimi),
            'Neden: '.($iade->neden ?: '-'),
            'Tarih: '.($iade->iade_tarihi?->format('d.m.Y H:i') ?: now()->format('d.m.Y H:i')),
            'Kasiyer: '.($iade->olusturan?->name ?: '-'),
            'Kalemler: '.$this->iadeKalemleriMetni($iade),
        ];

        return $this->telegramaGonder((int) $iade->firma_id, self::OLAY_IADE_OLUSTURULDU, implode("\n", $satirlar));
    }

    private function gonder(BarkodluSatis $satis, string $olay): bool
    {
        $firmaId = (int) $satis->firma_id;
        if ($firmaId <= 0 || ! $this->olayAktifMi($firmaId, $olay)) {
            return false;
        }

        $satis->loadMissing(['cari', 'kalemler', 'olusturan', 'iptalEden']);

        $baslik = $olay === self::OLAY_SATIS_IPTAL_EDILDI
            ? 'Barkodlu Satış İptal Bildirimi'
            : 'Barkodlu Satış Bildirimi';

        $satirlar = [
            $baslik,
            '',
            'Satış No: '.($satis->satis_no ?: '-'),
            'Müşteri: '.$this->musteriAdi($satis),
            'Ödeme Tipi: '.$this->odemeTipi((string) $satis->odeme_tipi),
            'Toplam: '.$this->para((float) $satis->genel_toplam, (string) $satis->para_birimi),
            'Kalem Sayısı: '.$satis->kalemler->count(),
            'Tarih: '.($satis->satis_tarihi?->format('d.m.Y H:i') ?: now()->format('d.m.Y H:i')),
            'Kasiyer: '.($satis->olusturan?->name ?: '-'),
        ];

        if ($olay === self::OLAY_SATIS_IPTAL_EDILDI) {
            $satirlar[] = 'İptal Nedeni: '.($satis->iptal_nedeni ?: '-');
            $satirlar[] = 'İptal Eden: '.($satis->iptalEden?->name ?: '-');
            $satirlar[] = 'İptal Tarihi: '.($satis->iptal_tarihi?->format('d.m.Y H:i') ?: now()->format('d.m.Y H:i'));
        }

        return $this->telegramaGonder($firmaId, $olay, implode("\n", $satirlar));
    }

    private function olayAktifMi(int $firmaId, string $olay): bool
    {
        if (! (bool) $this->depo->oku($firmaId, 'barkodlu_satis_telegram_aktif_mi', false)) {
            return false;
        }

        $anahtar = match ($olay) {
            self::OLAY_SATIS_IPTAL_EDILDI => 'barkodlu_satis_telegram_iptal_aktif_mi',
            self::OLAY_IADE_OLUSTURULDU => 'barkodlu_satis_telegram_iade_aktif_mi',
            default => 'barkodlu_satis_telegram_satis_aktif_mi',
        };

        return (bool) $this->depo->oku($firmaId, $anahtar, true);
    }

    private function telegramaGonder(int $firmaId, string $olay, string $mesaj): bool
    {
        $token = trim((string) $this->depo->oku($firmaId, 'telegram_bot_token', ''));
        $chatId = trim((string) $this->depo->oku($firmaId, 'telegram_chat_id', ''));

        if ($token === '' || $chatId === '') {
            return false;
        }

        try {
            $yanit = Http::timeout(10)
                ->acceptJson()
                ->post('https://api.telegram.org/bot'.$token.'/sendMessage', [
                    'chat_id' => $chatId,
                    'text' => $mesaj,
                    'disable_web_page_preview' => true,
                ]);

            return $yanit->successful() && (bool) data_get($yanit->json(), 'ok', false);
        } catch (\Throwable $e) {
            Log::warning('Barkodlu satis Telegram bildirimi gonderilemedi.', [
                'firma_id' => $firmaId,
                'olay' => $olay,
                'hata' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function musteriAdi(BarkodluSatis $satis): string
    {
        return trim((string) ($satis->cari?->ad ?? '')) ?: 'Perakende Müşteri';
    }

    private function odemeTipi(string $tip): string
    {
        return [
            'nakit' => 'Nakit',
            'kart' => 'Kart',
            'havale' => 'Havale/EFT',
            'veresiye' => 'Veresiye',
            'taksitli' => 'Taksitli',
            'diger' => 'Diğer',
        ][$tip] ?? ucfirst($tip);
    }

    private function para(float $tutar, string $paraBirimi): string
    {
        return number_format($tutar, 2, ',', '.').' '.($paraBirimi ?: 'TRY');
    }

    private function iadeKalemleriMetni(BarkodluSatisIade $iade): string
    {
        $kalemler = $iade->kalemler
            ->map(function ($kalem): string {
                $ad = trim((string) ($kalem->satisKalemi?->stok_adi ?? $kalem->satisKalemi?->stok?->ad ?? ''));
                if ($ad === '') {
                    $ad = 'Ürün';
                }

                $miktar = rtrim(rtrim(number_format((float) $kalem->miktar, 4, '.', ''), '0'), '.');

                return $ad.' x'.$miktar;
            })
            ->filter()
            ->values()
            ->all();

        return $kalemler !== [] ? implode(', ', $kalemler) : '-';
    }
}
