<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\BarkodluSatis\Guvenlik\BarkodluSatisFilamentErisimYardimcisi;
use App\BarkodluSatis\Mutabakat\BarkodluSatisMuhasebeMutabakatServisi;
use App\Filament\Clusters\Muhasebe as MuhasebeCluster;
use App\Services\TenantContextService;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BarkodluSatisMuhasebeMutabakatSayfasi extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $cluster = MuhasebeCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Satis muhasebe mutabakat';

    protected static ?string $slug = 'satis/barkodlu-satis-muhasebe-mutabakat';

    protected static string $view = 'filament.clusters.muhasebe.pages.barkodlu-satis-muhasebe-mutabakat-sayfasi';

    /** @var array<string,mixed> */
    public array $data = [];

    /** @var array<string,mixed> */
    public array $ozet = [
        'kontrol_edilen' => 0,
        'toplam_sorun' => 0,
        'sorunlu_kayit' => 0,
        'kod_dagilimi' => [],
    ];

    /** @var array<int,array<string,mixed>> */
    public array $sorunlar = [];

    public function getHeading(): string|Htmlable
    {
        return 'Barkodlu satis muhasebe mutabakat';
    }

    public function getSubheading(): ?string
    {
        return 'Satis kayitlari ile finans tahsilat kayitlarini salt okunur olarak karsilastirin.';
    }

    public static function canAccess(): bool
    {
        return BarkodluSatisFilamentErisimYardimcisi::herhangiBirBarkodluSatisYetkisiVarMi([
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_GORUNTULE,
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_GUNCELLE,
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_IPTAL,
        ]);
    }

    public function mount(): void
    {
        $this->form->fill([
            'baslangic_tarihi' => now()->subDays(30)->toDateString(),
            'bitis_tarihi' => now()->toDateString(),
            'limit' => 1000,
            'sadece_kritik' => false,
            'iade_no_ara' => null,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('baslangic_tarihi')
                    ->label('Baslangic tarihi')
                    ->native(false)
                    ->required(),
                Forms\Components\DatePicker::make('bitis_tarihi')
                    ->label('Bitis tarihi')
                    ->native(false)
                    ->required(),
                Forms\Components\TextInput::make('limit')
                    ->label('Kayit limiti')
                    ->numeric()
                    ->minValue(50)
                    ->maxValue(5000)
                    ->default(1000)
                    ->required(),
                Forms\Components\Toggle::make('sadece_kritik')
                    ->label('Sadece kritik sorunlar')
                    ->default(false),
                Forms\Components\TextInput::make('iade_no_ara')
                    ->label('Iade no ara')
                    ->placeholder('Ornek: IAD-2026-000123')
                    ->maxLength(50),
            ])
            ->columns(5)
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export_csv')
                ->label('CSV Disa Aktar')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (): StreamedResponse {
                    $this->yukle();

                    return $this->sorunlarCsvIndir(false);
                }),
            Actions\Action::make('export_excel_csv')
                ->label('Excel Uyumlu CSV')
                ->icon('heroicon-o-document-chart-bar')
                ->color('success')
                ->action(function (): StreamedResponse {
                    $this->yukle();

                    return $this->sorunlarCsvIndir(true);
                }),
            Actions\Action::make('yenile')
                ->label('Kontrolu Yenile')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->action(fn () => $this->yukle()),
        ];
    }

    public function yukle(): void
    {
        $state = $this->form->getState();
        $baslangic = isset($state['baslangic_tarihi']) ? Carbon::parse((string) $state['baslangic_tarihi'])->startOfDay() : now()->subDays(30)->startOfDay();
        $bitis = isset($state['bitis_tarihi']) ? Carbon::parse((string) $state['bitis_tarihi'])->endOfDay() : now()->endOfDay();
        $limit = max(50, min(5000, (int) ($state['limit'] ?? 1000)));
        $sadeceKritik = (bool) ($state['sadece_kritik'] ?? false);
        $firmaId = app(TenantContextService::class)->aktifFirmaId();

        if ($baslangic->gt($bitis)) {
            Notification::make()
                ->title('Tarih araligi hatali')
                ->body('Baslangic tarihi bitis tarihinden buyuk olamaz.')
                ->danger()
                ->send();

            return;
        }

        $sonuc = app(BarkodluSatisMuhasebeMutabakatServisi::class)->raporla(
            firmaId: $firmaId ? (int) $firmaId : null,
            baslangic: $baslangic,
            bitis: $bitis,
            limit: $limit,
            sadeceKritik: $sadeceKritik
        );

        $this->ozet = [
            'kontrol_edilen' => (int) ($sonuc['kontrol_edilen'] ?? 0),
            'toplam_sorun' => (int) ($sonuc['toplam_sorun'] ?? 0),
            'sorunlu_kayit' => (int) ($sonuc['sorunlu_kayit'] ?? 0),
            'kod_dagilimi' => (array) ($sonuc['kod_dagilimi'] ?? []),
        ];
        $sorunlar = (array) ($sonuc['sorunlar'] ?? []);
        $iadeNoAra = Str::lower(trim((string) ($state['iade_no_ara'] ?? '')));
        if ($iadeNoAra !== '') {
            $sorunlar = array_values(array_filter($sorunlar, static function (array $sorun) use ($iadeNoAra): bool {
                $iadeNo = Str::lower(trim((string) ($sorun['iade_no'] ?? '')));

                return $iadeNo !== '' && str_contains($iadeNo, $iadeNoAra);
            }));
        }

        $this->sorunlar = $sorunlar;
        $this->ozet['toplam_sorun'] = count($this->sorunlar);
        $this->ozet['sorunlu_kayit'] = count(array_unique(array_map(
            static fn (array $s): int => (int) ($s['satis_id'] ?? 0),
            $this->sorunlar
        )));
        $this->ozet['kod_dagilimi'] = collect($this->sorunlar)
            ->map(fn (array $s): string => (string) ($s['kod'] ?? 'tanimsiz'))
            ->countBy()
            ->all();
    }

    public function sorunlarCsvIndir(bool $excelUyumlu = false): StreamedResponse
    {
        $state = $this->form->getState();
        $delimiter = $excelUyumlu ? ';' : ',';
        $dosyaAdi = 'barkodlu-satis-muhasebe-mutabakat-'.now()->format('Ymd_His').($excelUyumlu ? '-excel' : '').'.csv';

        return response()->streamDownload(function () use ($state, $delimiter, $excelUyumlu): void {
            $out = fopen('php://output', 'wb');
            if (! is_resource($out)) {
                return;
            }

            if ($excelUyumlu) {
                fwrite($out, "\xEF\xBB\xBF");
            }

            fputcsv($out, ['Rapor', 'Barkodlu Satis Muhasebe Mutabakat'], $delimiter);
            fputcsv($out, ['Olusturulma', now()->format('d.m.Y H:i:s')], $delimiter);
            fputcsv($out, ['Baslangic', (string) ($state['baslangic_tarihi'] ?? '-')], $delimiter);
            fputcsv($out, ['Bitis', (string) ($state['bitis_tarihi'] ?? '-')], $delimiter);
            fputcsv($out, ['Limit', (string) ($state['limit'] ?? '1000')], $delimiter);
            fputcsv($out, ['Sadece kritik', ((bool) ($state['sadece_kritik'] ?? false)) ? 'Evet' : 'Hayir'], $delimiter);
            fputcsv($out, ['Iade no ara', (string) ($state['iade_no_ara'] ?? '-')], $delimiter);
            fputcsv($out, ['Kontrol edilen', (string) ($this->ozet['kontrol_edilen'] ?? 0)], $delimiter);
            fputcsv($out, ['Sorunlu kayit', (string) ($this->ozet['sorunlu_kayit'] ?? 0)], $delimiter);
            fputcsv($out, ['Toplam sorun', (string) ($this->ozet['toplam_sorun'] ?? 0)], $delimiter);
            fputcsv($out, [], $delimiter);
            fputcsv($out, ['Kod', 'Seviye', 'Referans', 'Iade No', 'Firma', 'Satis No', 'Satis Tarihi', 'Cari', 'Odeme Tipi', 'Durum', 'Beklenen', 'Aktif Finans', 'Adet', 'Detay'], $delimiter);

            foreach ($this->sorunlar as $sorun) {
                fputcsv($out, [
                    (string) ($sorun['kod'] ?? ''),
                    (string) ($sorun['seviye'] ?? ''),
                    (string) ($sorun['referans_turu'] ?? 'barkodlu_satis'),
                    (string) ($sorun['iade_no'] ?? ''),
                    (string) ($sorun['firma_id'] ?? ''),
                    (string) ($sorun['satis_no'] ?? ''),
                    (string) ($sorun['satis_tarihi'] ?? ''),
                    (string) ($sorun['cari'] ?? ''),
                    (string) ($sorun['odeme_tipi'] ?? ''),
                    (string) ($sorun['durum'] ?? ''),
                    (string) ($sorun['beklenen_tutar'] ?? ''),
                    (string) ($sorun['aktif_finans_toplami'] ?? ''),
                    (string) ($sorun['aktif_finans_adedi'] ?? ''),
                    (string) ($sorun['detay'] ?? ''),
                ], $delimiter);
            }

            fclose($out);
        }, $dosyaAdi, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function iadeGecmisiUrl(array $sorun): ?string
    {
        $iadeNo = trim((string) ($sorun['iade_no'] ?? ''));
        if ($iadeNo === '') {
            return null;
        }

        return BarkodluSatisIadeGecmisiSayfasi::getUrl([
            'tableSearch' => $iadeNo,
        ]);
    }
}
