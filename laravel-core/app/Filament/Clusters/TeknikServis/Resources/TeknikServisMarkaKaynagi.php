<?php

namespace App\Filament\Clusters\TeknikServis\Resources;

use App\Filament\Clusters\TeknikServis as TeknikServisCluster;
use App\Filament\Clusters\TeknikServis\Resources\Concerns\TeknikServisTanimKaynakErisimi;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisMarkaKaynagi\Pages;
use App\Models\TeknikServis\TeknikServisMarkaTanimi;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TeknikServisMarkaKaynagi extends Resource
{
    use TeknikServisTanimKaynakErisimi;

    protected static ?string $model = TeknikServisMarkaTanimi::class;

    protected static ?string $cluster = TeknikServisCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-bookmark';

    protected static ?string $navigationLabel = 'Marka tanımları';

    protected static ?string $modelLabel = 'Marka';

    protected static ?string $pluralModelLabel = 'Marka tanımları';

    protected static ?string $slug = 'tanimlar/markalar';

    protected static ?string $navigationGroup = 'Tanımlar';

    protected static ?int $navigationSort = 32;

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        if (static::hizliDuzenlemeModu()) {
            return static::getModel()::query()
                ->select([
                    'id',
                    'firma_id',
                    'aktif',
                ])
                ->whereKey($key)
                ->first();
        }

        return static::getModel()::query()
            ->select([
                'id',
                'firma_id',
                'ad',
                'kod',
                'aktif',
                'siralama',
                'varsayilan_mi',
            ])
            ->whereKey($key)
            ->first();
    }

    public static function form(Form $form): Form
    {
        if (static::hizliDuzenlemeModu()) {
            return $form->schema([
                Forms\Components\Toggle::make('aktif')->label('Aktif')->default(true),
            ]);
        }

        return $form->schema([
            Forms\Components\Hidden::make('firma_id')
                ->default(null)
                ->dehydrated(),
            Forms\Components\TextInput::make('ad')->label('Ad')->required()->maxLength(191),
            Forms\Components\TextInput::make('kod')->label('Kod')->maxLength(64),
            Forms\Components\Toggle::make('aktif')->label('Aktif')->default(true),
            Forms\Components\TextInput::make('siralama')->label('Sıralama')->numeric()->default(0),
            Forms\Components\Toggle::make('varsayilan_mi')->label('Varsayılan')->default(false),
        ]);
    }

    public static function table(Table $tablo): Table
    {
        return $tablo
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->select([
                    'id',
                    'firma_id',
                    'ad',
                    'kod',
                    'aktif',
                ]))
            ->columns([
                Tables\Columns\TextColumn::make('ad')->label('Ad')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('kod')->label('Kod')->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('aktif')->label('Aktif')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->using(fn (Model $record): bool => static::silmeIslemi($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    public static function silmeIslemi(Model $record, ?Model $hedef = null): bool
    {
        $bagli = (int) $record->teknikServisKayitlari()->count();
        if ($bagli > 0 && ! $hedef) {
            Notification::make()->title('Hedef tanım seçilmelidir')->body('Bağlı servis kayıtlarını aktarmak için hedef marka seçin.')->danger()->send();

            return false;
        }

        return (bool) DB::transaction(function () use ($record, $hedef): bool {
            if ($hedef) {
                $record->teknikServisKayitlari()->update(['marka_id' => $hedef->getKey()]);
            }

            return (bool) $record->delete();
        });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeknikServisMarkalari::route('/'),
            'create' => Pages\CreateTeknikServisMarkasi::route('/create'),
            'edit' => Pages\EditTeknikServisMarkasi::route('/{record}/edit'),
        ];
    }

    public static function detayModu(): bool
    {
        return request()->boolean('detay');
    }

    public static function hizliDuzenlemeModu(): bool
    {
        $routeName = (string) (request()->route()?->getName() ?? '');

        return str_ends_with($routeName, '.edit') && ! static::detayModu();
    }
}
