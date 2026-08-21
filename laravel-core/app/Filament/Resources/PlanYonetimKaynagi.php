<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlanYonetimKaynagi\Pages;
use App\Filament\Resources\PlanYonetimKaynagi\RelationManagers;
use App\Models\Plan;
use App\Models\User;
use App\Support\SaaSemaYardimcisi;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PlanYonetimKaynagi extends Resource
{
    private static ?bool $erisimCache = null;

    protected static ?string $model = Plan::class;

    protected static ?string $slug = 'sistem-planlar';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static bool $shouldRegisterNavigation = false;

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        return static::getModel()::query()
            ->select([
                'id',
                'ad',
                'kod',
                'ucret',
                'sure_gun',
                'aktif_mi',
            ])
            ->whereKey($key)
            ->first();
    }

    public static function getNavigationLabel(): string
    {
        return 'Planlar';
    }

    public static function getModelLabel(): string
    {
        return 'Plan';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Planlar';
    }

    protected static function sadeceSistemYoneticisi(): bool
    {
        $kullanici = auth()->user();

        return $kullanici instanceof User
            && ((bool) ($kullanici->super_admin_mi ?? false) || (bool) ($kullanici->is_admin ?? false));
    }

    public static function canAccess(): bool
    {
        return self::$erisimCache ??= static::sadeceSistemYoneticisi() && SaaSemaYardimcisi::planlarTablosuVarMi();
    }

    public static function canViewAny(): bool
    {
        return static::sadeceSistemYoneticisi() && SaaSemaYardimcisi::planlarTablosuVarMi();
    }

    public static function canView(Model $kayit): bool
    {
        return static::sadeceSistemYoneticisi() && SaaSemaYardimcisi::planlarTablosuVarMi();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Plan')
                ->schema([
                    Forms\Components\TextInput::make('ad')
                        ->label('Plan adı')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('kod')
                        ->label('Kod')
                        ->required()
                        ->maxLength(80)
                        ->unique(Plan::class, 'kod', ignoreRecord: true)
                        ->helperText('Benzersiz plan kodu (ör. temel, pro).'),
                    Forms\Components\TextInput::make('ucret')
                        ->label('Ücret')
                        ->numeric()
                        ->prefix('₺')
                        ->default(0),
                    Forms\Components\TextInput::make('sure_gun')
                        ->label('Süre (gün)')
                        ->numeric()
                        ->required()
                        ->default(30)
                        ->minValue(1),
                    Forms\Components\Toggle::make('aktif_mi')
                        ->label('Aktif')
                        ->default(true),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->select([
                'id',
                'ad',
                'kod',
                'ucret',
                'sure_gun',
                'aktif_mi',
                'updated_at',
            ]))
            ->columns([
                Tables\Columns\TextColumn::make('ad')->label('Plan adı')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('kod')->label('Kod')->searchable()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('ucret')->label('Ücret')->money('TRY')->sortable(),
                Tables\Columns\TextColumn::make('sure_gun')->label('Süre (gün)')->sortable(),
                Tables\Columns\IconColumn::make('aktif_mi')->label('Aktif')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->label('Güncellendi')->dateTime('d.m.Y H:i'),
            ])
            ->defaultSort('ad')
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    public static function getRelations(): array
    {
        if (! SaaSemaYardimcisi::planlarTablosuVarMi()) {
            return [];
        }

        if (! SaaSemaYardimcisi::planModulleriTablosuVarMi() || ! SaaSemaYardimcisi::modullerTablosuVarMi()) {
            return [];
        }

        return [
            RelationManagers\PlanModulleriIliskisi::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\PlanListesi::route('/'),
            'create' => Pages\PlanOlustur::route('/create'),
            'edit' => Pages\PlanDuzenle::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return static::sadeceSistemYoneticisi() && SaaSemaYardimcisi::planlarTablosuVarMi();
    }

    public static function canEdit(Model $kayit): bool
    {
        return static::sadeceSistemYoneticisi() && SaaSemaYardimcisi::planlarTablosuVarMi();
    }

    public static function canDelete(Model $kayit): bool
    {
        return static::sadeceSistemYoneticisi() && SaaSemaYardimcisi::planlarTablosuVarMi();
    }

    public static function detayModu(): bool
    {
        return request()->boolean('detay');
    }
}
