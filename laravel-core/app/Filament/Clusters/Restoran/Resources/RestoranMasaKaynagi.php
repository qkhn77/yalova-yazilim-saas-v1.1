<?php

namespace App\Filament\Clusters\Restoran\Resources;

use App\Filament\Clusters\Restoran as RestoranCluster;
use App\Filament\Clusters\Restoran\Kaynaklar\RestoranKaynakErisimi;
use App\Filament\Clusters\Restoran\Resources\RestoranMasaKaynagi\Pages;
use App\Models\Restoran\RestoranMasasi;
use App\Models\Restoran\RestoranSalonu;
use App\Models\Sube;
use App\Services\TenantContextService;
use App\Support\Restoran\RestoranYetkiSablonlari;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class RestoranMasaKaynagi extends Resource
{
    use RestoranKaynakErisimi;

    protected static ?string $model = RestoranMasasi::class;

    protected static ?string $cluster = RestoranCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationLabel = 'Masalar';

    protected static ?string $modelLabel = 'Masa';

    protected static ?string $pluralModelLabel = 'Masalar';

    protected static ?string $slug = 'masalar';

    protected static function goruntuleYetkisi(): string
    {
        return RestoranYetkiSablonlari::MASA_GORUNTULE;
    }

    protected static function olusturYetkisi(): string
    {
        return RestoranYetkiSablonlari::MASA_DUZENLE;
    }

    protected static function guncelleYetkisi(): string
    {
        return RestoranYetkiSablonlari::MASA_DUZENLE;
    }

    protected static function silYetkisi(): string
    {
        return RestoranYetkiSablonlari::MASA_DUZENLE;
    }

    public static function form(Form $form): Form
    {
        if (static::hizliDuzenlemeModu()) {
            return $form->schema([
                Forms\Components\Select::make('durum')
                    ->label('Durum')
                    ->options([
                        RestoranMasasi::DURUM_BOS => 'Boş',
                        RestoranMasasi::DURUM_DOLU => 'Dolu',
                        RestoranMasasi::DURUM_REZERVE => 'Rezerve',
                        RestoranMasasi::DURUM_KAPALI => 'Kapalı',
                    ])
                    ->default(RestoranMasasi::DURUM_BOS)
                    ->required(),
            ]);
        }

        return $form->schema([
            Forms\Components\Hidden::make('firma_id')
                ->default(fn (): ?int => app(TenantContextService::class)->aktifFirmaId())
                ->dehydrated(),
            Forms\Components\Section::make('Masa bilgileri')
                ->schema([
                    Forms\Components\TextInput::make('ad')
                        ->label('Ad')
                        ->required()
                        ->maxLength(191),
                    Forms\Components\TextInput::make('kod')
                        ->label('Kod')
                        ->maxLength(64),
                    Forms\Components\TextInput::make('qr_siparis_kodu')
                        ->label('QR siparis kodu')
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText('Masa QR siparis linki icin kullanilir.'),
                    Forms\Components\Select::make('sube_id')
                        ->label('Şube')
                        ->options(fn (): array => static::subeSecenekleri())
                        ->searchable()
                        ->preload(),
                    Forms\Components\Select::make('salon_id')
                        ->label('Salon')
                        ->options(fn (): array => static::salonSecenekleri())
                        ->searchable()
                        ->preload(),
                    Forms\Components\TextInput::make('kapasite')
                        ->label('Kapasite')
                        ->numeric()
                        ->minValue(0)
                        ->default(0),
                    Forms\Components\Select::make('durum')
                        ->label('Durum')
                        ->options([
                            RestoranMasasi::DURUM_BOS => 'Boş',
                            RestoranMasasi::DURUM_DOLU => 'Dolu',
                            RestoranMasasi::DURUM_REZERVE => 'Rezerve',
                            RestoranMasasi::DURUM_KAPALI => 'Kapalı',
                        ])
                        ->default(RestoranMasasi::DURUM_BOS)
                        ->required(),
                    Forms\Components\TextInput::make('siralama')
                        ->label('Sıralama')
                        ->numeric()
                        ->default(0),
                    Forms\Components\Toggle::make('aktif_mi')
                        ->label('Aktif')
                        ->default(true),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ad')->label('Masa')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('salon.ad')->label('Salon')->sortable(),
                Tables\Columns\TextColumn::make('sube.ad')->label('Şube')->sortable(),
                Tables\Columns\TextColumn::make('qr_siparis_kodu')
                    ->label('QR kod')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->limit(12),
                Tables\Columns\TextColumn::make('kapasite')->label('Kapasite')->sortable(),
                Tables\Columns\TextColumn::make('durum')
                    ->label('Durum')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        RestoranMasasi::DURUM_BOS => 'success',
                        RestoranMasasi::DURUM_DOLU => 'danger',
                        RestoranMasasi::DURUM_REZERVE => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        RestoranMasasi::DURUM_BOS => 'Boş',
                        RestoranMasasi::DURUM_DOLU => 'Dolu',
                        RestoranMasasi::DURUM_REZERVE => 'Rezerve',
                        RestoranMasasi::DURUM_KAPALI => 'Kapalı',
                        default => $state,
                    }),
                Tables\Columns\IconColumn::make('aktif_mi')->label('Aktif')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('durum')
                    ->label('Durum')
                    ->options([
                        RestoranMasasi::DURUM_BOS => 'Boş',
                        RestoranMasasi::DURUM_DOLU => 'Dolu',
                        RestoranMasasi::DURUM_REZERVE => 'Rezerve',
                        RestoranMasasi::DURUM_KAPALI => 'Kapalı',
                    ]),
                Tables\Filters\SelectFilter::make('salon_id')
                    ->label('Salon')
                    ->options(fn (): array => static::salonSecenekleri()),
                Tables\Filters\TernaryFilter::make('aktif_mi')->label('Aktif'),
            ])
            ->actions([
                Tables\Actions\Action::make('qr_kod_yenile')
                    ->label('QR yenile')
                    ->icon('heroicon-o-qr-code')
                    ->requiresConfirmation()
                    ->action(function (RestoranMasasi $record): void {
                        $record->qrSiparisKodunuYenile();

                        Notification::make()
                            ->title('QR siparis kodu yenilendi.')
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
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRestoranMasalari::route('/'),
            'create' => Pages\CreateRestoranMasa::route('/create'),
            'edit' => Pages\EditRestoranMasa::route('/{record}/edit'),
        ];
    }

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        if (static::hizliDuzenlemeModu()) {
            return RestoranMasasi::query()
                ->select(['id', 'durum'])
                ->whereKey($key)
                ->first();
        }

        return parent::resolveRecordRouteBinding($key);
    }

    public static function detayModu(): bool
    {
        return request()->boolean('detay');
    }

    private static function hizliDuzenlemeModu(): bool
    {
        return filled(request()->route('record')) && ! static::detayModu();
    }

    /**
     * @return array<int, string>
     */
    private static function salonSecenekleri(): array
    {
        $firmaId = app(TenantContextService::class)->aktifFirmaId();
        $cacheFirmaAnahtari = $firmaId ?: 'genel';

        return Cache::remember(
            "restoran:masa:salon-secenekleri:v1:{$cacheFirmaAnahtari}",
            now()->addMinutes(5),
            static fn (): array => RestoranSalonu::query()
                ->where('firma_id', $firmaId)
                ->orderBy('ad')
                ->pluck('ad', 'id')
                ->all()
        );
    }

    /**
     * @return array<int, string>
     */
    private static function subeSecenekleri(): array
    {
        $firmaId = app(TenantContextService::class)->aktifFirmaId();
        $cacheFirmaAnahtari = $firmaId ?: 'genel';

        return Cache::remember(
            "restoran:masa:sube-secenekleri:v1:{$cacheFirmaAnahtari}",
            now()->addMinutes(5),
            static fn (): array => Sube::query()
                ->where('firma_id', $firmaId)
                ->orderBy('ad')
                ->pluck('ad', 'id')
                ->all()
        );
    }
}
