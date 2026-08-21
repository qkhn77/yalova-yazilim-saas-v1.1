<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe as MuhasebeCluster;
use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeSayfaErisimleri;
use App\Services\MuhasebeDisaAktarimServisi;
use App\Services\TenantContextService;
use App\Support\MuhasebeYetkiSablonlari;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class GelirGiderRaporuSayfasi extends Page implements HasForms
{
    use InteractsWithForms;
    use MuhasebeSayfaErisimleri;

    protected static ?string $cluster = MuhasebeCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Gelir Gider Raporu';

    protected static ?string $slug = 'raporlar/gelir-gider';

    protected static string $view = 'filament.clusters.muhasebe.pages.gelir-gider-raporu';

    /** @var array{baslangic: string, bitis: string} */
    public array $filtreler = [];

    /** @var array<string, mixed> */
    public array $rapor = [];

    public function mount(): void
    {
        $this->filtreler = [
            'baslangic' => now()->startOfMonth()->toDateString(),
            'bitis' => now()->toDateString(),
        ];

        $this->form->fill($this->filtreler);
        $this->raporuYukle(false);
    }

    public function getHeading(): string|Htmlable
    {
        return 'Gelir / gider raporu';
    }

    public function getSubheading(): ?string
    {
        return 'Onaylı faturaları ve hızlı masraf kayıtlarını para birimi bazında karşılaştırın.';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('baslangic')
                    ->label('Başlangıç tarihi')
                    ->required()
                    ->native(false)
                    ->maxDate(fn (Forms\Get $get) => $get('bitis') ?: now()->toDateString()),
                Forms\Components\DatePicker::make('bitis')
                    ->label('Bitiş tarihi')
                    ->required()
                    ->native(false)
                    ->minDate(fn (Forms\Get $get) => $get('baslangic'))
                    ->maxDate(now()->toDateString()),
            ])
            ->statePath('filtreler');
    }

    public function raporuYukle(bool $bildirimGoster = true): void
    {
        $this->validate([
            'filtreler.baslangic' => ['required', 'date'],
            'filtreler.bitis' => ['required', 'date', 'after_or_equal:filtreler.baslangic'],
        ]);

        $firmaId = $this->aktifFirmaId();
        if ($firmaId === null) {
            $this->rapor = ['firma_id' => null, 'satirlar' => []];

            if ($bildirimGoster) {
                Notification::make()
                    ->title('Aktif firma bulunamadı')
                    ->body('Raporu görmek için önce aktif firma seçin.')
                    ->warning()
                    ->send();
            }

            return;
        }

        $baslangic = Carbon::parse($this->filtreler['baslangic'])->startOfDay();
        $bitis = Carbon::parse($this->filtreler['bitis'])->endOfDay();
        if ($baslangic->diffInDays($bitis) > 366) {
            throw ValidationException::withMessages([
                'filtreler.bitis' => 'Rapor dönemi en fazla 366 gün olabilir.',
            ]);
        }

        $cacheKey = 'muhasebe:gelir-gider-raporu:v1:firma:'.$firmaId.':'.$baslangic->toDateString().':'.$bitis->toDateString();
        $satirlar = Cache::remember(
            $cacheKey,
            now()->addSeconds(30),
            fn (): array => app(MuhasebeDisaAktarimServisi::class)->gelirGiderOzeti($firmaId, $baslangic, $bitis),
        );

        $this->rapor = [
            'firma_id' => $firmaId,
            'baslangic' => $baslangic->toDateString(),
            'bitis' => $bitis->toDateString(),
            'baslangic_gosterim' => $baslangic->format('d.m.Y'),
            'bitis_gosterim' => $bitis->format('d.m.Y'),
            'satirlar' => $satirlar,
            'fatura_adedi' => array_sum(array_map(fn (array $satir): int => (int) ($satir['Fatura Adedi'] ?? 0), $satirlar)),
        ];

        if ($bildirimGoster) {
            Notification::make()
                ->title('Gelir-gider raporu yenilendi')
                ->body(count($satirlar).' para birimi kırılımı gösteriliyor.')
                ->success()
                ->send();
        }
    }

    public function filtreleriSifirla(): void
    {
        $this->filtreler = [
            'baslangic' => now()->startOfMonth()->toDateString(),
            'bitis' => now()->toDateString(),
        ];
        $this->form->fill($this->filtreler);
        $this->raporuYukle();
    }

    private function aktifFirmaId(): ?int
    {
        $firmaId = app(TenantContextService::class)->aktifFirmaId();

        return $firmaId ? (int) $firmaId : null;
    }

    protected static function gerekliYetkiKodu(): string
    {
        return MuhasebeYetkiSablonlari::RAPOR_GORUNTULE;
    }
}
