<?php

namespace App\Filament\Clusters\TeknikServis\Resources;

use App\Filament\Clusters\TeknikServis as TeknikServisCluster;
use App\Filament\Clusters\TeknikServis\Resources\Concerns\TeknikServisTanimKaynakErisimi;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisArizaKaynagi\Pages;
use App\Models\TeknikServis\TeknikServisArizaTanimi;
use App\Models\TeknikServis\TeknikServisCihazTanimi;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TeknikServisArizaKaynagi extends Resource
{
    use TeknikServisTanimKaynakErisimi;

    protected static ?string $model = TeknikServisArizaTanimi::class;

    protected static ?string $cluster = TeknikServisCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationLabel = 'Arıza tanımları';

    protected static ?string $modelLabel = 'Arıza';

    protected static ?string $pluralModelLabel = 'Arıza tanımları';

    protected static ?string $slug = 'tanimlar/arizalar';

    protected static ?string $navigationGroup = 'Tanımlar';

    protected static ?int $navigationSort = 34;

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        if (static::hizliDuzenlemeModu()) {
            return static::getModel()::query()
                ->select(['id', 'aktif'])
                ->whereKey($key)
                ->first();
        }

        return parent::resolveRecordRouteBinding($key);
    }

    public static function form(Form $form): Form
    {
        if ($form->getOperation() !== 'create' && static::hizliDuzenlemeModu()) {
            return $form->schema([
                Forms\Components\Toggle::make('aktif')
                    ->label('Aktif')
                    ->default(true),
            ]);
        }

        return $form->schema([
            Forms\Components\Hidden::make('firma_id')
                ->default(null)
                ->dehydrated(),
            Forms\Components\Select::make('cihaz_id')
                ->label('Cihaz')
                ->options(fn (): array => TeknikServisCihazTanimi::query()
                    ->orderBy('ad')
                    ->pluck('ad', 'id')
                    ->all())
                ->searchable()
                ->placeholder('Kategorisiz')
                ->nullable(),
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
                    'cihaz_id',
                    'ad',
                    'kod',
                    'aktif',
                    'siralama',
                    'varsayilan_mi',
                ])
                ->with(['cihaz:id,ad']))
            ->columns([
                Tables\Columns\TextColumn::make('cihaz.ad')->label('Cihaz')->placeholder('-')->sortable(),
                Tables\Columns\TextColumn::make('ad')->label('Arıza')->searchable()->sortable(),
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
        $pivot = 'teknik_servis_ariza_kayitlari';
        $bagli = (int) $record->teknikServisKayitlari()->count()
            + (int) $record->teknikServisKayitCoklu()->count();
        if ($bagli > 0 && ! $hedef) {
            Notification::make()->title('Hedef tanım seçilmelidir')->body('Bağlı servis kayıtlarını aktarmak için hedef arıza seçin.')->danger()->send();

            return false;
        }

        return (bool) DB::transaction(function () use ($record, $hedef, $pivot): bool {
            if ($hedef) {
                $record->teknikServisKayitlari()->update(['ariza_id' => $hedef->getKey()]);
                DB::table($pivot)
                    ->where('ariza_id', $record->getKey())
                    ->get(['id', 'teknik_servis_kaydi_id'])
                    ->each(function (object $satir) use ($hedef, $pivot): void {
                        $hedefVar = DB::table($pivot)
                            ->where('teknik_servis_kaydi_id', $satir->teknik_servis_kaydi_id)
                            ->where('ariza_id', $hedef->getKey())
                            ->exists();
                        if ($hedefVar) {
                            DB::table($pivot)->where('id', $satir->id)->delete();
                        } else {
                            DB::table($pivot)->where('id', $satir->id)->update(['ariza_id' => $hedef->getKey()]);
                        }
                    });
            }

            return (bool) $record->delete();
        });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeknikServisArizalari::route('/'),
            'create' => Pages\CreateTeknikServisArizasi::route('/create'),
            'edit' => Pages\EditTeknikServisArizasi::route('/{record}/edit'),
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
