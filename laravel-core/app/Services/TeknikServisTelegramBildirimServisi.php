<?php

namespace App\Services;

use App\Models\TeknikServis\TeknikServisKaydi;
use App\Models\TeknikServis\TeknikServisMesajLogu;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TeknikServisTelegramBildirimServisi
{
    public const OLAY_YENI_SERVIS = 'yeni_servis';

    public const OLAY_TESLIM_EDILDI = 'teslim_edildi';

    private ?string $sonHataMesaji = null;

    public function __construct(
        private readonly FirmaAyarDeposu $depo,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function ayarlariGetir(int $firmaId): array
    {
        return [
            'teknik_servis_telegram_aktif_mi' => (bool) $this->depo->oku($firmaId, 'teknik_servis_telegram_aktif_mi', false),
            'telegram_bot_token' => (string) $this->depo->oku($firmaId, 'telegram_bot_token', ''),
            'telegram_chat_id' => (string) $this->depo->oku($firmaId, 'telegram_chat_id', ''),
            'teknik_servis_telegram_yeni_servis_aktif_mi' => (bool) $this->depo->oku($firmaId, 'teknik_servis_telegram_yeni_servis_aktif_mi', true),
            'teknik_servis_telegram_teslim_edildi_aktif_mi' => (bool) $this->depo->oku($firmaId, 'teknik_servis_telegram_teslim_edildi_aktif_mi', true),
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

        $this->depo->yaz($firmaId, 'teknik_servis_telegram_aktif_mi', (bool) ($data['teknik_servis_telegram_aktif_mi'] ?? false));
        $this->depo->yaz($firmaId, 'teknik_servis_telegram_yeni_servis_aktif_mi', (bool) ($data['teknik_servis_telegram_yeni_servis_aktif_mi'] ?? true));
        $this->depo->yaz($firmaId, 'teknik_servis_telegram_teslim_edildi_aktif_mi', (bool) ($data['teknik_servis_telegram_teslim_edildi_aktif_mi'] ?? true));
    }

    public function yeniServisKaydi(TeknikServisKaydi $kayit): bool
    {
        return $this->gonder($kayit, self::OLAY_YENI_SERVIS);
    }

    public function teslimEdildi(TeknikServisKaydi $kayit): bool
    {
        return $this->gonder($kayit, self::OLAY_TESLIM_EDILDI);
    }

    public function testMesajiGonder(int $firmaId): bool
    {
        $mesaj = "Teknik servis Telegram test bildirimi\n\nFirma Ayarları sayfasındaki ortak Telegram bot ayarları ile gönderildi.\nTarih: ".now()->format('d.m.Y H:i');

        $basarili = $this->telegramaGonder($firmaId, null, 'telegram_test', 'Telegram test bildirimi', $mesaj);
        $this->sonTestBilgisiniKaydet($firmaId, $basarili, $this->sonHataMesaji);

        return $basarili;
    }

    /**
     * @return array{durum: string, mesaj: string, tarih: string}
     */
    public function sonTestBilgisiGetir(int $firmaId): array
    {
        return [
            'durum' => (string) $this->depo->oku($firmaId, 'teknik_servis_telegram_son_test_durumu', ''),
            'mesaj' => (string) $this->depo->oku($firmaId, 'teknik_servis_telegram_son_test_mesaji', ''),
            'tarih' => (string) $this->depo->oku($firmaId, 'teknik_servis_telegram_son_test_tarihi', ''),
        ];
    }

    public function testMesajiOnizleme(): string
    {
        return implode("\n", [
            'Yeni Servis Ekleme Bildirimi',
            '',
            'Fis No: TS-0001',
            'Musteri: Ornek Musteri',
            'Telefon: 05xx xxx xx xx',
            'Cihaz: Kamera DVR Model',
            'Ariza: Goruntu gelmiyor',
            'Aksesuarlar: Adaptör x1',
            'Durum: Servise Alindi',
            'Kabul Tarihi: '.now()->format('d.m.Y H:i'),
        ]);
    }

    public function sonHataMesaji(): ?string
    {
        return $this->sonHataMesaji;
    }

    private function gonder(TeknikServisKaydi $kayit, string $olay): bool
    {
        $firmaId = (int) $kayit->firma_id;
        if ($firmaId <= 0 || ! $this->olayAktifMi($firmaId, $olay)) {
            return false;
        }

        $konu = $olay === self::OLAY_TESLIM_EDILDI
            ? 'Teslim Edildi Bildirimi'
            : 'Yeni Servis Ekleme Bildirimi';

        return $this->telegramaGonder($firmaId, $kayit, $olay, $konu, $this->mesajHazirla($kayit, $olay));
    }

    private function olayAktifMi(int $firmaId, string $olay): bool
    {
        if (! (bool) $this->depo->oku($firmaId, 'teknik_servis_telegram_aktif_mi', false)) {
            return false;
        }

        $anahtar = match ($olay) {
            self::OLAY_TESLIM_EDILDI => 'teknik_servis_telegram_teslim_edildi_aktif_mi',
            default => 'teknik_servis_telegram_yeni_servis_aktif_mi',
        };

        return (bool) $this->depo->oku($firmaId, $anahtar, true);
    }

    private function telegramaGonder(int $firmaId, ?TeknikServisKaydi $kayit, string $olay, string $konu, string $mesaj): bool
    {
        $this->sonHataMesaji = null;

        $token = trim((string) $this->depo->oku($firmaId, 'telegram_bot_token', ''));
        $chatId = trim((string) $this->depo->oku($firmaId, 'telegram_chat_id', ''));

        if ($token === '' || $chatId === '') {
            $this->sonHataMesaji = 'Firma Ayarları Telegram bot token veya chat ID eksik.';

                Log::warning('Teknik servis Telegram bildirimi atlandi: ortak Telegram ayarlari eksik.', [
                'firma_id' => $firmaId,
                'token_var' => $token !== '',
                'chat_id_var' => $chatId !== '',
                'olay' => $olay,
            ]);

            $this->logla($firmaId, $kayit, $konu, $mesaj, 'atlanan', 'Firma Ayarları Telegram bot token veya chat ID eksik.');
            return false;
        }

        try {
            $yanit = Http::timeout(10)
                ->retry(2, 500)
                ->acceptJson()
                ->post('https://api.telegram.org/bot'.$token.'/sendMessage', [
                    'chat_id' => $chatId,
                    'text' => $mesaj,
                    'disable_web_page_preview' => true,
                ]);

            if ($yanit->successful() && (bool) data_get($yanit->json(), 'ok', false)) {
                $this->logla($firmaId, $kayit, $konu, $mesaj, 'gonderildi', null, (string) data_get($yanit->json(), 'result.message_id', ''));
                $this->cihazGorseliVarsaGonder($firmaId, $kayit, $token, $chatId);

                return true;
            }

            $hata = $this->hataMesajiniGizle(mb_substr($yanit->body(), 0, 500), $token);
            $this->sonHataMesaji = $hata;

            Log::warning('Teknik servis Telegram bildirimi API tarafinda reddedildi.', [
                'firma_id' => $firmaId,
                'teknik_servis_kaydi_id' => $kayit?->getKey(),
                'olay' => $olay,
                'http_status' => $yanit->status(),
                'yanit' => $hata,
            ]);

            $this->logla($firmaId, $kayit, $konu, $mesaj, 'hata', $hata);

            return false;
        } catch (\Throwable $e) {
            $hata = $this->hataMesajiniGizle($e->getMessage(), $token);
            $this->sonHataMesaji = $hata;

            Log::warning('Teknik servis Telegram bildirimi gonderilemedi.', [
                'firma_id' => $firmaId,
                'teknik_servis_kaydi_id' => $kayit?->getKey(),
                'olay' => $olay,
                'hata' => $hata,
            ]);

            $this->logla($firmaId, $kayit, $konu, $mesaj, 'hata', mb_substr($hata, 0, 500));

            return false;
        }
    }

    private function sonTestBilgisiniKaydet(int $firmaId, bool $basarili, ?string $hata = null): void
    {
        if ($firmaId <= 0) {
            return;
        }

        $this->depo->yaz($firmaId, 'teknik_servis_telegram_son_test_durumu', $basarili ? 'basarili' : 'basarisiz');
        $this->depo->yaz($firmaId, 'teknik_servis_telegram_son_test_mesaji', $basarili ? 'Test mesajı gönderildi.' : ($hata ?: 'Test mesajı gönderilemedi.'));
        $this->depo->yaz($firmaId, 'teknik_servis_telegram_son_test_tarihi', now()->format('d.m.Y H:i'));
    }

    private function mesajHazirla(TeknikServisKaydi $kayit, string $olay): string
    {
        $kayit->loadMissing(['cari', 'cihaz', 'marka', 'ariza', 'servisDurumu', 'aksesuarKayitlari.aksesuar']);

        $baslik = $olay === self::OLAY_TESLIM_EDILDI
            ? 'Teslim Edildi Bildirimi'
            : 'Yeni Servis Ekleme Bildirimi';

        $cihaz = trim((string) (($kayit->cihaz?->ad ?? '').' '.($kayit->marka?->ad ?? '').' '.($kayit->model_no ?? '')));
        $musteri = trim((string) ($kayit->musteri_ad_soyad ?: $kayit->cari?->ad ?: '-'));
        $telefon = trim((string) ($kayit->musteri_tel ?: $kayit->cari?->telefon ?: $kayit->cari?->gsm ?: '-'));

        $satirlar = [
            $baslik,
            '',
            'Fis No: '.($kayit->fis_no ?: '-'),
            'Musteri: '.$musteri,
            'Telefon: '.$telefon,
            'Cihaz: '.($cihaz !== '' ? $cihaz : '-'),
            'Ariza: '.($kayit->ariza?->ad ?: $kayit->musteri_sikayeti ?: '-'),
            'Aksesuarlar: '.$this->aksesuarlarMetni($kayit),
            'Durum: '.($kayit->servisDurumu?->ad ?: '-'),
            'Kabul Tarihi: '.($kayit->kabul_tarihi?->format('d.m.Y H:i') ?: '-'),
        ];

        if ($olay === self::OLAY_TESLIM_EDILDI) {
            $satirlar[] = 'Teslim Tarihi: '.($kayit->teslim_tarihi?->format('d.m.Y H:i') ?: now()->format('d.m.Y H:i'));
        }

        return implode("\n", $satirlar);
    }

    private function aksesuarlarMetni(TeknikServisKaydi $kayit): string
    {
        $aksesuarlar = $kayit->aksesuarKayitlari
            ->map(function ($kayit): string {
                $ad = trim((string) ($kayit->aksesuar?->ad ?? ''));
                if ($ad === '') {
                    return '';
                }

                $adet = (float) ($kayit->adet ?? 0);
                $adetMetni = $adet > 0
                    ? ' x'.rtrim(rtrim(number_format($adet, 2, '.', ''), '0'), '.')
                    : '';
                $not = trim((string) ($kayit->not ?? ''));

                return $ad.$adetMetni.($not !== '' ? ' ('.$not.')' : '');
            })
            ->filter()
            ->values()
            ->all();

        return $aksesuarlar !== [] ? implode(', ', $aksesuarlar) : '-';
    }

    private function cihazGorseliVarsaGonder(int $firmaId, ?TeknikServisKaydi $kayit, string $token, string $chatId): void
    {
        if (! $kayit) {
            return;
        }

        $gorselYolu = $this->ilkCihazGorseliYolu($kayit);
        if (! $gorselYolu) {
            return;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($gorselYolu)) {
            return;
        }

        $tamYol = $disk->path($gorselYolu);
        if (! is_file($tamYol)) {
            return;
        }

        try {
            $yanit = Http::timeout(20)
                ->attach('photo', fopen($tamYol, 'r'), basename($tamYol))
                ->post('https://api.telegram.org/bot'.$token.'/sendPhoto', [
                    'chat_id' => $chatId,
                    'caption' => 'Cihaz görseli - Fiş No: '.($kayit->fis_no ?: '-'),
                ]);

            if ($yanit->successful() && (bool) data_get($yanit->json(), 'ok', false)) {
                $this->logla(
                    $firmaId,
                    $kayit,
                    'Cihaz görseli',
                    'Cihaz görseli Telegram ile gönderildi: '.$gorselYolu,
                    'gonderildi',
                    null,
                    (string) data_get($yanit->json(), 'result.message_id', '')
                );

                return;
            }

            $this->logla($firmaId, $kayit, 'Cihaz görseli', $gorselYolu, 'hata', mb_substr($yanit->body(), 0, 500));
        } catch (\Throwable $e) {
            Log::warning('Teknik servis Telegram cihaz gorseli gonderilemedi.', [
                'firma_id' => $firmaId,
                'teknik_servis_kaydi_id' => $kayit->getKey(),
                'gorsel' => $gorselYolu,
                'hata' => $e->getMessage(),
            ]);

            $this->logla($firmaId, $kayit, 'Cihaz görseli', $gorselYolu, 'hata', mb_substr($e->getMessage(), 0, 500));
        }
    }

    private function ilkCihazGorseliYolu(TeknikServisKaydi $kayit): ?string
    {
        $gorseller = (array) ($kayit->cihaz_gorseller ?? []);

        foreach ($gorseller as $gorsel) {
            if (is_array($gorsel)) {
                $gorsel = $gorsel['path'] ?? $gorsel['url'] ?? reset($gorsel);
            }

            $yol = trim((string) $gorsel);
            if ($yol !== '') {
                return ltrim($yol, '/');
            }
        }

        return null;
    }

    private function logla(
        int $firmaId,
        ?TeknikServisKaydi $kayit,
        string $konu,
        string $mesaj,
        string $durum,
        ?string $hata = null,
        ?string $disId = null
    ): void {
        if ($firmaId <= 0) {
            return;
        }

        if (! $kayit) {
            return;
        }

        TeknikServisMesajLogu::query()->create([
            'firma_id' => $firmaId,
            'teknik_servis_kaydi_id' => $kayit->getKey(),
            'kanal' => 'telegram',
            'yon' => 'giden',
            'alici' => (string) $this->depo->oku($firmaId, 'telegram_chat_id', ''),
            'konu' => $konu,
            'icerik_ozeti' => mb_substr($mesaj, 0, 500),
            'dis_id' => $disId,
            'durum' => $durum,
            'hata_mesaji' => $hata,
            'gonderen_kullanici_id' => Auth::id(),
            'olay_tarihi' => now(),
        ]);
    }

    private function hataMesajiniGizle(string $hata, string $token): string
    {
        if ($token !== '') {
            $hata = str_replace($token, '[telegram-token]', $hata);
        }

        return (string) preg_replace('/bot\\d+:[A-Za-z0-9_-]+/', 'bot[telegram-token]', $hata);
    }
}
