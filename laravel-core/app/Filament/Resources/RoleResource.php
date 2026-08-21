<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use App\Filament\Resources\RoleResource\RelationManagers;
use App\Models\Rol;
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

class RoleResource extends Resource
{
    protected static ?string $model = Rol::class;

    private static ?bool $erisimCache = null;

    protected static ?string $slug = 'sistem-roller';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Sistem Yönetimi';

    protected static ?int $navigationSort = 21;

    protected static ?string $navigationLabel = 'Roller';

    protected static ?string $modelLabel = 'Rol';

    protected static ?string $pluralModelLabel = 'Roller';

    protected static ?string $recordTitleAttribute = 'ad';

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        return static::getModel()::query()
            ->select([
                'id',
                'ad',
                'kod',
                'aciklama',
                'sistem_rolu_mu',
            ])
            ->whereKey($key)
            ->first();
    }

    protected static function sadeceSistemYoneticisi(): bool
    {
        $record = Auth::user();

        return $record instanceof User
            && ((bool) ($record->super_admin_mi ?? false) || (bool) ($record->is_admin ?? false));
    }

    public static function canAccess(): bool
    {
        return self::$erisimCache ??= static::sadeceSistemYoneticisi()
            && SaaSemaYardimcisi::rollerTablosuVarMi();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Grup bilgileri')->schema([
                    Forms\Components\TextInput::make('ad')
                        ->label('Ad')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('kod')
                        ->label('Kod')
                        ->required()
                        ->maxLength(100)
                        ->unique(Rol::class, 'kod', ignoreRecord: true),
                    Forms\Components\Textarea::make('aciklama')
                        ->label('Açıklama')
                        ->columnSpanFull(),
                    Forms\Components\Toggle::make('sistem_rolu_mu')
                        ->label('Sistem rolü')
                        ->default(true),
                ]),
            ]);
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
                    'sistem_rolu_mu',
                ]))
            ->columns([
                Tables\Columns\TextColumn::make('ad')->label('Ad')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('kod')->label('Kod')->searchable()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('aciklama')->label('Açıklama')->limit(40),
                Tables\Columns\IconColumn::make('sistem_rolu_mu')->label('Sistem')->boolean(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Görüntüle'),
                Tables\Actions\EditAction::make()->label('Düzenle'),
                Tables\Actions\DeleteAction::make()
                    ->label('Sil')
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    public static function getRelations(): array
    {
        if (! SaaSemaYardimcisi::yetkilerTablosuVarMi() || ! SaaSemaYardimcisi::rolYetkileriTablosuVarMi()) {
            return [];
        }

        return [
            RelationManagers\YetkilerIliskiYoneticisi::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'view' => Pages\ViewRole::route('/{record}'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return static::canAccess();
    }

    public static function canView(Model $record): bool
    {
        return static::canAccess();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canAccess();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canAccess();
    }

    public static function detayModu(): bool
    {
        return request()->boolean('detay');
    }
}
