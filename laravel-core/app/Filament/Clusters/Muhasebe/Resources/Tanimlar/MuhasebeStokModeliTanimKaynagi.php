<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeStokModeliTanimKaynagi\Pages;
use App\Models\Muhasebe\MuhasebeMarka;
use App\Models\Muhasebe\MuhasebeStokModeli;
use App\Muhasebe\Filament\AbstractKaynaklar\StandartMuhasebeTanimKaynakResource;
use App\Services\TenantContextService;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MuhasebeStokModeliTanimKaynagi extends StandartMuhasebeTanimKaynakResource
{
    protected static ?string $model = MuhasebeStokModeli::class;

    protected static ?string $slug = 'tanimlar/stok-modelleri';

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $modelLabel = 'Ürün Model';

    protected static ?string $pluralModelLabel = 'Ürün Modelleri';

    public static function form(Form $form): Form
    {
        if (static::hizliDuzenlemeModu()) {
            return $form->schema([
                Forms\Components\Hidden::make('is_sabit')
                    ->dehydrated(),
                Forms\Components\Hidden::make('firma_id')
                    ->default(fn () => app(TenantContextService::class)->aktifFirmaId())
                    ->dehydrated(),
                Forms\Components\Hidden::make('marka_id'),
                Forms\Components\Grid::make(['default' => 1, 'md' => 3])
                    ->schema([
                        Forms\Components\TextInput::make('kod')
                            ->label('Kod')
                            ->required()
                            ->maxLength(64)
                            ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                            ->dehydrateStateUsing(fn (?string $state) => $state ? strtoupper(trim($state)) : $state),
                        Forms\Components\TextInput::make('ad')
                            ->label('Ad')
                            ->maxLength(191),
                        Forms\Components\Checkbox::make('aktif_mi')
                            ->label('Aktif')
                            ->default(true),
                    ]),
            ]);
        }

        return parent::form($form);
    }

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        if (static::hizliDuzenlemeModu()) {
            return MuhasebeStokModeli::query()
                ->select(['id', 'firma_id', 'is_sabit', 'marka_id', 'kod', 'ad', 'aktif_mi'])
                ->whereKey($key)
                ->first();
        }

        return parent::resolveRecordRouteBinding($key);
    }

    public static function table(Table $table): Table
    {
        return parent::table($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->select([
                    'id',
                    'firma_id',
                    'marka_id',
                    'kod',
                    'ad',
                    'aktif_mi',
                    'is_sabit',
                    'updated_at',
                ])
                ->with([
                    'firma:id,ad',
                    'marka:id,ad',
                ]));
    }

    /**
     * @return array<Forms\Components\Component>
     */
    protected static function tanimFormEkstraOnceKod(): array
    {
        return [
            Forms\Components\Select::make('marka_id')
                ->label('Ürün Markaları')
                ->required()
                ->getSearchResultsUsing(fn (string $search, Get $get): array => static::markaAramaSonuclari($search, $get))
                ->getOptionLabelUsing(fn ($value): ?string => static::markaEtiketi((int) $value))
                ->live(onBlur: true)
                ->searchable()
                ->optionsLimit(50),
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function markaAramaSonuclari(string $search, Get $get): array
    {
        $search = trim($search);

        if ((bool) $get('is_sabit')) {
            return MuhasebeMarka::tenantScopeOlmadan(fn () => MuhasebeMarka::query()
                ->where('is_sabit', true)
                ->whereNull('firma_id')
                ->when($search !== '', fn ($query) => $query->where('ad', 'like', '%'.$search.'%'))
                ->orderBy('ad')
                ->limit(50)
                ->pluck('ad', 'id')
                ->all());
        }

        $fid = (int) ($get('firma_id') ?: app(TenantContextService::class)->aktifFirmaId() ?? 0);
        if ($fid < 1) {
            return [];
        }

        return MuhasebeMarka::query()
            ->gorunurFirmaIle($fid)
            ->when($search !== '', fn ($query) => $query->where('ad', 'like', '%'.$search.'%'))
            ->orderBy('ad')
            ->limit(50)
            ->pluck('ad', 'id')
            ->all();
    }

    private static function markaEtiketi(int $markaId): ?string
    {
        if ($markaId < 1) {
            return null;
        }

        return MuhasebeMarka::tenantScopeOlmadan(fn () => MuhasebeMarka::query()
            ->whereKey($markaId)
            ->value('ad'));
    }

    /**
     * @return array<Tables\Columns\Column>
     */
    protected static function tanimTabloKodOncesiSutunlari(): array
    {
        return [
            Tables\Columns\TextColumn::make('marka.ad')
                ->label('Ürün Markaları')
                ->searchable()
                ->sortable(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMuhasebeStokModelleri::route('/'),
            'create' => Pages\CreateMuhasebeStokModeli::route('/create'),
            'edit' => Pages\EditMuhasebeStokModeli::route('/{record}/edit'),
        ];
    }
}
