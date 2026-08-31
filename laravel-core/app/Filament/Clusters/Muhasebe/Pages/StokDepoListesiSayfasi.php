<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeSayfaErisimleri;
use App\Models\Muhasebe\Depo;
use App\Models\Muhasebe\StokDepoBakiyesi;
use App\Filament\Clusters\Muhasebe;
use App\Services\TenantContextService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use App\Support\MuhasebeYetkiSablonlari;

class StokDepoListesiSayfasi extends Page implements HasForms
{
    use InteractsWithForms;
    use MuhasebeSayfaErisimleri;

    protected static ?string $cluster = Muhasebe::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static string $view = 'filament.clusters.muhasebe.pages.stok-depo-listesi-sayfasi';

    protected static ?string $title = 'Depo Stokları';

    protected static ?string $slug = 'stok/depo-stoklari';

    public ?array $data = [];

    protected static function gerekliYetkiKodu(): string
    {
        return MuhasebeYetkiSablonlari::DEPO_GORUNTULE;
    }

    public function getSubNavigation(): array
    {
        return [];
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        $firmaId = $this->firmaId();

        return $form
            ->schema([
                Forms\Components\Select::make('depo_id')
                    ->label('Depo')
                    ->options(fn (): array => [0 => 'Tüm depolar'] + Depo::query()
                        ->where('firma_id', $firmaId)
                        ->orderBy('ad')
                        ->pluck('ad', 'id')
                        ->all())
                    ->default(0)
                    ->live(),
                Forms\Components\TextInput::make('arama')
                    ->label('Stok ara')
                    ->placeholder('Kod veya ürün adı')
                    ->live(debounce: 350),
            ])
            ->columns(2)
            ->statePath('data');
    }

    public function getBakiyelerProperty()
    {
        $state = $this->form->getState();
        $firmaId = $this->firmaId();
        $depoId = (int) ($state['depo_id'] ?? 0);
        $arama = trim((string) ($state['arama'] ?? ''));

        return StokDepoBakiyesi::query()
            ->where('firma_id', $firmaId)
            ->when($depoId > 0, fn (Builder $query): Builder => $query->where('depo_id', $depoId))
            ->when($arama !== '', function (Builder $query) use ($arama): Builder {
                return $query->whereHas('stokKarti', fn (Builder $stok): Builder => $stok
                    ->where('ad', 'like', '%'.$arama.'%')
                    ->orWhere('kod', 'like', '%'.$arama.'%'));
            })
            ->with([
                'depo:id,ad,kod',
                'stokKarti:id,firma_id,kod,ad,birim,stok_takip,guncel_birim_maliyet',
            ])
            ->orderByDesc('miktar')
            ->limit(500)
            ->get();
    }

    private function firmaId(): int
    {
        return (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
    }
}
