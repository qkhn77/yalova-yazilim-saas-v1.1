<?php

namespace App\Filament\Clusters\MasrafTakip\Pages;

use App\Filament\Clusters\MasrafTakip as MasrafTakipCluster;
use App\Filament\Clusters\MasrafTakip\Kaynaklar\MasrafTakipFilamentErisimYardimcisi;
use App\Filament\Clusters\MasrafTakip\Kaynaklar\MasrafTakipSayfaErisimleri;
use App\Models\Masraf\DuzenliFaturaTanimi;
use App\Models\Muhasebe\MasrafKategorisi;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Servisler\DuzenliFaturaTanimiServisi;
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

class DuzenliFaturaTanimlariSayfasi extends Page implements HasTable
{
    use InteractsWithTable;
    use MasrafTakipSayfaErisimleri;

    protected static ?string $cluster = MasrafTakipCluster::class;
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $title = 'Düzenli Faturalar';
    protected static ?string $slug = 'tanimlar/duzenli-faturalar';
    protected static string $view = 'filament.clusters.masraf-takip.pages.duzenli-faturalar';

    public function mount(): void
    {
        if ($firmaId = $this->aktifFirmaId()) {
            MasrafKategorisi::varsayilanlariHazirla($firmaId);
        }
    }

    public function getHeading(): string
    {
        return 'Düzenli fatura tanımları';
    }

    public function getSubheading(): ?string
    {
        return 'Elektrik, su, doğalgaz, telefon ve benzeri aylık faturaların tanım kartlarını yönetin.';
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('yeniTanim')
                ->label('Yeni fatura tanımı')
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => $this->yetkiVarMi(MasrafTakipYetkiSablonlari::OLUSTUR))
                ->form($this->tanimFormu())
                ->action(fn (array $data): mixed => $this->tanimKaydet($data)),
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
            ->query(DuzenliFaturaTanimi::query()->with('kategori:id,ad')->where('firma_id', $this->aktifFirmaId() ?? 0))
            ->deferLoading()
            ->defaultSort('ad')
            ->columns([
                Tables\Columns\TextColumn::make('ad')->label('Tanım')->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('kategori.ad')->label('Masraf türü')->badge(),
                Tables\Columns\TextColumn::make('abone_no')->label('Abone / sözleşme no')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('tedarikci')->label('Tedarikçi')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('masraflar_count')->label('Masraf kaydı')->counts('masraflar')->sortable(),
                Tables\Columns\IconColumn::make('aktif_mi')->label('Aktif')->boolean(),
            ])
            ->actions([
                Tables\Actions\Action::make('duzenle')
                    ->label('Düzenle')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (): bool => $this->yetkiVarMi(MasrafTakipYetkiSablonlari::GUNCELLE))
                    ->fillForm(fn (DuzenliFaturaTanimi $record): array => $record->only(['masraf_kategorisi_id', 'ad', 'abone_no', 'tedarikci', 'aktif_mi', 'notlar']))
                    ->form($this->tanimFormu())
                    ->action(fn (DuzenliFaturaTanimi $record, array $data): mixed => $this->tanimKaydet($data, (int) $record->getKey())),
                Tables\Actions\Action::make('durumDegistir')
                    ->label(fn (DuzenliFaturaTanimi $record): string => $record->aktif_mi ? 'Pasifleştir' : 'Aktifleştir')
                    ->icon(fn (DuzenliFaturaTanimi $record): string => $record->aktif_mi ? 'heroicon-o-pause-circle' : 'heroicon-o-play-circle')
                    ->color(fn (DuzenliFaturaTanimi $record): string => $record->aktif_mi ? 'warning' : 'success')
                    ->visible(fn (): bool => $this->yetkiVarMi(MasrafTakipYetkiSablonlari::GUNCELLE))
                    ->action(function (DuzenliFaturaTanimi $record): void {
                        app(DuzenliFaturaTanimiServisi::class)->durumDegistir((int) $record->firma_id, (int) $record->getKey());
                        $this->resetTable();
                    }),
            ])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    /** @return array<int, Forms\Components\Component> */
    private function tanimFormu(): array
    {
        return [
            Forms\Components\Select::make('masraf_kategorisi_id')
                ->label('Masraf türü')
                ->options(fn (): array => $this->duzenliFaturaKategorileri())
                ->searchable()->native(false)->required(),
            Forms\Components\TextInput::make('ad')->label('Tanım adı')->required()->maxLength(120)->placeholder('Örn. Merkez ofis elektriği'),
            Forms\Components\TextInput::make('abone_no')->label('Abone / sözleşme no')->maxLength(120),
            Forms\Components\TextInput::make('tedarikci')->label('Tedarikçi')->maxLength(160),
            Forms\Components\Toggle::make('aktif_mi')->label('Aktif')->default(true),
            Forms\Components\Textarea::make('notlar')->label('Notlar')->rows(3)->maxLength(2000)->columnSpanFull(),
        ];
    }

    /** @return array<int|string, string> */
    private function duzenliFaturaKategorileri(): array
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId === null) {
            return [];
        }

        return Cache::remember('masraf:duzenli-fatura-kategorileri:v1:'.$firmaId, now()->addMinutes(10), fn (): array => MasrafKategorisi::query()
            ->where('firma_id', $firmaId)->where('aktif_mi', true)->where('secilir_mi', true)
            ->whereHas('ustKategori', fn (Builder $query): Builder => $query->where('kod', 'duzenli_faturalar'))
            ->orderBy('sira')->pluck('ad', 'id')->all());
    }

    /** @param array<string, mixed> $data */
    private function tanimKaydet(array $data, ?int $tanimId = null): mixed
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId === null) {
            return $this->uyariGoster('Aktif firma bulunamadı', 'Düzenli fatura tanımı için önce aktif firma seçin.');
        }

        try {
            app(DuzenliFaturaTanimiServisi::class)->kaydet($firmaId, $data, $tanimId);
        } catch (IsKuraliIstisnasi $exception) {
            return $this->uyariGoster('Tanım kaydedilemedi', $exception->getMessage());
        }

        $this->resetTable();
        Notification::make()->title($tanimId === null ? 'Düzenli fatura tanımı eklendi' : 'Düzenli fatura tanımı güncellendi')->success()->send();

        return null;
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
