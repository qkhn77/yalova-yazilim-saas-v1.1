<?php

namespace App\Filament\Clusters\PersonelTakip\Resources;

use App\Filament\Clusters\PersonelTakip as PersonelTakipCluster;
use App\Filament\Clusters\PersonelTakip\Kaynaklar\PersonelTakipFilamentErisimYardimcisi;
use App\Filament\Clusters\PersonelTakip\Kaynaklar\PersonelTakipKaynakErisimi;
use App\Filament\Clusters\PersonelTakip\Resources\PersonelDepartmanKaynagi\Pages;
use App\Models\Personel\PersonelDepartmani;
use App\Models\Sube;
use App\Services\TenantContextService;
use App\Support\PersonelTakip\PersonelTakipYetkiSablonlari;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PersonelDepartmanKaynagi extends Resource
{
    use PersonelTakipKaynakErisimi;

    private static ?bool $guncellemeErisimCache = null;

    protected static ?string $model = PersonelDepartmani::class;

    protected static ?string $cluster = PersonelTakipCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Departmanlar';

    protected static ?string $modelLabel = 'Departman';

    protected static ?string $pluralModelLabel = 'Departmanlar';

    protected static ?string $slug = 'tanimlar/departmanlar';

    protected static function goruntuleYetkisi(): string
    {
        return PersonelTakipYetkiSablonlari::TANIM_GORUNTULE;
    }

    protected static function olusturYetkisi(): string
    {
        return PersonelTakipYetkiSablonlari::TANIM_GUNCELLE;
    }

    protected static function guncelleYetkisi(): string
    {
        return PersonelTakipYetkiSablonlari::TANIM_GUNCELLE;
    }

    protected static function silYetkisi(): string
    {
        return PersonelTakipYetkiSablonlari::TANIM_GUNCELLE;
    }

    public static function canEdit(Model $record): bool
    {
        if (! PersonelTakipFilamentErisimYardimcisi::kayitAktifFirmayaAitMi($record)) {
            return false;
        }

        return self::$guncellemeErisimCache ??= PersonelTakipFilamentErisimYardimcisi::personelYetkisiVarMi(static::guncelleYetkisi());
    }

    public static function form(Form $form): Form
    {
        if (static::hizliDuzenlemeModu()) {
            return $form->schema([
                Forms\Components\Toggle::make('aktif_mi')
                    ->label('Aktif')
                    ->default(true),
            ]);
        }

        return $form->schema([
            Forms\Components\Hidden::make('firma_id')
                ->default(fn (): ?int => app(TenantContextService::class)->aktifFirmaId())
                ->dehydrated(),
            Forms\Components\Section::make('Departman bilgileri')
                ->schema([
                    Forms\Components\TextInput::make('ad')
                        ->label('Ad')
                        ->required()
                        ->maxLength(191),
                    Forms\Components\TextInput::make('kod')
                        ->label('Kod')
                        ->maxLength(64),
                    Forms\Components\Select::make('sube_id')
                        ->label('Şube')
                        ->options(fn (): array => static::subeSecenekleri())
                        ->searchable()
                        ->preload(),
                    Forms\Components\Toggle::make('aktif_mi')
                        ->label('Aktif')
                        ->default(true),
                    Forms\Components\TextInput::make('siralama')
                        ->label('Sıralama')
                        ->numeric()
                        ->default(0),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->select([
                    'id',
                    'ad',
                    'sube_id',
                    'kod',
                    'aktif_mi',
                    'siralama',
                ])
                ->with('sube:id,ad'))
            ->columns([
                Tables\Columns\TextColumn::make('ad')->label('Ad')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('sube.ad')->label('Şube')->sortable(),
                Tables\Columns\TextColumn::make('kod')->label('Kod')->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('aktif_mi')->label('Aktif')->boolean(),
                Tables\Columns\TextColumn::make('siralama')->label('Sıra')->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('aktif_mi')->label('Aktif'),
                Tables\Filters\SelectFilter::make('sube_id')
                    ->label('Şube')
                    ->options(fn (): array => static::subeSecenekleri()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPersonelDepartmanlari::route('/'),
            'create' => Pages\CreatePersonelDepartman::route('/create'),
            'edit' => Pages\EditPersonelDepartman::route('/{record}/edit'),
        ];
    }

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        if (static::hizliDuzenlemeModu()) {
            return PersonelDepartmani::query()
                ->select(['id', 'aktif_mi'])
                ->whereKey($key)
                ->first();
        }

        return parent::resolveRecordRouteBinding($key);
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

    /**
     * @return array<int, string>
     */
    protected static function subeSecenekleri(): array
    {
        $firmaId = (int) app(TenantContextService::class)->aktifFirmaId();
        if ($firmaId < 1) {
            return [];
        }

        return Cache::remember(
            'personel:departman:sube-secenekleri:v1:'.$firmaId,
            now()->addMinutes(5),
            fn (): array => Sube::query()
                ->where('firma_id', $firmaId)
                ->orderBy('ad')
                ->pluck('ad', 'id')
                ->all()
        );
    }
}
