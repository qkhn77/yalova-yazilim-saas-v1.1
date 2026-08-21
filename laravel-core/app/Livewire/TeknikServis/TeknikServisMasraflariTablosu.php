<?php

namespace App\Livewire\TeknikServis;

use App\Filament\Clusters\MasrafTakip\Kaynaklar\MasrafTakipFilamentErisimYardimcisi;
use App\Models\Muhasebe\Masraf;
use App\Models\Muhasebe\MasrafKategorisi;
use App\Models\Proje\IsletmeProjesi;
use App\Models\TeknikServis\TeknikServisKaydi;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Servisler\MasrafKayitServisi;
use App\Support\MasrafTakipYetkiSablonlari;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Livewire\Component;

class TeknikServisMasraflariTablosu extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public int $recordId = 0;

    public int $firmaId = 0;

    public function mount(int $recordId): void
    {
        $this->recordId = $recordId;
        $this->firmaId = (int) TeknikServisKaydi::query()
            ->whereKey($recordId)
            ->value('firma_id');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->masrafSorgusu())
            ->deferLoading()
            ->heading('Masraf Kayıtları')
            ->description('Bu servis kaydına bağlı masraflar. Masraf kaydı silinmez; iptal edilerek raporlardan çıkarılır.')
            ->defaultSort('tarih', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('tarih')
                    ->label('Tarih')
                    ->date('d.m.Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('kategori.ad')
                    ->label('Masraf türü')
                    ->badge()
                    ->wrap(),
                Tables\Columns\TextColumn::make('isletmeProjesi.ad')
                    ->label('Proje')
                    ->placeholder('—')
                    ->wrap(),
                Tables\Columns\TextColumn::make('tutar')
                    ->label('Tutar')
                    ->formatStateUsing(fn ($state, Masraf $record): string => number_format((float) $state, 2, ',', '.').' '.strtoupper((string) ($record->para_birimi ?: 'TRY')))
                    ->sortable(),
                Tables\Columns\TextColumn::make('durum')
                    ->label('Durum')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === Masraf::DURUM_IPTAL ? 'İptal' : 'Aktif')
                    ->color(fn (?string $state): string => $state === Masraf::DURUM_IPTAL ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('aciklama')
                    ->label('Açıklama')
                    ->limit(45)
                    ->tooltip(fn (Masraf $record): ?string => $record->aciklama)
                    ->wrap(),
                Tables\Columns\TextColumn::make('belge_adi')
                    ->label('Belge')
                    ->placeholder('—')
                    ->url(fn (Masraf $record): ?string => $record->belge_yolu ? route('masraf.belge', ['masraf' => $record->getKey()]) : null, shouldOpenInNewTab: true)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\Action::make('duzenle')
                    ->label('Aç / Düzenle')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->visible(fn (Masraf $record): bool => $record->durum === Masraf::DURUM_AKTIF && $this->guncellemeYetkisiVarMi())
                    ->modalHeading('Masraf kaydını düzenle')
                    ->modalWidth('2xl')
                    ->form(fn (): array => $this->duzenlemeFormu())
                    ->fillForm(fn (Masraf $record): array => [
                        'tarih' => optional($record->tarih)->toDateString(),
                        'masraf_kategorisi_id' => $record->masraf_kategorisi_id,
                        'isletme_proje_id' => $record->isletme_proje_id,
                        'aciklama' => $record->aciklama,
                        'notlar' => $record->notlar,
                        'belge_yolu' => $record->belge_yolu,
                        'belge_adi' => $record->belge_adi,
                    ])
                    ->action(function (Masraf $record, array $data): void {
                        try {
                            if (! $this->guncellemeYetkisiVarMi()) {
                                throw new IsKuraliIstisnasi('Masraf kaydını düzenleme yetkiniz bulunmuyor.');
                            }

                            app(MasrafKayitServisi::class)->guncelle($this->firmaId, (int) $record->getKey(), $data);
                            $this->resetTable();
                            $this->dispatch('servis-tahsilat-guncellendi');
                            Notification::make()->title('Masraf güncellendi')->success()->send();
                        } catch (\Throwable $exception) {
                            Notification::make()->title('Masraf güncellenemedi')->body($exception->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('iptal')
                    ->label('İptal et')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Masraf $record): bool => $record->durum === Masraf::DURUM_AKTIF && $this->silmeYetkisiVarMi())
                    ->requiresConfirmation()
                    ->modalHeading('Masraf kaydını iptal et')
                    ->modalDescription('Kayıt silinmez; iptal edilerek masraf raporlarından çıkarılır.')
                    ->form([
                        Forms\Components\Textarea::make('neden')
                            ->label('İptal nedeni')
                            ->maxLength(2000),
                    ])
                    ->action(function (Masraf $record, array $data): void {
                        try {
                            if (! $this->silmeYetkisiVarMi()) {
                                throw new IsKuraliIstisnasi('Masraf kaydını iptal etme yetkiniz bulunmuyor.');
                            }

                            app(MasrafKayitServisi::class)->iptalEt(
                                $this->firmaId,
                                (int) $record->getKey(),
                                auth()->id() ? (int) auth()->id() : null,
                                $data['neden'] ?? null,
                            );
                            $this->resetTable();
                            $this->dispatch('servis-tahsilat-guncellendi');
                            Notification::make()->title('Masraf iptal edildi')->success()->send();
                        } catch (\Throwable $exception) {
                            Notification::make()->title('Masraf iptal edilemedi')->body($exception->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    #[On('servis-tahsilat-guncellendi')]
    public function yenile(): void
    {
        $this->resetTable();
    }

    /** @return Builder<Masraf> */
    private function masrafSorgusu(): Builder
    {
        return Masraf::query()
            ->where('firma_id', $this->firmaId)
            ->where('kaynak_turu', 'teknik_servis')
            ->where('kaynak_id', $this->recordId)
            ->with(['kategori:id,ad', 'isletmeProjesi:id,ad']);
    }

    /** @return array<int, Forms\Components\Component> */
    private function duzenlemeFormu(): array
    {
        return [
            Forms\Components\DatePicker::make('tarih')
                ->label('Masraf tarihi')
                ->required()
                ->native(false),
            Forms\Components\Select::make('masraf_kategorisi_id')
                ->label('Masraf türü')
                ->options(fn (): array => $this->kategoriSecenekleri())
                ->required()
                ->searchable()
                ->native(false),
            Forms\Components\Select::make('isletme_proje_id')
                ->label('İşletme projesi')
                ->searchable()
                ->options(fn (): array => $this->projeSecenekleri())
                ->getSearchResultsUsing(fn (string $search): array => $this->projeSecenekleri($search))
                ->getOptionLabelUsing(fn ($value): ?string => $this->projeEtiketi($value))
                ->native(false),
            Forms\Components\TextInput::make('aciklama')
                ->label('Kısa açıklama')
                ->required()
                ->maxLength(191),
            Forms\Components\Textarea::make('notlar')
                ->label('Not')
                ->maxLength(2000),
            Forms\Components\FileUpload::make('belge_yolu')
                ->label('Belge / fiş / fatura')
                ->helperText('PDF, JPG veya PNG; en fazla 10 MB.')
                ->disk('public')
                ->directory('masraflar/'.$this->firmaId)
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                ->maxSize(10240)
                ->storeFileNamesIn('belge_adi'),
        ];
    }

    /** @return array<int|string, string> */
    private function kategoriSecenekleri(): array
    {
        $kategoriler = MasrafKategorisi::query()
            ->where('firma_id', $this->firmaId)
            ->aktif()
            ->where('secilir_mi', true)
            ->orderBy('sira')
            ->orderBy('ad')
            ->get(['id', 'ad', 'ust_kategori_id']);

        $harita = $kategoriler->keyBy('id');

        return $kategoriler->mapWithKeys(function (MasrafKategorisi $kategori) use ($harita): array {
            $parcalar = [$kategori->ad];
            $ust = $kategori->ust_kategori_id;
            $guard = 0;

            while ($ust && $guard++ < 12) {
                $ustKategori = $harita->get($ust);
                if (! $ustKategori) {
                    break;
                }

                array_unshift($parcalar, $ustKategori->ad);
                $ust = $ustKategori->ust_kategori_id;
            }

            return [(string) $kategori->getKey() => implode(' / ', $parcalar)];
        })->all();
    }

    /** @return array<int|string, string> */
    private function projeSecenekleri(string $arama = ''): array
    {
        $arama = trim($arama);

        return IsletmeProjesi::query()
            ->where('firma_id', $this->firmaId)
            ->secilebilir()
            ->when($arama !== '', fn (Builder $query): Builder => $query->where(function (Builder $inner) use ($arama): void {
                $inner->where('kod', 'like', '%'.$arama.'%')
                    ->orWhere('ad', 'like', '%'.$arama.'%');
            }))
            ->orderBy('ad')
            ->limit(50)
            ->get(['id', 'kod', 'ad'])
            ->mapWithKeys(fn (IsletmeProjesi $proje): array => [$proje->id => $proje->ad])
            ->all();
    }

    private function projeEtiketi(mixed $value): ?string
    {
        $id = (int) $value;
        if ($id < 1) {
            return null;
        }

        return IsletmeProjesi::query()
            ->where('firma_id', $this->firmaId)
            ->whereKey($id)
            ->value('ad');
    }

    private function guncellemeYetkisiVarMi(): bool
    {
        return MasrafTakipFilamentErisimYardimcisi::masrafTakipYetkisiVarMi(MasrafTakipYetkiSablonlari::GUNCELLE);
    }

    private function silmeYetkisiVarMi(): bool
    {
        return MasrafTakipFilamentErisimYardimcisi::masrafTakipYetkisiVarMi(MasrafTakipYetkiSablonlari::SIL);
    }

    public function render()
    {
        return view('livewire.teknik-servis.teknik-servis-masraflari-tablosu');
    }
}
