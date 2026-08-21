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

    private function parcaSecenekleri(int $firmaId, int $stokId, int $depoId, string $arama, string $siralama): array
    {
        if ($stokId < 1 || $depoId < 1) {
            return [];
        }
        $sorgu = StokParcasi::query()->where('firma_id', $firmaId)->where('stok_id', $stokId)->where('depo_id', $depoId)
            ->where('parca_mi', true)->where('kalan_miktar', '>', 0)
            ->when(trim($arama) !== '', function (Builder $query) use ($arama): void {
                $arama = trim($arama);
                $query->where(function (Builder $inner) use ($arama): void {
                    $inner->where('parca_kodu', 'like', '%'.$arama.'%')->orWhere('parca_kodu', 'like', '%'.$arama.'%')
                        ->orWhere('barkod', 'like', '%'.$arama.'%')->orWhere('plaka_no', 'like', '%'.$arama.'%')
                        ->orWhere('renk_desen', 'like', '%'.$arama.'%')->orWhere('kalite_sinifi', 'like', '%'.$arama.'%')
                        ->orWhere('metrekare', 'like', '%'.$arama.'%')->orWhere('kalan_miktar', 'like', '%'.$arama.'%')
                        ->orWhere('birim_maliyet', 'like', '%'.$arama.'%')->orWhere('created_at', 'like', '%'.$arama.'%');
                });
            });
        match ($siralama) {
            'tarih_eski' => $sorgu->orderBy('created_at'),
            'miktar_cok' => $sorgu->orderByDesc('kalan_miktar'),
            'miktar_az' => $sorgu->orderBy('kalan_miktar'),
            'olcu_buyuk' => $sorgu->orderByDesc('metrekare'),
            'olcu_kucuk' => $sorgu->orderByRaw('metrekare IS NULL')->orderBy('metrekare'),
            'maliyet_cok' => $sorgu->orderByDesc('birim_maliyet'),
            'maliyet_az' => $sorgu->orderByRaw('birim_maliyet IS NULL')->orderBy('birim_maliyet'),
            'desen' => $sorgu->orderByRaw('renk_desen IS NULL')->orderBy('renk_desen'),
            'kod' => $sorgu->orderByRaw('COALESCE(parca_kodu, parca_kodu)'),
            default => $sorgu->orderByDesc('created_at'),
        };

        return $sorgu->limit(50)->get()->mapWithKeys(fn (StokParcasi $parca): array => [$parca->id => $this->parcaEtiketi($parca)])->all();
    }

    private function parcaEtiketi(StokParcasi|int|null $parca): ?string
    {
        if (is_int($parca)) {
            $parca = StokParcasi::query()->find($parca);
        }
        if (! $parca) {
            return null;
        }

        return ($parca->parca_kodu ?: $parca->parca_kodu).' · Kalan: '.$parca->kalan_miktar
            .' · '.($parca->metrekare !== null ? $parca->metrekare.' m²' : 'ölçüsüz')
            .(filled($parca->renk_desen) ? ' · '.$parca->renk_desen : '');
    }

    private function parcaEtiketleri(array $degerler): array
    {
        return StokParcasi::query()->whereIn('id', array_map('intval', $degerler))->get()
            ->mapWithKeys(fn (StokParcasi $parca): array => [$parca->id => $this->parcaEtiketi($parca)])->all();
    }

    public function form(Form $form): Form
    {
        $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);

        return $form->schema([
            Forms\Components\Select::make('stok_id')
                ->label('Stok')
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => $this->stokSecenekleri($firmaId, $search))
                ->getOptionLabelUsing(fn ($value): ?string => $this->stokEtiketi((int) $value))
                ->live()
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

        $transfer = app(StokHareketServisi::class)->transferOlustur($firmaId, $data);
        app(DepoBildirimServisi::class)->transferKaydedildi($transfer);
        $this->form->fill(['tarih' => now(), 'miktar' => null]);
        Notification::make()->title('Depo transferi kaydedildi.')->success()->send();
    }
}
