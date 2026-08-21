<?php

namespace App\Filament\Clusters\TeknikServis\Pages;

use App\Filament\Clusters\TeknikServis as TeknikServisCluster;
use App\Filament\Clusters\TeknikServis\Kaynaklar\TeknikServisAyarSayfaErisimleri;
use App\Models\Firma;
use App\Models\TeknikServis\TeknikServisMesajLogu;
use App\Services\TeknikServisTelegramBildirimServisi;
use App\Services\TenantContextService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Log;

class TelegramSayfasi extends Page
{
    use TeknikServisAyarSayfaErisimleri;

    protected static ?string $cluster = TeknikServisCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $title = 'Telegram';

    protected static ?string $navigationLabel = 'Telegram';

    protected static ?string $navigationGroup = 'Ayarlar ve şablonlar';

    protected static ?int $navigationSort = 45;

    protected static ?string $slug = 'ayarlar/telegram';

    protected static string $view = 'filament.clusters.teknik-servis.pages.telegram-sayfasi';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * @var array{durum: string, mesaj: string, tarih: string}
     */
    public array $sonTest = [
        'durum' => '',
        'mesaj' => '',
        'tarih' => '',
    ];

    /**
     * @var array<int, array{tarih: string, konu: string, durum: string, hata: string}>
     */
    public array $bildirimGecmisi = [];

    public string $mesajOnizleme = '';

    public function mount(TeknikServisTelegramBildirimServisi $telegram): void
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId <= 0) {
            abort(403);
        }

        $this->data = $telegram->ayarlariGetir($firmaId);
        $this->panelOzetleriniYukle($firmaId, $telegram);
    }

    public function getHeading(): string|Htmlable
    {
        return 'Telegram';
    }

    public function getSubheading(): ?string
    {
        return 'Teknik servis olayları için Telegram bot entegrasyonunu yönetin.';
    }

    public function kaydet(TeknikServisTelegramBildirimServisi $telegram): void
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId <= 0) {
            abort(403);
        }

        $telegram->kaydetAyarlar($firmaId, $this->dogrulanmisAyarlar());
        $this->panelOzetleriniYukle($firmaId, $telegram);

        Notification::make()
            ->title('Telegram ayarları kaydedildi.')
            ->success()
            ->send();
    }

    public function testGonder(TeknikServisTelegramBildirimServisi $telegram): void
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId <= 0) {
            abort(403);
        }

        $telegram->kaydetAyarlar($firmaId, $this->dogrulanmisAyarlar());
        $basarili = $telegram->testMesajiGonder($firmaId);
        $this->panelOzetleriniYukle($firmaId, $telegram);

        Log::info('Teknik servis Telegram test mesaji sonucu.', [
            'firma_id' => $firmaId,
            'basarili' => $basarili,
        ]);

        $bildirim = Notification::make()
            ->title($basarili ? 'Telegram test mesajı gönderildi.' : 'Telegram test mesajı gönderilemedi.');

        if (! $basarili) {
            $bildirim->body('Firma Ayarları sayfasındaki ortak bot token, chat ID veya Telegram API yanıtını kontrol edin.');
        }

        ($basarili ? $bildirim->success() : $bildirim->danger())->send();
    }

    private function panelOzetleriniYukle(int $firmaId, TeknikServisTelegramBildirimServisi $telegram): void
    {
        $this->sonTest = $telegram->sonTestBilgisiGetir($firmaId);
        $this->mesajOnizleme = $telegram->testMesajiOnizleme();
        $this->bildirimGecmisi = TeknikServisMesajLogu::query()
            ->withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->where('kanal', 'telegram')
            ->latest('olay_tarihi')
            ->limit(10)
            ->get(['konu', 'durum', 'hata_mesaji', 'olay_tarihi'])
            ->map(fn (TeknikServisMesajLogu $log): array => [
                'tarih' => $log->olay_tarihi?->format('d.m.Y H:i') ?: '-',
                'konu' => (string) ($log->konu ?: '-'),
                'durum' => (string) ($log->durum ?: '-'),
                'hata' => (string) ($log->hata_mesaji ?: ''),
            ])
            ->all();
    }

    private function aktifFirmaId(): int
    {
        $firmaId = (int) app(TenantContextService::class)->aktifFirmaId();

        if ($firmaId > 0) {
            return $firmaId;
        }

        return (int) Firma::query()->orderBy('id')->value('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function dogrulanmisAyarlar(): array
    {
        $veri = $this->validate([
            'data.teknik_servis_telegram_aktif_mi' => ['nullable', 'boolean'],
            'data.teknik_servis_telegram_yeni_servis_aktif_mi' => ['nullable', 'boolean'],
            'data.teknik_servis_telegram_teslim_edildi_aktif_mi' => ['nullable', 'boolean'],
        ])['data'];

        return [
            'teknik_servis_telegram_aktif_mi' => (bool) ($veri['teknik_servis_telegram_aktif_mi'] ?? false),
            'teknik_servis_telegram_yeni_servis_aktif_mi' => (bool) ($veri['teknik_servis_telegram_yeni_servis_aktif_mi'] ?? false),
            'teknik_servis_telegram_teslim_edildi_aktif_mi' => (bool) ($veri['teknik_servis_telegram_teslim_edildi_aktif_mi'] ?? false),
        ];
    }
}
