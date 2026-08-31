<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe as MuhasebeCluster;
use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeSayfaErisimleri;
use App\Models\Muhasebe\Cari;
use App\Muhasebe\Servisler\CariEkstreServisi;
use App\Services\TenantContextService;
use App\Support\MuhasebeYetkiSablonlari;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class CariEkstreSayfasi extends Page implements HasForms
{
    use InteractsWithForms;
    use MuhasebeSayfaErisimleri;

    protected static ?string $cluster = MuhasebeCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Cari Ekstreleri';

    protected static ?string $slug = 'cari-yonetimi/cari-ekstreleri';

    protected static string $view = 'filament.clusters.muhasebe.pages.cari-ekstre';

    /** @var array<string, mixed>|null */
    public ?array $rapor = null;

    /** @var array<string, mixed> */
    public array $data = [];

    public function getTitle(): string|Htmlable
    {
        return 'Cari Ekstre';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Cari Ekstre';
    }

    public function getSubheading(): ?string
    {
        return 'Seçilen cari ve para birimi için dönem içi hareketler, devreden ve güncel bakiye.';
    }

    protected static function gerekliYetkiKodu(): string
    {
        return MuhasebeYetkiSablonlari::CARI_GORUNTULE;
    }

    /**
     * {@see CariPolicy::view} ile hizalı: yalnızca güncelle yetkisi olan da ekstreyi açabilsin.
     *
     * @return array<int, string>
     */
    protected static function muhasebeSayfasiYetkiKodlari(): array
    {
        return [
            MuhasebeYetkiSablonlari::CARI_GORUNTULE,
            MuhasebeYetkiSablonlari::CARI_GUNCELLE,
        ];
    }

    public function getSubNavigation(): array
    {
        return [];
    }

    public function mount(): void
    {
        $cariId = (int) request()->query('cari_id', 0);
        $paraBirimi = strtoupper((string) request()->query('para_birimi', 'TRY'));

        $this->form->fill([
            'cari_id' => $cariId > 0 ? $cariId : null,
            'para_birimi' => $paraBirimi !== '' ? $paraBirimi : 'TRY',
            'baslangic' => (string) request()->query('baslangic', Carbon::now()->subDays(30)->format('Y-m-d')),
            'bitis' => (string) request()->query('bitis', Carbon::now()->format('Y-m-d')),
        ]);

        if ($cariId > 0 && (bool) request()->query('otomatik', false)) {
            $this->raporuGuncelle(false);
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\Select::make('cari_id')
                            ->label('Cari')
                            ->searchable()
                            ->options(fn (): array => $this->cariAramaSonuclari(''))
                            ->optionsLimit(50)
                            ->live(onBlur: true)
                            ->getSearchResultsUsing(fn (string $search): array => $this->cariAramaSonuclari($search))
                            ->getOptionLabelUsing(fn ($value): ?string => $this->cariSecimEtiketi((int) $value))
                            ->required()
                            ->helperText('Liste yalnızca erişebildiğiniz firmaya ait carileri gösterir.'),
                        Forms\Components\TextInput::make('para_birimi')
                            ->label('Para birimi')
                            ->required()
                            ->length(3)
                            ->default('TRY')
                            ->dehydrateStateUsing(fn (?string $state) => $state ? strtoupper($state) : $state),
                    ]),
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\DatePicker::make('baslangic')
                            ->label('Dönem başı')
                            ->required(),
                        Forms\Components\DatePicker::make('bitis')
                            ->label('Dönem sonu')
                            ->required(),
                    ]),
            ])
            ->columns(1)
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('raporla')
                ->label('Raporu güncelle')
                ->icon('heroicon-o-arrow-path')
                ->action('raporuGuncelle'),
        ];
    }

    public function raporuGuncelle(bool $bildirimGoster = true): void
    {
        $data = $this->form->getState();
        $firmaId = app(TenantContextService::class)->aktifFirmaId();
        if (! $firmaId) {
            if ($bildirimGoster) {
                Notification::make()
                    ->title('Aktif firma yok')
                    ->body('Lütfen üst menüden firma seçin veya süper yönetici oturumunda firma bağlamı kullanın.')
                    ->danger()
                    ->send();
            }

            return;
        }

        $cariId = (int) ($data['cari_id'] ?? 0);
        if ($cariId < 1) {
            if ($bildirimGoster) {
                Notification::make()->title('Cari seçin')->danger()->send();
            }

            return;
        }

        $cari = Cari::query()->whereKey($cariId)->first();
        if (! $cari || (int) $cari->firma_id !== (int) $firmaId) {
            if ($bildirimGoster) {
                Notification::make()->title('Bu cariye erişim yok')->danger()->send();
            }

            return;
        }

        $para = (string) ($data['para_birimi'] ?? 'TRY');
        $bas = Carbon::parse((string) $data['baslangic'])->startOfDay();
        $bit = Carbon::parse((string) $data['bitis'])->endOfDay();

        if ($bas->greaterThan($bit)) {
            if ($bildirimGoster) {
                Notification::make()->title('Başlangıç bitişten sonra olamaz')->danger()->send();
            }

            return;
        }

        $this->rapor = app(CariEkstreServisi::class)->ekstre(
            (int) $firmaId,
            $cariId,
            $para,
            $bas,
            $bit
        );

        if ($bildirimGoster) {
            Notification::make()->title('Ekstre güncellendi')->success()->send();
        }
    }

    private function cariAramaSonuclari(string $search): array
    {
        $firmaId = app(TenantContextService::class)->aktifFirmaId();
        if (! $firmaId) {
            return [];
        }

        $arama = trim($search);

        return Cari::query()
            ->where('firma_id', $firmaId)
            ->when($arama !== '', function (Builder $query) use ($arama): Builder {
                return $query->where(function (Builder $alt) use ($arama): void {
                    $alt->where('ad', 'like', '%'.$arama.'%')
                        ->orWhere('kod', 'like', '%'.$arama.'%')
                        ->orWhere('telefon', 'like', '%'.$arama.'%');
                });
            })
            ->orderBy('ad')
            ->limit(50)
            ->get(['id', 'ad', 'kod'])
            ->mapWithKeys(fn (Cari $cari): array => [
                $cari->id => $this->cariSecimMetni($cari),
            ])
            ->all();
    }

    private function cariSecimEtiketi(int $cariId): ?string
    {
        if ($cariId < 1) {
            return null;
        }

        $firmaId = app(TenantContextService::class)->aktifFirmaId();
        if (! $firmaId) {
            return null;
        }

        $cari = Cari::query()
            ->where('firma_id', $firmaId)
            ->whereKey($cariId)
            ->first(['id', 'ad', 'kod']);

        return $cari instanceof Cari ? $this->cariSecimMetni($cari) : null;
    }

    private function cariSecimMetni(Cari $cari): string
    {
        return trim((string) $cari->ad.($cari->kod ? ' - '.$cari->kod : ''));
    }
}
