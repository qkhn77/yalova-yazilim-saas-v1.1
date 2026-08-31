<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeSayfaErisimleri;
use App\Filament\Clusters\Muhasebe\Pages\StokSerileriSayfasi;
use App\Models\Muhasebe\Depo;
use App\Models\Muhasebe\StokKarti;
use App\Filament\Clusters\Muhasebe;
use App\Muhasebe\Servisler\DepoBildirimServisi;
use App\Muhasebe\Servisler\StokHareketServisi;
use App\Services\TenantContextService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Support\MuhasebeYetkiSablonlari;

class StokDepoSayimSayfasi extends Page implements HasForms
{
    use InteractsWithForms;
    use MuhasebeSayfaErisimleri;

    protected static ?string $cluster = Muhasebe::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string $view = 'filament.clusters.muhasebe.pages.stok-depo-sayim-sayfasi';

    protected static ?string $title = 'Depo stok sayımı';

    protected static ?string $slug = 'stok/depo-stok-sayimi';

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
        $this->form->fill();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('seriSayiminaGit')
                ->label('Seri No Barkodu sayımı')
                ->icon('heroicon-o-qr-code')
                ->color('gray')
                ->url(fn (): string => StokSerileriSayfasi::getUrl())
                ->visible(fn (): bool => StokSerileriSayfasi::canAccess()),
        ];
    }

    public function form(Form $form): Form
    {
        $firmaId = $this->firmaId();

        return $form
            ->schema([
                Forms\Components\Select::make('stok_id')
                    ->label('Stok')
                    ->options(fn (): array => StokKarti::query()
                        ->where('firma_id', $firmaId)
                        ->where('durum', 'aktif')
                        ->where(function ($query): void {
                            $query->whereNull('stok_takip_tipi')
                                ->orWhere('stok_takip_tipi', StokKarti::STOK_TAKIP_TIPI_BASIT);
                        })
                        ->orderBy('ad')
                        ->limit(500)
                        ->get()
                        ->mapWithKeys(fn (StokKarti $stok): array => [$stok->id => $stok->kod.' — '.$stok->ad])
                        ->all())
                    ->searchable()
                    ->optionsLimit(50)
                    ->live(onBlur: true)
                    ->helperText('Bu ekran yalnızca basit stoklar içindir. Seri No Barkodu takipli ürünleri üstteki ilgili sayım ekranından yönetin.')
                    ->required(),
                Forms\Components\Select::make('depo_id')
                    ->label('Depo')
                    ->options(fn (): array => Depo::query()
                        ->where('firma_id', $firmaId)
                        ->aktif()
                        ->orderBy('ad')
                        ->pluck('ad', 'id')
                        ->all())
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('hedef_miktar')
                    ->label('Sayım sonucu')
                    ->helperText('Bu depoda fiilen bulunan miktarı yazın. Fark, stok hareketi olarak kaydedilir.')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                Forms\Components\Textarea::make('aciklama')
                    ->label('Açıklama')
                    ->placeholder('Örn. Aylık sayım, raf kontrolü')
                    ->rows(3)
                    ->columnSpanFull(),
            ])
            ->columns(2)
            ->statePath('data');
    }

    public function sayimiUygula(): void
    {
        $firmaId = $this->firmaId();
        if ($firmaId < 1) {
            throw ValidationException::withMessages(['stok_id' => 'Aktif firma bulunamadı.']);
        }

        $data = $this->form->getState();
        $stok = StokKarti::query()
            ->where('firma_id', $firmaId)
            ->whereKey((int) $data['stok_id'])
            ->first();

        if (! $stok) {
            throw ValidationException::withMessages(['stok_id' => 'Seçilen stok bulunamadı.']);
        }

        if ((string) ($stok->stok_takip_tipi ?? '') === StokKarti::STOK_TAKIP_TIPI_SERI) {
            throw ValidationException::withMessages([
                'stok_id' => 'Seri numarası takipli ürünlerde özel sayım akışı kullanılmalıdır.',
            ]);
        }

        $hareket = app(StokHareketServisi::class)->depoSayiminiUygula(
            $firmaId,
            (int) $stok->id,
            (int) $data['depo_id'],
            $data['hedef_miktar'],
            abs(crc32((string) Str::uuid())),
            trim((string) ($data['aciklama'] ?? '')) ?: 'Depo stok sayımı',
        );
        $hareket->loadMissing('depo:id,ad');
        app(DepoBildirimServisi::class)->sayimKaydedildi(
            $firmaId,
            (int) $stok->id,
            (int) $data['depo_id'],
            bcsub((string) ($hareket->sonraki_miktar ?? 0), (string) ($hareket->onceki_miktar ?? 0), 4),
            (string) $stok->ad,
            (string) ($hareket->depo?->ad ?? 'Depo'),
        );

        $this->form->fill();
        Notification::make()->title('Depo sayımı kaydedildi.')->success()->send();
    }

    private function firmaId(): int
    {
        return (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
    }
}
