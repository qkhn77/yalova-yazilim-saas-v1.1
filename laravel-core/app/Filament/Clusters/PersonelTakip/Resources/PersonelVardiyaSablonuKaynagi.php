<?php

namespace App\Filament\Clusters\PersonelTakip\Resources;

use App\Filament\Clusters\PersonelTakip as PersonelTakipCluster;
use App\Filament\Clusters\PersonelTakip\Kaynaklar\PersonelTakipKaynakErisimi;
use App\Filament\Clusters\PersonelTakip\Resources\PersonelVardiyaSablonuKaynagi\Pages;
use App\Models\Personel\Personel;
use App\Models\Personel\PersonelVardiyaSablonu;
use App\Models\Sube;
use App\Services\PersonelTakip\PersonelVardiyaPlanlamaServisi;
use App\Services\TenantContextService;
use App\Support\PersonelTakip\PersonelTakipYetkiSablonlari;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PersonelVardiyaSablonuKaynagi extends Resource
{
    use PersonelTakipKaynakErisimi;

    /** @var array<int,string> */
    private static array $subeEtiketCache = [];

    protected static ?string $model = PersonelVardiyaSablonu::class;

    protected static ?string $cluster = PersonelTakipCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Vardiya Şablonları';

    protected static ?string $modelLabel = 'Vardiya şablonu';

    protected static ?string $pluralModelLabel = 'Vardiya şablonları';

    protected static ?string $slug = 'tanimlar/vardiya-sablonlari';

    protected static function goruntuleYetkisi(): string
    {
        return PersonelTakipYetkiSablonlari::TANIM_GORUNTULE;
    }

    protected static function olusturYetkisi(): string
    {
        return PersonelTakipYetkiSablonlari::TANIM_GUNCELLE;
    }

    protected static function guncelleYetkisi(): string
    {
        return PersonelTakipYetkiSablonlari::TANIM_GUNCELLE;
    }

    protected static function silYetkisi(): string
    {
        return PersonelTakipYetkiSablonlari::TANIM_GUNCELLE;
    }

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        $query = PersonelVardiyaSablonu::query();

        if (static::hizliDuzenlemeModu()) {
            return $query
                ->select([
                    'id',
                    'aktif_mi',
                ])
                ->whereKey($key)
                ->first();
        }

        $record = $query
            ->select([
                'id',
                'firma_id',
                'sube_id',
                'ad',
                'baslangic_saati',
                'bitis_saati',
                'mola_dakika',
                'renk',
                'aktif_mi',
            ])
            ->with('sube:id,ad')
            ->whereKey($key)
            ->first();

        if ($record?->sube) {
            self::$subeEtiketCache[(int) $record->sube->id] = (string) $record->sube->ad;
        }

        return $record;
    }

    public static function form(Form $form): Form
    {
        if (static::hizliDuzenlemeModu()) {
            return $form->schema([
                Forms\Components\Toggle::make('aktif_mi')
                    ->label('Aktif')
                    ->default(true),
            ]);
        }

        return $form->schema([
            Forms\Components\Hidden::make('firma_id')
                ->default(fn (): ?int => app(TenantContextService::class)->aktifFirmaId())
                ->dehydrated(),
            Forms\Components\Section::make('Vardiya şablonu')
                ->schema([
                    Forms\Components\Select::make('sube_id')
                        ->label('Şube')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => static::subeAramaSonuclari($search))
                        ->getOptionLabelUsing(fn ($value): ?string => static::subeSecimEtiketi((int) $value)),
                    Forms\Components\TextInput::make('ad')
                        ->label('Ad')
                        ->required()
                        ->maxLength(191),
                    Forms\Components\TimePicker::make('baslangic_saati')
                        ->label('Başlangıç')
                        ->seconds(false)
                        ->required(),
                    Forms\Components\TimePicker::make('bitis_saati')
                        ->label('Bitiş')
                        ->seconds(false)
                        ->required(),
                    Forms\Components\TextInput::make('mola_dakika')
                        ->label('Mola (dk)')
                        ->numeric()
                        ->default(0)
                        ->required(),
                    Forms\Components\ColorPicker::make('renk')
                        ->label('Renk')
                        ->visible(fn (string $operation): bool => $operation !== 'create'),
                    Forms\Components\Toggle::make('aktif_mi')
                        ->label('Aktif')
                        ->default(true),
                ])
                ->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->select([
                    'id',
                    'firma_id',
                    'sube_id',
                    'ad',
                    'baslangic_saati',
                    'bitis_saati',
                    'mola_dakika',
                    'renk',
                    'aktif_mi',
                ])
                ->with(['sube:id,ad']))
            ->defaultSort('ad')
            ->columns([
                Tables\Columns\TextColumn::make('ad')->label('Ad')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('sube.ad')->label('Şube')->sortable(),
                Tables\Columns\TextColumn::make('baslangic_saati')->label('Başlangıç'),
                Tables\Columns\TextColumn::make('bitis_saati')->label('Bitiş'),
                Tables\Columns\TextColumn::make('mola_dakika')->label('Mola')->suffix(' dk')->sortable(),
                Tables\Columns\ColorColumn::make('renk')->label('Renk'),
                Tables\Columns\IconColumn::make('aktif_mi')->label('Aktif')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('aktif_mi')->label('Aktif'),
                Tables\Filters\SelectFilter::make('sube_id')
                    ->label('Şube')
                    ->options(fn (): array => Sube::query()->orderBy('ad')->pluck('ad', 'id')->all()),
            ])
            ->actions([
                Tables\Actions\Action::make('toplu_planla')
                    ->label('Toplu planla')
                    ->icon('heroicon-o-calendar-days')
                    ->color('warning')
                    ->visible(fn (): bool => static::canCreate())
                    ->fillForm(fn (): array => [
                        'baslangic_tarihi' => now()->startOfWeek()->toDateString(),
                        'bitis_tarihi' => now()->endOfWeek()->toDateString(),
                        'gunler' => [1, 2, 3, 4, 5],
                    ])
                    ->form([
                        Forms\Components\Select::make('personel_ids')
                            ->label('Personeller')
                            ->multiple()
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => static::personelAramaSonuclari($search))
                            ->getOptionLabelsUsing(fn (array $values): array => static::personelSecimEtiketleri($values))
                            ->required(),
                        Forms\Components\DatePicker::make('baslangic_tarihi')
                            ->label('Baslangic tarihi')
                            ->required(),
                        Forms\Components\DatePicker::make('bitis_tarihi')
                            ->label('Bitis tarihi')
                            ->required(),
                        Forms\Components\CheckboxList::make('gunler')
                            ->label('Gunler')
                            ->options([
                                1 => 'Pazartesi',
                                2 => 'Sali',
                                3 => 'Carsamba',
                                4 => 'Persembe',
                                5 => 'Cuma',
                                6 => 'Cumartesi',
                                7 => 'Pazar',
                            ])
                            ->columns(4),
                    ])
                    ->action(function (PersonelVardiyaSablonu $record, array $data): void {
                        $sonuc = app(PersonelVardiyaPlanlamaServisi::class)->sablondanAralikOlustur(
                            firmaId: (int) $record->firma_id,
                            sablonId: (int) $record->id,
                            personelIds: (array) ($data['personel_ids'] ?? []),
                            baslangicTarihi: (string) ($data['baslangic_tarihi'] ?? now()->toDateString()),
                            bitisTarihi: (string) ($data['bitis_tarihi'] ?? now()->toDateString()),
                            gunler: (array) ($data['gunler'] ?? []),
                            subeId: $record->sube_id ? (int) $record->sube_id : null,
                        );

                        Notification::make()
                            ->title($sonuc['olusan'].' vardiya olusturuldu')
                            ->body($sonuc['atlanan'] > 0 ? $sonuc['atlanan'].' kayit atlandi.' : null)
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPersonelVardiyaSablonlari::route('/'),
            'create' => Pages\CreatePersonelVardiyaSablonu::route('/create'),
            'edit' => Pages\EditPersonelVardiyaSablonu::route('/{record}/edit'),
        ];
    }

    public static function detayModu(): bool
    {
        return request()->boolean('detay');
    }

    public static function hizliDuzenlemeModu(): bool
    {
        return filled(request()->route('record')) && ! static::detayModu();
    }

    private static function personelAramaSonuclari(string $search): array
    {
        return Personel::query()
            ->where('durum', Personel::DURUM_AKTIF)
            ->when($search !== '', fn (Builder $query): Builder => $query->where('ad_soyad', 'like', "%{$search}%"))
            ->orderBy('ad_soyad')
            ->limit(50)
            ->pluck('ad_soyad', 'id')
            ->all();
    }

    private static function subeAramaSonuclari(string $search): array
    {
        return Sube::query()
            ->when($search !== '', fn (Builder $query): Builder => $query
                ->where(function (Builder $q) use ($search): void {
                    $q->where('ad', 'like', "%{$search}%")
                        ->orWhere('kod', 'like', "%{$search}%");
                }))
            ->orderBy('ad')
            ->limit(50)
            ->pluck('ad', 'id')
            ->all();
    }

    private static function subeSecimEtiketi(int $subeId): ?string
    {
        if ($subeId < 1) {
            return null;
        }

        return self::$subeEtiketCache[$subeId] ??= Sube::query()
            ->whereKey($subeId)
            ->value('ad');
    }

    private static function personelSecimEtiketleri(array $values): array
    {
        return Personel::query()
            ->whereIn('id', $values)
            ->pluck('ad_soyad', 'id')
            ->all();
    }
}
