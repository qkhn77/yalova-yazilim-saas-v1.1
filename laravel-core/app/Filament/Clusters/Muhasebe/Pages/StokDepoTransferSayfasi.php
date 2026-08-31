<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe;
use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeSayfaErisimleri;
use App\Models\Muhasebe\Depo;
use App\Models\Muhasebe\StokKarti;
use App\Muhasebe\Servisler\DepoBildirimServisi;
use App\Muhasebe\Servisler\StokHareketServisi;
use App\Services\TenantContextService;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;

class StokDepoTransferSayfasi extends Page implements HasForms
{
    use InteractsWithForms;
    use MuhasebeSayfaErisimleri;

    protected static ?string $cluster = Muhasebe::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string $view = 'filament.clusters.muhasebe.pages.stok-depo-transfer-sayfasi';

    protected static ?string $title = 'Depolar arası transfer';

    protected static ?string $slug = 'stok/depolar-arasi-transfer';

    public ?array $data = [];

    protected static function gerekliYetkiKodu(): string
    {
        return MuhasebeYetkiSablonlari::DEPO_GUNCELLE;
    }

    public function getSubNavigation(): array
    {
        return [];
    }

    public function mount(): void
    {
        $this->form->fill(['tarih' => now(), 'miktar' => null]);
    }

    private function stokSecenekleri(int $firmaId, string $arama): array
    {
        return StokKarti::query()->where('firma_id', $firmaId)->where('durum', 'aktif')
            ->where(function (Builder $query) use ($arama): void {
                $query->where('ad', 'like', '%'.trim($arama).'%')->orWhere('kod', 'like', '%'.trim($arama).'%');
            })
            ->orderBy('ad')->limit(50)->get(['id', 'kod', 'ad'])
            ->mapWithKeys(fn (StokKarti $stok): array => [$stok->id => $this->stokEtiketi($stok)])
            ->all();
    }

    private function stokEtiketi(StokKarti|int|null $stok): ?string
    {
        if (is_int($stok)) {
            $stok = StokKarti::query()->find($stok);
        }

        return $stok ? $stok->kod.' — '.$stok->ad : null;
    }

    public function form(Form $form): Form
    {
        $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);

        return $form->schema([
            Forms\Components\Select::make('stok_id')
                ->label('Stok')
                // Arama kutusu açılmadan önce de ilk seçenekleri hazırla. Filament
                // searchable alanlarda yalnızca getSearchResultsUsing kullanıldığında
                // Livewire yeniden çiziminden sonra seçim listesi boş kalabiliyor.
                ->options(fn (): array => $this->stokSecenekleri($firmaId, ''))
                ->optionsLimit(50)
                ->searchable()
                ->searchDebounce(300)
                ->getSearchResultsUsing(fn (string $search): array => $this->stokSecenekleri($firmaId, $search))
                ->getOptionLabelUsing(fn ($value): ?string => $this->stokEtiketi((int) $value))
                ->live(onBlur: true)
                ->required(),
            Forms\Components\Select::make('kaynak_depo_id')
                ->label('Kaynak depo')
                ->options(fn (): array => Depo::query()->where('firma_id', $firmaId)->aktif()->orderBy('ad')->pluck('ad', 'id')->all())
                ->searchable()
                ->live()
                ->required(),
            Forms\Components\Select::make('hedef_depo_id')
                ->label('Hedef depo')
                ->options(fn (): array => Depo::query()->where('firma_id', $firmaId)->aktif()->orderBy('ad')->pluck('ad', 'id')->all())
                ->searchable()
                ->live(onBlur: true)
                ->helperText(fn (Forms\Get $get): ?string => (int) ($get('kaynak_depo_id') ?? 0) > 0
                    && (int) ($get('kaynak_depo_id') ?? 0) === (int) ($get('hedef_depo_id') ?? 0)
                    ? 'Kaynak ve hedef depo aynı olamaz.'
                    : null)
                ->required(),
            Forms\Components\TextInput::make('miktar')
                ->label('Miktar')
                ->numeric()
                ->minValue(0.0001)
                ->required(),
            Forms\Components\DateTimePicker::make('tarih')
                ->label('Tarih')
                ->required(),
            Forms\Components\Textarea::make('aciklama')
                ->label('Açıklama')
                ->rows(3)
                ->columnSpanFull(),
        ])->columns(2)->statePath('data');
    }

    public function transferiKaydet(): void
    {
        $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
        $data = $this->form->getState();
        if ((int) $data['kaynak_depo_id'] === (int) $data['hedef_depo_id']) {
            throw ValidationException::withMessages(['hedef_depo_id' => 'Kaynak ve hedef depo aynı olamaz.']);
        }

        try {
            $transfer = app(StokHareketServisi::class)->transferOlustur($firmaId, $data);
        } catch (IsKuraliIstisnasi $exception) {
            $this->addError('data.miktar', $exception->getMessage());
            Notification::make()
                ->title('Depo transferi kaydedilemedi.')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        app(DepoBildirimServisi::class)->transferKaydedildi($transfer);
        $this->form->fill(['tarih' => now(), 'miktar' => null]);
        Notification::make()->title('Depo transferi kaydedildi.')->success()->send();
    }
}
