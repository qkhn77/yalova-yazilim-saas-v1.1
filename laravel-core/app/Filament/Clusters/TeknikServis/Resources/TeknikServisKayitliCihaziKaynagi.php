<?php

namespace App\Filament\Clusters\TeknikServis\Resources;

use App\Filament\Clusters\TeknikServis as TeknikServisCluster;
use App\Filament\Clusters\TeknikServis\Kaynaklar\TeknikServisFilamentErisimYardimcisi;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKayitliCihaziKaynagi\Pages;
use App\Models\Muhasebe\Cari;
use App\Models\TeknikServis\TeknikServisCihazTanimi;
use App\Models\TeknikServis\TeknikServisKayitliCihazi;
use App\Models\TeknikServis\TeknikServisMarkaTanimi;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi;
use App\Support\TeknikServisYetkiSablonlari;
use App\Services\TenantContextService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TeknikServisKayitliCihaziKaynagi extends Resource
{
    protected static ?string $model = TeknikServisKayitliCihazi::class;
    protected static ?string $cluster = TeknikServisCluster::class;
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';
    protected static ?string $navigationLabel = 'Kayıtlı cihazlar';
    protected static ?string $modelLabel = 'Kayıtlı cihaz';
    protected static ?string $pluralModelLabel = 'Kayıtlı cihazlar';
    protected static ?string $slug = 'kayitli-cihazlar';
    protected static ?string $navigationGroup = null;
    protected static ?int $navigationSort = 51;

    public static function canViewAny(): bool
    {
        return TeknikServisFilamentErisimYardimcisi::herhangiBirTeknikServisErisimiVarMi([
            TeknikServisYetkiSablonlari::GORUNTULE,
            TeknikServisYetkiSablonlari::GUNCELLE,
        ]);
    }

    public static function canView(Model $record): bool { return static::canViewAny(); }
    public static function canEdit(Model $record): bool
    {
        return TeknikServisFilamentErisimYardimcisi::teknikServisYetkisiVarMi(TeknikServisYetkiSablonlari::GUNCELLE);
    }
    public static function canCreate(): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Hidden::make('firma_id')->default(fn (): int => (int) app(TenantContextService::class)->aktifFirmaId())->dehydrated(),
            Forms\Components\Placeholder::make('cihaz_no')->label('Benzersiz cihaz numarası')->content(fn (?TeknikServisKayitliCihazi $record): string => $record?->cihaz_no ?? 'Kaydedildikten sonra oluşturulur'),
            Forms\Components\Select::make('cari_id')
                ->label('Cari')
                ->options(fn (): array => Cari::query()->orderBy('ad')->pluck('ad', 'id')->all())
                ->searchable()->preload()->required(),
            Forms\Components\Select::make('cihaz_id')
                ->label('Cihaz türü')
                ->options(fn (): array => TeknikServisCihazTanimi::query()->orderBy('ad')->pluck('ad', 'id')->all())
                ->searchable()->preload(),
            Forms\Components\Select::make('marka_id')
                ->label('Marka')
                ->options(fn (): array => TeknikServisMarkaTanimi::query()->orderBy('ad')->pluck('ad', 'id')->all())
                ->searchable()->preload(),
            Forms\Components\TextInput::make('model_no')->label('Model no')->maxLength(128),
            Forms\Components\TextInput::make('seri_no')->label('Seri no')->maxLength(128),
            Forms\Components\TextInput::make('ayirt_edici_bilgi')->label('Ayırt edici bilgi')->maxLength(255),
            Forms\Components\DatePicker::make('garanti_baslangic_tarihi')->label('Garanti başlangıcı'),
            Forms\Components\DatePicker::make('garanti_bitis_tarihi')->label('Garanti bitişi'),
            Forms\Components\TextInput::make('bakim_periyot_ay')->label('Bakım periyodu (ay)')->numeric()->minValue(1)->maxValue(120),
            Forms\Components\DatePicker::make('son_bakim_tarihi')->label('Son bakım tarihi'),
            Forms\Components\Textarea::make('notlar')->label('Notlar')->rows(3)->columnSpanFull(),
            Forms\Components\Toggle::make('aktif_mi')->label('Aktif')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['cari:id,ad', 'cihaz:id,ad', 'marka:id,ad'])->withCount('servisKayitlari'))
            ->columns([
                Tables\Columns\TextColumn::make('cihaz_no')->label('Cihaz no')->state(fn (TeknikServisKayitliCihazi $record): string => $record->cihaz_no)->searchable(false)->sortable(),
                Tables\Columns\TextColumn::make('cari.ad')
                    ->label('Cari')
                    ->searchable()
                    ->sortable()
                    ->width('12rem')
                    ->limit(28)
                    ->tooltip(fn (TeknikServisKayitliCihazi $record): ?string => $record->cari?->ad),
                Tables\Columns\TextColumn::make('cihaz.ad')->label('Cihaz')->placeholder('-'),
                Tables\Columns\TextColumn::make('marka.ad')->label('Marka')->placeholder('-'),
                Tables\Columns\TextColumn::make('model_no')->label('Model')->placeholder('-'),
                Tables\Columns\TextColumn::make('seri_no')->label('Seri no')->placeholder('-')->searchable(),
                Tables\Columns\TextColumn::make('servis_kayitlari_count')->label('Servis')->sortable(),
                Tables\Columns\TextColumn::make('garanti_baslangic_tarihi')->label('Garanti başlangıcı')->date('d.m.Y')->placeholder('-')->sortable(),
                Tables\Columns\TextColumn::make('garanti_bitis_tarihi')->label('Garanti bitişi')->date('d.m.Y')->placeholder('-')->sortable(),
                Tables\Columns\TextColumn::make('garanti_durumu')->label('Garanti durumu')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Devam ediyor' => 'success',
                        'Süresi doldu' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('sonraki_bakim_tarihi')->label('Sonraki bakım')
                    ->state(fn (TeknikServisKayitliCihazi $record): ?string => $record->sonraki_bakim_tarihi?->format('d.m.Y'))
                    ->placeholder('-')
                    ->sortable(false),
                Tables\Columns\TextColumn::make('bakim_durumu')->label('Bakım durumu')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Gecikti' => 'danger',
                        'Yaklaşıyor' => 'warning',
                        'Planlandı' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('aktif_mi')->label('Aktif')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('cari_id')->label('Cari')->relationship('cari', 'ad')->searchable()->preload(),
                Tables\Filters\SelectFilter::make('cihaz_id')->label('Cihaz')->relationship('cihaz', 'ad')->searchable()->preload(),
                Tables\Filters\SelectFilter::make('marka_id')->label('Marka')->relationship('marka', 'ad')->searchable()->preload(),
                Tables\Filters\TernaryFilter::make('aktif_mi')->label('Aktif'),
                Tables\Filters\Filter::make('garanti_durumu')
                    ->label('Garanti durumu')
                    ->form([
                        Forms\Components\Select::make('durum')->options([
                            'aktif' => 'Garanti devam ediyor',
                            'bitmis' => 'Garanti bitmiş',
                            'yok' => 'Garanti tarihi yok',
                        ])->placeholder('Tümü'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['durum'] ?? null, function (Builder $query, string $durum): void {
                            if ($durum === 'aktif') {
                                $query->whereDate('garanti_bitis_tarihi', '>=', now()->toDateString());
                            } elseif ($durum === 'bitmis') {
                                $query->whereNotNull('garanti_bitis_tarihi')->whereDate('garanti_bitis_tarihi', '<', now()->toDateString());
                            } else {
                                $query->whereNull('garanti_bitis_tarihi');
                            }
                        });
                    }),
                Tables\Filters\Filter::make('bakim_durumu')
                    ->label('Bakım durumu')
                    ->form([
                        Forms\Components\Select::make('durum')->options([
                            'planlandi' => 'Planlandı',
                            'yaklasiyor' => '30 gün içinde',
                            'gecikti' => 'Gecikti',
                            'yok' => 'Planlanmamış',
                        ])->placeholder('Tümü'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['durum'] ?? null, function (Builder $query, string $durum): void {
                            if ($durum === 'yok') {
                                $query->where(function (Builder $query): void {
                                    $query->whereNull('son_bakim_tarihi')->orWhereNull('bakim_periyot_ay');
                                });
                            } elseif ($durum === 'gecikti') {
                                $query->whereNotNull('son_bakim_tarihi')->whereNotNull('bakim_periyot_ay')
                                    ->whereRaw('DATE_ADD(son_bakim_tarihi, INTERVAL bakim_periyot_ay MONTH) < ?', [now()->toDateString()]);
                            } elseif ($durum === 'yaklasiyor') {
                                $query->whereNotNull('son_bakim_tarihi')->whereNotNull('bakim_periyot_ay')
                                    ->whereRaw('DATE_ADD(son_bakim_tarihi, INTERVAL bakim_periyot_ay MONTH) BETWEEN ? AND ?', [now()->toDateString(), now()->addDays(30)->toDateString()]);
                            } else {
                                $query->whereNotNull('son_bakim_tarihi')->whereNotNull('bakim_periyot_ay')
                                    ->whereRaw('DATE_ADD(son_bakim_tarihi, INTERVAL bakim_periyot_ay MONTH) > ?', [now()->addDays(30)->toDateString()]);
                            }
                        });
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('detay')
                    ->label('Detay')
                    ->icon('heroicon-o-eye')
                    ->iconButton()
                    ->tooltip('Cihaz detayını gör')
                    ->url(fn (TeknikServisKayitliCihazi $record): string => static::getUrl('view', ['record' => $record])),
                Tables\Actions\EditAction::make()->label('Düzenle')->icon('heroicon-o-pencil-square')->iconButton()->tooltip('Cihazı düzenle'),
                Tables\Actions\Action::make('yeni_servis')
                    ->label('Yeni servis')
                    ->icon('heroicon-o-plus-circle')
                    ->iconButton()
                    ->tooltip('Bu cihaz için yeni servis kaydı aç')
                    ->url(fn (TeknikServisKayitliCihazi $record): string => TeknikServisKaydiKaynagi::getUrl('create_arizali', [
                        'cari_id' => $record->cari_id,
                        'kayitli_cihaz_id' => $record->getKey(),
                    ])),
                Tables\Actions\Action::make('servis_gecmisi')
                    ->label('Servis geçmişi')
                    ->icon('heroicon-o-clock')
                    ->iconButton()
                    ->tooltip('Cihazın servis geçmişini gör')
                    ->url(fn (TeknikServisKayitliCihazi $record): string => TeknikServisKaydiKaynagi::getUrl('index', ['kayitli_cihaz_id' => $record->getKey()])),
                Tables\Actions\Action::make('birlestir')
                    ->label('Birleştir')
                    ->icon('heroicon-o-arrows-pointing-in')
                    ->iconButton()
                    ->tooltip('Bu cihazı başka bir cihaza aktar ve birleştir')
                    ->requiresConfirmation()
                    ->form(fn (TeknikServisKayitliCihazi $record): array => [
                        Forms\Components\Select::make('hedef_id')
                            ->label('Hedef cihaz')
                            ->options(fn (): array => TeknikServisKayitliCihazi::query()
                                ->where('id', '<>', $record->getKey())
                                ->where('cari_id', $record->cari_id)
                                ->orderBy('id')
                                ->get()
                                ->mapWithKeys(fn (TeknikServisKayitliCihazi $hedef): array => [$hedef->getKey() => $hedef->cihaz_no.' — '.($hedef->model_no ?: $hedef->seri_no ?: 'bilgi yok')])
                                ->all())
                            ->required()
                            ->searchable(),
                    ])
                    ->action(function (TeknikServisKayitliCihazi $record, array $data): void {
                        $hedef = TeknikServisKayitliCihazi::query()->findOrFail((int) $data['hedef_id']);
                        DB::transaction(function () use ($record, $hedef): void {
                            $record->servisKayitlari()->update(['kayitli_cihaz_id' => $hedef->getKey()]);
                            $record->forceDelete();
                        });
                    })
                    ->successNotificationTitle('Cihaz kayıtları birleştirildi.'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('aktif_yap')
                        ->label('Aktif yap')->icon('heroicon-o-check-circle')->color('success')
                        ->action(fn ($records) => $records->each->update(['aktif_mi' => true]))
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle('Seçilen cihazlar aktif yapıldı.'),
                    Tables\Actions\BulkAction::make('pasif_yap')
                        ->label('Pasif yap')->icon('heroicon-o-x-circle')->color('gray')
                        ->action(fn ($records) => $records->each->update(['aktif_mi' => false]))
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle('Seçilen cihazlar pasif yapıldı.'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeknikServisKayitliCihazlari::route('/'),
            'view' => Pages\ViewTeknikServisKayitliCihazi::route('/{record}'),
            'edit' => Pages\EditTeknikServisKayitliCihazi::route('/{record}/duzenle'),
        ];
    }
}
