<?php

namespace App\Filament\Clusters\MasrafTakip\Pages;

use App\Filament\Clusters\MasrafTakip as MasrafTakipCluster;
use App\Filament\Clusters\MasrafTakip\Kaynaklar\MasrafTakipFilamentErisimYardimcisi;
use App\Filament\Clusters\MasrafTakip\Kaynaklar\MasrafTakipSayfaErisimleri;
use App\Models\Muhasebe\MasrafKategorisi;
use App\Models\Masraf\MasrafButcesi;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Servisler\MasrafButceServisi;
use App\Services\TenantContextService;
use App\Support\MasrafTakipYetkiSablonlari;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MasrafButceleriSayfasi extends Page implements HasTable
{
    use InteractsWithTable;
    use MasrafTakipSayfaErisimleri;

    protected static ?string $cluster = MasrafTakipCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Masraf Bütçeleri';

    protected static ?string $slug = 'tanimlar/butceler';

    protected static string $view = 'filament.clusters.masraf-takip.pages.masraf-butceleri';

    public function getHeading(): string
    {
        return 'Masraf bütçeleri';
    }

    public function getSubheading(): ?string
    {
        return 'Kategori bazında dönem bütçeleri tanımlayın ve gerçekleşen giderleri raporlarda karşılaştırın.';
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('yeniButce')
                ->label('Yeni bütçe')
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => $this->yetkiVarMi(MasrafTakipYetkiSablonlari::OLUSTUR))
                ->form($this->butceFormu())
                ->action(fn (array $data): mixed => $this->butceKaydet($data)),
            Action::make('masrafTakibineDon')
                ->label('Masraflara dön')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(MasrafTakibiSayfasi::getUrl()),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(MasrafButcesi::query()->with('kategori:id,ad,ust_kategori_id')->where('firma_id', $this->aktifFirmaId() ?? 0))
            ->deferLoading()
            ->defaultSort('donem_baslangic', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('kategori.ad')
                    ->label('Masraf türü')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('donem_baslangic')
                    ->label('Başlangıç')
                    ->date('d.m.Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('donem_bitis')
                    ->label('Bitiş')
                    ->date('d.m.Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('butce_tutari')
                    ->label('Bütçe')
                    ->formatStateUsing(fn ($state, MasrafButcesi $record): string => number_format((float) $state, 2, ',', '.').' '.strtoupper($record->para_birimi ?: 'TRY'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('para_birimi')
                    ->label('Para birimi'),
                Tables\Columns\TextColumn::make('durum')
                    ->label('Durum')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === MasrafButcesi::DURUM_AKTIF ? 'Aktif' : 'Kapalı')
                    ->color(fn (string $state): string => $state === MasrafButcesi::DURUM_AKTIF ? 'success' : 'gray'),
            ])
            ->actions([
                Tables\Actions\Action::make('duzenle')
                    ->label('Düzenle')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (): bool => $this->yetkiVarMi(MasrafTakipYetkiSablonlari::GUNCELLE))
                    ->fillForm(fn (MasrafButcesi $record): array => $record->only([
                        'masraf_kategorisi_id', 'donem_baslangic', 'donem_bitis', 'butce_tutari', 'para_birimi', 'durum', 'notlar',
                    ]))
                    ->form($this->butceFormu())
                    ->action(fn (MasrafButcesi $record, array $data): mixed => $this->butceKaydet($data, (int) $record->getKey())),
                Tables\Actions\Action::make('durumDegistir')
                    ->label(fn (MasrafButcesi $record): string => $record->durum === MasrafButcesi::DURUM_AKTIF ? 'Kapat' : 'Aktifleştir')
                    ->icon(fn (MasrafButcesi $record): string => $record->durum === MasrafButcesi::DURUM_AKTIF ? 'heroicon-o-lock-closed' : 'heroicon-o-lock-open')
                    ->color(fn (MasrafButcesi $record): string => $record->durum === MasrafButcesi::DURUM_AKTIF ? 'warning' : 'success')
                    ->visible(fn (): bool => $this->yetkiVarMi(MasrafTakipYetkiSablonlari::GUNCELLE))
                    ->action(function (MasrafButcesi $record): void {
                        try {
                            app(MasrafButceServisi::class)->durumDegistir((int) $record->firma_id, (int) $record->getKey());
                            $this->resetTable();
                            Notification::make()->title('Bütçe durumu güncellendi')->success()->send();
                        } catch (\Throwable $exception) {
                            $this->uyariGoster('Bütçe güncellenemedi', $exception->getMessage());
                        }
                    }),
            ])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    /** @return array<int, Forms\Components\Component> */
    private function butceFormu(): array
    {
        return [
            Forms\Components\Select::make('masraf_kategorisi_id')
                ->label('Masraf türü')
                ->options(fn (): array => $this->kategoriSecenekleri())
                ->searchable()
                ->required()
                ->native(false),
            Forms\Components\DatePicker::make('donem_baslangic')
                ->label('Dönem başlangıcı')
                ->required()
                ->native(false),
            Forms\Components\DatePicker::make('donem_bitis')
                ->label('Dönem bitişi')
                ->required()
                ->native(false),
            Forms\Components\TextInput::make('butce_tutari')
                ->label('Bütçe tutarı')
                ->numeric()
                ->minValue(0.01)
                ->step('0.01')
                ->required(),
            Forms\Components\Select::make('para_birimi')
                ->label('Para birimi')
                ->options(['TRY' => '₺ Türk Lirası', 'USD' => '$ Amerikan Doları', 'EUR' => '€ Euro', 'GBP' => '£ İngiliz Sterlini'])
                ->default('TRY')
                ->required()
                ->native(false),
            Forms\Components\Select::make('durum')
                ->label('Durum')
                ->options([MasrafButcesi::DURUM_AKTIF => 'Aktif', MasrafButcesi::DURUM_KAPALI => 'Kapalı'])
                ->default(MasrafButcesi::DURUM_AKTIF)
                ->required()
                ->native(false),
            Forms\Components\Textarea::make('notlar')
                ->label('Notlar')
                ->rows(3)
                ->maxLength(2000)
                ->columnSpanFull(),
        ];
    }

    /** @param array<string, mixed> $data */
    private function butceKaydet(array $data, ?int $butceId = null): mixed
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId === null) {
            return $this->uyariGoster('Aktif firma bulunamadı', 'Bütçe kaydetmek için önce aktif firma seçin.');
        }

        try {
            app(MasrafButceServisi::class)->kaydet($firmaId, $data, $butceId);
        } catch (IsKuraliIstisnasi $exception) {
            return $this->uyariGoster('Bütçe kaydedilemedi', $exception->getMessage());
        }

        $this->resetTable();
        Notification::make()->title($butceId === null ? 'Bütçe eklendi' : 'Bütçe güncellendi')->success()->send();

        return null;
    }

    /** @return array<int|string, string> */
    private function kategoriSecenekleri(): array
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId === null) {
            return [];
        }

        return MasrafKategorisi::query()
            ->where('firma_id', $firmaId)
            ->where('aktif_mi', true)
            ->where('secilir_mi', true)
            ->with('ustKategori:id,ad')
            ->orderBy('sira')
            ->get(['id', 'ad', 'ust_kategori_id'])
            ->mapWithKeys(fn (MasrafKategorisi $kategori): array => [
                $kategori->id => $kategori->ustKategori ? $kategori->ustKategori->ad.' / '.$kategori->ad : $kategori->ad,
            ])
            ->all();
    }

    private function aktifFirmaId(): ?int
    {
        $firmaId = app(TenantContextService::class)->aktifFirmaId();

        return $firmaId ? (int) $firmaId : null;
    }

    private function yetkiVarMi(string $yetki): bool
    {
        return MasrafTakipFilamentErisimYardimcisi::masrafTakipYetkisiVarMi($yetki);
    }

    private function uyariGoster(string $baslik, string $govde): void
    {
        Notification::make()->title($baslik)->body($govde)->warning()->send();
    }
}
