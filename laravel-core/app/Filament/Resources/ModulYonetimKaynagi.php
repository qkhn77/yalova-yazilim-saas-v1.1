<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ModulYonetimKaynagi\Pages;
use App\Models\Modul;
use App\Models\User;
use App\Support\SaaSemaYardimcisi;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ModulYonetimKaynagi extends Resource
{
    protected static ?string $model = Modul::class;

    private static ?bool $erisimCache = null;

    protected static ?string $slug = 'sistem-moduller';

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static bool $shouldRegisterNavigation = false;

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        return static::getModel()::query()
            ->select([
                'id',
                'ad',
                'kod',
                'aciklama',
                'aktif_mi',
            ])
            ->whereKey($key)
            ->first();
    }

    protected static function sadeceSistemYoneticisi(): bool
    {
        $kullanici = Auth::user();

        return $kullanici instanceof User
            && ((bool) ($kullanici->super_admin_mi ?? false) || (bool) ($kullanici->is_admin ?? false));
    }

    public static function canAccess(): bool
    {
        return self::$erisimCache ??= static::sadeceSistemYoneticisi() && SaaSemaYardimcisi::modullerTablosuVarMi();
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canView(Model $record): bool
    {
        return static::canAccess();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('ad')
                ->label('Ad')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('kod')
                ->label('Kod')
                ->required()
                ->maxLength(100)
                ->unique(Modul::class, 'kod', ignoreRecord: true),
            Forms\Components\Textarea::make('aciklama')
                ->label('Açıklama')
                ->columnSpanFull(),
            Forms\Components\Toggle::make('aktif_mi')
                ->label('Aktif')
                ->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->select([
                    'id',
                    'ad',
                    'kod',
                    'aciklama',
                    'aktif_mi',
                ]))
            ->columns([
                Tables\Columns\TextColumn::make('ad')->label('Ad')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('kod')->label('Kod')->searchable()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('aciklama')->label('Açıklama')->limit(50),
                Tables\Columns\IconColumn::make('aktif_mi')->label('Aktif')->boolean(),
            ])
            ->defaultSort('ad')
            ->actions([
                Tables\Actions\EditAction::make()->label('Düzenle'),
                Tables\Actions\DeleteAction::make()
                    ->label('Sil')
                    ->requiresConfirmation(),
            ])
            ->bulkActions([])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function detayModu(): bool
    {
        return request()->boolean('detay');
    }

    public static function hizliDuzenlemeModu(): bool
    {
        return filled(request()->route('record')) && ! static::detayModu();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ModulListesi::route('/'),
            'create' => Pages\ModulOlustur::route('/create'),
            'edit' => Pages\ModulDuzenle::route('/{record}/edit'),
        ];
    }
}
