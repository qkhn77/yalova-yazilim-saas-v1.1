<?php

namespace App\Muhasebe\Filament\AbstractKaynaklar;

use App\Filament\Clusters\Muhasebe;
use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeFilamentKaynakYetkileri;
use App\Models\Firma;
use App\Services\TenantContextService;
use App\Support\KullaniciRolYardimcisi;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Para birimi / stok kategorisi ile aynı yetki ve sabit/firma form deseninde standart tanım CRUD.
 */
abstract class StandartMuhasebeTanimKaynakResource extends Resource
{
    use MuhasebeFilamentKaynakYetkileri;

    /** @var array<int,string> */
    private static array $firmaSecenekleriCache = [];

    private static ?bool $superAdminMiCache = null;

    protected static ?string $cluster = Muhasebe::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static bool $isScopedToTenant = false;

    protected static function goruntuleYetkisi(): string
    {
        return MuhasebeYetkiSablonlari::TANIM_GORUNTULE;
    }

    protected static function olusturYetkisi(): string
    {
        return MuhasebeYetkiSablonlari::TANIM_GUNCELLE;
    }

    protected static function guncelleYetkisi(): string
    {
        return MuhasebeYetkiSablonlari::TANIM_GUNCELLE;
    }

    protected static function silYetkisi(): string
    {
        return MuhasebeYetkiSablonlari::TANIM_GUNCELLE;
    }

    /**
     * @return array<Forms\Components\Component>
     */
    protected static function tanimFormEkstraOnceKod(): array
    {
        return [];
    }

    /**
     * @return array<Forms\Components\Component>
     */
    protected static function tanimFormEkstraKodSonrasi(): array
    {
        return [];
    }

    public static function canEdit(Model $record): bool
    {
        if (! parent::canEdit($record)) {
            return false;
        }

        if ((bool) $record->getAttribute('is_sabit')) {
            return static::superAdminMi();
        }

        return true;
    }

    public static function canDelete(Model $record): bool
    {
        if (! parent::canDelete($record)) {
            return false;
        }

        if ((bool) $record->getAttribute('is_sabit')) {
            return static::superAdminMi();
        }

        return true;
    }

    public static function form(Form $form): Form
    {
        $ekstraOnceKod = static::tanimFormEkstraOnceKod();
        $ekstraKodSonrasi = static::tanimFormEkstraKodSonrasi();

        if (static::hizliDuzenlemeModu() && $ekstraOnceKod === [] && $ekstraKodSonrasi === []) {
            return $form->schema([
                Forms\Components\TextInput::make('ad')
                    ->label('Ad')
                    ->maxLength(191),
            ]);
        }

        $superAdminMi = static::superAdminMi();

        return $form->schema([
            Forms\Components\Section::make()
                ->schema([
                    Forms\Components\Toggle::make('is_sabit')
                        ->label('Sistem sabit tanımı')
                        ->helperText('Tüm firmalar görür; yalnızca süper yönetici düzenleyebilir/silebilir.')
                        ->visible($superAdminMi)
                        ->live()
                        ->default(false),
                    Forms\Components\Select::make('firma_id')
                        ->label('Firma')
                        ->options(fn (): array => static::firmaSecenekleri())
                        ->searchable()
                        ->preload()
                        ->required(fn (Get $get): bool => $superAdminMi && ! (bool) $get('is_sabit'))
                        ->visible(fn (Get $get): bool => $superAdminMi && ! (bool) $get('is_sabit'))
                        ->default(fn () => app(TenantContextService::class)->aktifFirmaId())
                        ->dehydrated(fn (Get $get): bool => $superAdminMi && ! (bool) $get('is_sabit'))
                        ->helperText(fn () => $superAdminMi ? null : 'Aktif firma oturumuna kaydedilir.'),
                    ...$ekstraOnceKod,
                    Forms\Components\TextInput::make('kod')
                        ->label('Kod')
                        ->required()
                        ->maxLength(64)
                        ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                        ->dehydrateStateUsing(fn (?string $state) => $state ? strtoupper(trim($state)) : $state),
                    Forms\Components\TextInput::make('ad')
                        ->label('Ad')
                        ->maxLength(191),
                    ...$ekstraKodSonrasi,
                    Forms\Components\Toggle::make('aktif_mi')
                        ->label('Aktif')
                        ->default(true),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        $superAdminMi = static::superAdminMi();

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $superAdminMi
                ? $query->with('firma:id,ad')
                : $query)
            ->columns([
                Tables\Columns\TextColumn::make('firma.ad')
                    ->label('Firma')
                    ->placeholder('— (sabit)')
                    ->sortable()
                    ->visible($superAdminMi),
                Tables\Columns\IconColumn::make('is_sabit')
                    ->label('Sabit')
                    ->boolean(),
                ...static::tanimTabloKodOncesiSutunlari(),
                Tables\Columns\TextColumn::make('kod')
                    ->label('Kod')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ad')
                    ->label('Ad')
                    ->searchable()
                    ->placeholder('—'),
                ...static::tanimTabloAdSonrasiSutunlari(),
                Tables\Columns\IconColumn::make('aktif_mi')
                    ->label('Aktif')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Güncellendi')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('kod')
            ->filters([
                Tables\Filters\TernaryFilter::make('aktif_mi')
                    ->label('Durum')
                    ->placeholder('Tümü')
                    ->trueLabel('Aktif')
                    ->falseLabel('Pasif'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->deferLoading()
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        if (
            static::hizliDuzenlemeModu()
            && static::tanimFormEkstraOnceKod() === []
            && static::tanimFormEkstraKodSonrasi() === []
        ) {
            /** @var class-string<Model> $model */
            $model = static::getModel();

            return $model::query()
                ->select(['id', 'firma_id', 'is_sabit', 'ad'])
                ->whereKey($key)
                ->first();
        }

        return parent::resolveRecordRouteBinding($key);
    }

    protected static function hizliDuzenlemeModu(): bool
    {
        $routeName = (string) (request()->route()?->getName() ?? '');

        return str_ends_with($routeName, '.edit') && ! request()->boolean('detay');
    }

    /**
     * @return array<int,string>
     */
    private static function firmaSecenekleri(): array
    {
        if (self::$firmaSecenekleriCache !== []) {
            return self::$firmaSecenekleriCache;
        }

        return self::$firmaSecenekleriCache = Firma::query()
            ->orderBy('ad')
            ->pluck('ad', 'id')
            ->all();
    }

    private static function superAdminMi(): bool
    {
        if (self::$superAdminMiCache !== null) {
            return self::$superAdminMiCache;
        }

        return self::$superAdminMiCache = KullaniciRolYardimcisi::superAdminVeyaIsAdmin(Auth::user());
    }

    /**
     * @return array<Tables\Columns\Column>
     */
    protected static function tanimTabloKodOncesiSutunlari(): array
    {
        return [];
    }

    /**
     * @return array<Tables\Columns\Column>
     */
    protected static function tanimTabloAdSonrasiSutunlari(): array
    {
        return [];
    }
}
