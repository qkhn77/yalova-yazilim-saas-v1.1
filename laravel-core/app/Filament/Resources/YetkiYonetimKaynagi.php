<?php

namespace App\Filament\Resources;

use App\Filament\Resources\YetkiYonetimKaynagi\Pages;
use App\Models\Modul;
use App\Models\User;
use App\Models\Yetki;
use App\Support\SaaSemaYardimcisi;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class YetkiYonetimKaynagi extends Resource
{
    private static ?bool $erisimCache = null;

    protected static ?string $model = Yetki::class;

    protected static ?string $slug = 'sistem-yetkiler';

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static bool $shouldRegisterNavigation = false;

    protected static function sadeceSistemYoneticisi(): bool
    {
        $kullanici = Auth::user();

        return $kullanici instanceof User
            && ((bool) ($kullanici->super_admin_mi ?? false) || (bool) ($kullanici->is_admin ?? false));
    }

    public static function canAccess(): bool
    {
        return self::$erisimCache ??= static::sadeceSistemYoneticisi()
            && SaaSemaYardimcisi::yetkilerTablosuVarMi()
            && SaaSemaYardimcisi::modullerTablosuVarMi();
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
                ->maxLength(120)
                ->unique(Yetki::class, 'kod', ignoreRecord: true),
            Forms\Components\Select::make('modul_kodu')
                ->label('Modül')
                ->options(fn (): array => Modul::query()->orderBy('ad')->pluck('ad', 'kod')->all())
                ->required()
                ->searchable()
                ->preload(),
            Forms\Components\TextInput::make('eylem')
                ->label('Eylem')
                ->maxLength(60),
        ])->columns(2);
    }

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        return parent::resolveRecordRouteBinding($key);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->select([
                    'id',
                    'ad',
                    'kod',
                    'modul_kodu',
                    'eylem',
                ]))
            ->columns([
                Tables\Columns\TextColumn::make('ad')->label('Ad')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('kod')->label('Kod')->searchable()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('modul_kodu')->label('Modül')->badge()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('eylem')->label('Eylem')->sortable(),
            ])
            ->defaultSort('modul_kodu')
            ->filters([
                Tables\Filters\SelectFilter::make('modul_kodu')
                    ->label('Modül')
                    ->options(fn (): array => Modul::query()->orderBy('ad')->pluck('ad', 'kod')->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $query): Builder => $query->where('modul_kodu', (string) $data['value'])
                    )),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Düzenle'),
            ])
            ->bulkActions([])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\YetkiListesi::route('/'),
            'create' => Pages\YetkiOlustur::route('/create'),
            'edit' => Pages\YetkiDuzenle::route('/{record}/edit'),
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

}
