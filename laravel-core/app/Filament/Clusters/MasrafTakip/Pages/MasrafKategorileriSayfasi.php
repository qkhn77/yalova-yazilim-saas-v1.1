<?php

namespace App\Filament\Clusters\MasrafTakip\Pages;

use App\Filament\Clusters\MasrafTakip as MasrafTakipCluster;
use App\Filament\Clusters\MasrafTakip\Kaynaklar\MasrafTakipFilamentErisimYardimcisi;
use App\Filament\Clusters\MasrafTakip\Kaynaklar\MasrafTakipSayfaErisimleri;
use App\Models\Muhasebe\MasrafKategorisi;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Servisler\MasrafKategoriServisi;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;

class MasrafKategorileriSayfasi extends Page implements HasTable
{
    use InteractsWithTable;
    use MasrafTakipSayfaErisimleri;

    protected static ?string $cluster = MasrafTakipCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Masraf Türleri';

    protected static ?string $slug = 'tanimlar/masraf-turleri';

    protected static string $view = 'filament.clusters.masraf-takip.pages.masraf-kategorileri';

    public function getHeading(): string|HtmlString
    {
        return 'Masraf türleri';
    }

    public function getSubheading(): ?string
    {
        return 'Kayıtlarda kullanılacak masraf türlerini firma bazında yönetin.';
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('yeniMasrafTuru')
                ->label('Yeni masraf türü')
                ->icon('heroicon-m-plus')
                ->visible(fn (): bool => $this->yetkiVarMi(MasrafTakipYetkiSablonlari::OLUSTUR))
                ->form($this->kategoriFormu())
                ->action(fn (array $data): mixed => $this->kategoriKaydet($data)),
            Action::make('masrafTakibineDon')
                ->label('Masraf takibine dön')
                ->icon('heroicon-m-arrow-left')
                ->color('gray')
                ->url(MasrafTakibiSayfasi::getUrl()),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->kategoriSorgusu())
            ->deferLoading()
            ->defaultSort('sira')
            ->columns([
                Tables\Columns\TextColumn::make('ad')
                    ->label('Masraf türü')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ustKategori.ad')
                    ->label('Ana kategori')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('kod')
                    ->label('Kod')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('masraflar_count')
                    ->label('Kayıt')
                    ->counts('masraflar')
                    ->sortable(),
                Tables\Columns\TextColumn::make('sira')
                    ->label('Sıra')
                    ->sortable(),
                Tables\Columns\TextColumn::make('sistem_mi')
                    ->label('Kaynak')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Sabit' : 'Firma')
                    ->color(fn (bool $state): string => $state ? 'info' : 'gray'),
                Tables\Columns\TextColumn::make('aktif_mi')
                    ->label('Durum')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Aktif' : 'Pasif')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->actions([
                Tables\Actions\Action::make('duzenle')
                    ->label('Düzenle')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (): bool => $this->yetkiVarMi(MasrafTakipYetkiSablonlari::GUNCELLE))
                    ->fillForm(fn (MasrafKategorisi $record): array => [
                        'ad' => $record->ad,
                        'ust_kategori_id' => $record->ust_kategori_id,
                        'sira' => $record->sira,
                        'aktif_mi' => $record->aktif_mi,
                    ])
                    ->form(fn (MasrafKategorisi $record): array => $this->kategoriFormu((int) $record->getKey()))
                    ->action(fn (MasrafKategorisi $record, array $data): mixed => $this->kategoriKaydet($data, (int) $record->getKey())),
                Tables\Actions\Action::make('durumDegistir')
                    ->label(fn (MasrafKategorisi $record): string => $record->aktif_mi ? 'Pasifleştir' : 'Aktifleştir')
                    ->icon(fn (MasrafKategorisi $record): string => $record->aktif_mi ? 'heroicon-o-pause-circle' : 'heroicon-o-play-circle')
                    ->color(fn (MasrafKategorisi $record): string => $record->aktif_mi ? 'warning' : 'success')
                    ->visible(fn (): bool => $this->yetkiVarMi(MasrafTakipYetkiSablonlari::GUNCELLE))
                    ->requiresConfirmation()
                    ->modalDescription('Pasif türler yeni masraf kaydında seçilemez; geçmiş kayıtlar korunur.')
                    ->action(fn (MasrafKategorisi $record): mixed => $this->kategoriDurumunuDegistir((int) $record->getKey())),
            ])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    /** @return array<int, Forms\Components\Component> */
    private function kategoriFormu(?int $kategoriId = null): array
    {
        return [
            Forms\Components\Select::make('ust_kategori_id')
                ->label('Ana kategori')
                ->options(fn (): array => $this->ustKategoriSecenekleri($kategoriId))
                ->placeholder('Ana kategori yok')
                ->searchable()
                ->native(false)
                ->helperText('En fazla iki seviye desteklenir. Sabit ana kategoriler sistem tarafından korunur.'),
            Forms\Components\TextInput::make('ad')
                ->label('Masraf türü adı')
                ->required()
                ->maxLength(120)
                ->placeholder('Örn. Kargo'),
            Forms\Components\TextInput::make('sira')
                ->label('Sıra')
                ->numeric()
                ->integer()
                ->minValue(0)
                ->maxValue(65535)
                ->default(500),
            Forms\Components\Toggle::make('aktif_mi')
                ->label('Aktif')
                ->default(true),
        ];
    }

    private function kategoriKaydet(array $data, ?int $kategoriId = null): mixed
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId === null) {
            return $this->uyariGoster('Aktif firma bulunamadı', 'Masraf türü yönetmek için önce aktif firma seçin.');
        }

        try {
            app(MasrafKategoriServisi::class)->kaydet($firmaId, $data, $kategoriId);
        } catch (IsKuraliIstisnasi $exception) {
            return $this->uyariGoster('Masraf türü kaydedilemedi', $exception->getMessage());
        }

        $this->resetTable();
        Notification::make()
            ->title($kategoriId === null ? 'Masraf türü eklendi' : 'Masraf türü güncellendi')
            ->success()
            ->send();

        return null;
    }

    private function kategoriDurumunuDegistir(int $kategoriId): mixed
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId === null) {
            return $this->uyariGoster('Aktif firma bulunamadı', 'Masraf türü yönetmek için önce aktif firma seçin.');
        }

        try {
            $kategori = app(MasrafKategoriServisi::class)->durumDegistir($firmaId, $kategoriId);
        } catch (IsKuraliIstisnasi $exception) {
            return $this->uyariGoster('Masraf türü güncellenemedi', $exception->getMessage());
        }

        $this->resetTable();
        Notification::make()
            ->title($kategori->aktif_mi ? 'Masraf türü aktifleştirildi' : 'Masraf türü pasifleştirildi')
            ->success()
            ->send();

        return null;
    }

    private function kategoriSorgusu(): Builder
    {
        return MasrafKategorisi::query()
            ->with(['ustKategori:id,ad'])
            ->withCount('masraflar')
            ->where('firma_id', $this->aktifFirmaId() ?? 0);
    }

    /** @return array<int|string, string> */
    private function ustKategoriSecenekleri(?int $kategoriId = null): array
    {
        $firmaId = $this->aktifFirmaId() ?? 0;

        $secenekler = Cache::remember(
            MasrafKategorisi::anaKategoriCacheAnahtari($firmaId),
            now()->addMinutes(10),
            fn (): array => MasrafKategorisi::query()
                ->where('firma_id', $firmaId)
                ->whereNull('ust_kategori_id')
                ->where('aktif_mi', true)
                ->orderBy('sira')
                ->orderBy('ad')
                ->pluck('ad', 'id')
                ->map(fn ($ad): string => (string) $ad)
                ->all(),
        );

        if ($kategoriId !== null) {
            unset($secenekler[$kategoriId]);
        }

        return $secenekler;
    }

    private function aktifFirmaId(): ?int
    {
        $firmaId = app(TenantContextService::class)->aktifFirmaId();

        return $firmaId ? (int) $firmaId : null;
    }

    private function yetkiVarMi(string $yetkiKodu): bool
    {
        return MasrafTakipFilamentErisimYardimcisi::masrafTakipYetkisiVarMi($yetkiKodu);
    }

    private function uyariGoster(string $baslik, string $govde): void
    {
        Notification::make()->title($baslik)->body($govde)->warning()->send();
    }
}
