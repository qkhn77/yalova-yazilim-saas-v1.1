<?php

namespace App\Filament\Clusters\TeknikServis\Resources;

use App\Filament\Clusters\TeknikServis as TeknikServisCluster;
use App\Filament\Clusters\TeknikServis\Resources\Concerns\TeknikServisTanimKaynakErisimi;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisCihazKaynagi\Pages;
use App\Models\TeknikServis\TeknikServisCihazTanimi;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;

class TeknikServisCihazKaynagi extends Resource
{
    use TeknikServisTanimKaynakErisimi;

    protected static ?string $model = TeknikServisCihazTanimi::class;

    protected static ?string $cluster = TeknikServisCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationLabel = 'Cihaz tanımları';

    protected static ?string $modelLabel = 'Cihaz';

    protected static ?string $pluralModelLabel = 'Cihaz tanımları';

    protected static ?string $slug = 'tanimlar/cihazlar';

    protected static ?string $navigationGroup = 'Tanımlar';

    protected static ?int $navigationSort = 31;

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
                Tables\Actions\DeleteAction::make()
                    ->using(fn (Model $record): bool => static::silmeIslemi($record)),
            ])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    public static function silmeIslemi(Model $record, ?Model $hedef = null): bool
    {
        $bagliServisSayisi = (int) $record->teknikServisKayitlari()->count();
        $bagliArizaSayisi = (int) $record->arizalar()->count();

        if (($bagliServisSayisi > 0 || $bagliArizaSayisi > 0) && ! $hedef) {
            Notification::make()
                ->title('Hedef tanım seçilmelidir')
                ->body('Bağlı servis veya arıza kayıtları bulunduğu için aktarılacak hedef tanımı seçin.')
                ->danger()
                ->send();

            return false;
        }

        if ($hedef && (int) $hedef->getKey() === (int) $record->getKey()) {
            Notification::make()
                ->title('Geçersiz hedef tanım')
                ->body('Silinen tanım hedef olarak seçilemez.')
                ->danger()
                ->send();

            return false;
        }

        return (bool) DB::transaction(function () use ($record, $hedef): bool {
            if ($hedef) {
                $record->teknikServisKayitlari()->update(['cihaz_id' => $hedef->getKey()]);
                $record->arizalar()->update(['cihaz_id' => $hedef->getKey()]);
            }

            return (bool) $record->delete();
        });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeknikServisCihazlari::route('/'),
            'create' => Pages\CreateTeknikServisCihazi::route('/create'),
            'edit' => Pages\EditTeknikServisCihazi::route('/{record}/edit'),
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
