<?php

namespace App\Filament\Clusters\TeknikServis\Resources;

use App\Filament\Clusters\TeknikServis as TeknikServisCluster;
use App\Filament\Clusters\TeknikServis\Resources\Concerns\TeknikServisTanimKaynakErisimi;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisAksesuarKaynagi\Pages;
use App\Models\TeknikServis\TeknikServisAksesuarTanimi;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TeknikServisAksesuarKaynagi extends Resource
{
    use TeknikServisTanimKaynakErisimi;

    protected static ?string $model = TeknikServisAksesuarTanimi::class;

    protected static ?string $cluster = TeknikServisCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?string $navigationLabel = 'Aksesuar tanımları';

    protected static ?string $modelLabel = 'Aksesuar';

    protected static ?string $pluralModelLabel = 'Aksesuar tanımları';

    protected static ?string $slug = 'tanimlar/aksesuarlar';

    protected static ?string $navigationGroup = 'Tanımlar';

    protected static ?int $navigationSort = 33;

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        if (! static::detayModu() && filled(request()->route('record'))) {
            return static::getModel()::query()
                ->select(['id', 'aktif'])
                ->whereKey($key)
                ->first();
        }

        return parent::resolveRecordRouteBinding($key);
    }

    public static function form(Form $form): Form
    {
        if (! static::detayModu() && filled(request()->route('record'))) {
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
        $pivot = 'teknik_servis_aksesuar_kayitlari';
        $bagli = (int) DB::table($pivot)->where('aksesuar_id', $record->getKey())->count();
        if ($bagli > 0 && ! $hedef) {
            Notification::make()->title('Hedef tanım seçilmelidir')->body('Bağlı servis kayıtlarını aktarmak için hedef aksesuar seçin.')->danger()->send();

            return false;
        }

        return (bool) DB::transaction(function () use ($record, $hedef, $pivot): bool {
            if ($hedef) {
                DB::table($pivot)
                    ->where('aksesuar_id', $record->getKey())
                    ->get(['id', 'teknik_servis_kaydi_id'])
                    ->each(function (object $satir) use ($hedef, $pivot): void {
                        $hedefVar = DB::table($pivot)
                            ->where('teknik_servis_kaydi_id', $satir->teknik_servis_kaydi_id)
                            ->where('aksesuar_id', $hedef->getKey())
                            ->exists();
                        if ($hedefVar) {
                            DB::table($pivot)->where('id', $satir->id)->delete();
                        } else {
                            DB::table($pivot)->where('id', $satir->id)->update(['aksesuar_id' => $hedef->getKey()]);
                        }
                    });
            }

            return (bool) $record->delete();
        });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeknikServisAksesuarlari::route('/'),
            'create' => Pages\CreateTeknikServisAksesuar::route('/create'),
            'edit' => Pages\EditTeknikServisAksesuar::route('/{record}/edit'),
        ];
    }

    public static function detayModu(): bool
    {
        return request()->boolean('detay');
    }
}
