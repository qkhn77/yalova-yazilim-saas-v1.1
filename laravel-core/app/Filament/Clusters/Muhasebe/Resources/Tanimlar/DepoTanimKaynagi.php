<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\DepoTanimKaynagi\Pages;
use App\Models\Muhasebe\Depo;
use App\Models\Muhasebe\StokDepoBakiyesi;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\StokTransferi;
use App\Filament\Clusters\Muhasebe\Pages\StokHareketleriSayfasi;
use App\Muhasebe\Filament\AbstractKaynaklar\StandartMuhasebeTanimKaynakResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class DepoTanimKaynagi extends StandartMuhasebeTanimKaynakResource
{
    protected static ?string $model = Depo::class;

    protected static ?string $slug = 'tanimlar/depolar';

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $modelLabel = 'Depo';

    protected static ?string $pluralModelLabel = 'Depolar';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Depo bilgileri')
                ->schema([
                    Forms\Components\TextInput::make('kod')
                        ->label('Kod')
                        ->required()
                        ->maxLength(64)
                        ->dehydrateStateUsing(fn (?string $state): string => strtoupper(trim((string) $state))),
                    Forms\Components\TextInput::make('ad')
                        ->label('Depo adı')
                        ->required()
                        ->maxLength(191),
                    Forms\Components\Textarea::make('adres')
                        ->label('Adres')
                        ->rows(3)
                        ->columnSpanFull(),
                    Forms\Components\Toggle::make('varsayilan_mi')
                        ->label('Varsayılan depo')
                        ->helperText('Firma ayarlarında ayrıca seçilebilir; tek varsayılan depo tutulur.'),
                    Forms\Components\Toggle::make('aktif_mi')
                        ->label('Aktif')
                        ->default(true),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('kod')->label('Kod')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('ad')->label('Depo')->searchable()->sortable(),
                Tables\Columns\IconColumn::make('varsayilan_mi')->label('Varsayılan')->boolean(),
                Tables\Columns\IconColumn::make('aktif_mi')->label('Aktif')->boolean(),
            ])
            ->actions([
                Tables\Actions\Action::make('depo_hareketleri')
                    ->label('Depo Hareketleri')
                    ->icon('heroicon-o-list-bullet')
                    ->color('gray')
                    ->url(fn (Depo $record): string => StokHareketleriSayfasi::getUrl(['depo_id' => (int) $record->getKey()])),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDepolar::route('/'),
            'create' => Pages\CreateDepo::route('/create'),
            'edit' => Pages\EditDepo::route('/{record}/edit'),
        ];
    }

    public static function canDelete(Model $record): bool
    {
        if (! parent::canDelete($record) || (bool) $record->varsayilan_mi) {
            return false;
        }

        $depoId = (int) $record->getKey();

        return ! StokKarti::query()->where('depo_id', $depoId)->exists()
            && ! StokDepoBakiyesi::query()->where('depo_id', $depoId)->exists()
            && ! StokTransferi::query()
                ->where(fn ($query) => $query
                    ->where('kaynak_depo_id', $depoId)
                    ->orWhere('hedef_depo_id', $depoId))
                ->exists();
    }
}
