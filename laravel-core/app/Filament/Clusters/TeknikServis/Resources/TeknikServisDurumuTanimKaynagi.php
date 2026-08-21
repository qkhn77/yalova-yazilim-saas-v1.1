<?php

namespace App\Filament\Clusters\TeknikServis\Resources;

use App\Filament\Clusters\TeknikServis as TeknikServisCluster;
use App\Filament\Clusters\TeknikServis\Resources\Concerns\TeknikServisTanimKaynakErisimi;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisDurumuTanimKaynagi\Pages;
use App\Models\TeknikServis\TeknikServisDurumTanimi;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TeknikServisDurumuTanimKaynagi extends Resource
{
    use TeknikServisTanimKaynakErisimi;

    protected static ?string $model = TeknikServisDurumTanimi::class;

    protected static ?string $cluster = TeknikServisCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationLabel = 'Servis durumları';

    protected static ?string $modelLabel = 'Servis durumu';

    protected static ?string $pluralModelLabel = 'Servis durumları';

    protected static ?string $slug = 'tanimlar/servis-durumlari';

    protected static ?string $navigationGroup = 'Tanımlar';

    protected static ?int $navigationSort = 30;

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
                'is_fiyat_verildi',
                'is_teslim_edildi',
                'is_iptal',
                'is_iade',
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
            Forms\Components\Fieldset::make('Bayraklar')->schema([
                Forms\Components\Toggle::make('is_fiyat_verildi')->label('Fiyat verildi')->default(false),
                Forms\Components\Toggle::make('is_teslim_edildi')->label('Teslim edildi')->default(false),
                Forms\Components\Toggle::make('is_iptal')->label('İptal')->default(false),
                Forms\Components\Toggle::make('is_iade')->label('İade')->default(false),
            ])->columns(2),
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
                    'siralama',
                ]))
            ->columns([
                Tables\Columns\TextColumn::make('ad')->label('Ad')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('kod')->label('Kod')->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('aktif')->label('Aktif')->boolean(),
                Tables\Columns\TextColumn::make('siralama')->label('Sıra')->sortable(),
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
            Notification::make()->title('Hedef tanım seçilmelidir')->body('Bağlı servis kayıtlarını aktarmak için hedef servis durumu seçin.')->danger()->send();

            return false;
        }

        return (bool) DB::transaction(function () use ($record, $hedef): bool {
            if ($hedef) {
                $record->teknikServisKayitlari()->update(['servis_durumu_id' => $hedef->getKey()]);
                DB::table('teknik_servis_durum_gecmisleri')
                    ->where('onceki_servis_durumu_id', $record->getKey())
                    ->update(['onceki_servis_durumu_id' => $hedef->getKey()]);
                DB::table('teknik_servis_durum_gecmisleri')
                    ->where('yeni_servis_durumu_id', $record->getKey())
                    ->update(['yeni_servis_durumu_id' => $hedef->getKey()]);
            }

            return (bool) $record->delete();
        });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeknikServisDurumlari::route('/'),
            'create' => Pages\CreateTeknikServisDurumu::route('/create'),
            'edit' => Pages\EditTeknikServisDurumu::route('/{record}/edit'),
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
