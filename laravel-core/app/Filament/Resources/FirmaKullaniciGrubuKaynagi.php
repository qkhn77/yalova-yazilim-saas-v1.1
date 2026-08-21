<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FirmaKullaniciGrubuKaynagi\Pages;
use App\Filament\Resources\FirmaKullaniciGrubuKaynagi\RelationManagers\YetkilerIliskiYoneticisi;
use App\Models\Rol;
use App\Models\User;
use App\Services\TenantContextService;
use App\Services\YetkiService;
use App\Support\FirmaIciRolKisitlayici;
use App\Support\KullaniciRolYardimcisi;
use App\Support\SaaSemaYardimcisi;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class FirmaKullaniciGrubuKaynagi extends Resource
{
    private static ?bool $yetkiYonetebilirCache = null;

    protected static ?string $model = Rol::class;

    protected static ?string $slug = 'firma-kullanici-gruplari';

    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationLabel(): string
    {
        return 'Firma Kullanıcı Grupları';
    }

    public static function getModelLabel(): string
    {
        return 'Firma kullanıcı grubu';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Firma kullanıcı grupları';
    }

    public static function canAccess(): bool
    {
        return static::yetkiYonetebilirMi();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->where('sistem_rolu_mu', false);
        $kullanici = Auth::user();
        $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);

        if ($kullanici instanceof User && KullaniciRolYardimcisi::superAdminVeyaIsAdmin($kullanici)) {
            // Super admin'ler firma bağlamı seçili olmasa da tüm firma gruplarını görebilir.
            return $query->where('kod', 'like', 'firma_%');
        }

        if ($firmaId < 1) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('kod', 'like', FirmaIciRolKisitlayici::firmaGrupKodOnEki($firmaId).'%');
    }

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        if (static::hizliDuzenlemeModu()) {
            return static::getModel()::query()
                ->select([
                    'id',
                    'ad',
                    'kod',
                    'sistem_rolu_mu',
                ])
                ->whereKey($key)
                ->first();
        }

        return parent::resolveRecordRouteBinding($key);
    }

    public static function form(Form $form): Form
    {
        if (static::hizliDuzenlemeModu()) {
            return $form->schema([
                Forms\Components\TextInput::make('ad')
                    ->label('Grup adı')
                    ->required()
                    ->maxLength(255),
            ]);
        }

        return $form->schema([
            Forms\Components\Section::make('Grup bilgileri')
                ->schema([
                    Forms\Components\TextInput::make('ad')
                        ->label('Grup adı')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Textarea::make('aciklama')
                        ->label('Açıklama')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->select([
                'id',
                'ad',
                'kod',
                'aciklama',
                'sistem_rolu_mu',
            ]))
            ->columns([
                Tables\Columns\TextColumn::make('ad')
                    ->label('Grup adı')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('aciklama')
                    ->label('Açıklama')
                    ->limit(60),
                Tables\Columns\TextColumn::make('firmaKullanicilari_count')
                    ->label('Kullanıcı sayısı')
                    ->counts('firmaKullanicilari')
                    ->badge(),
            ])
            ->defaultSort('ad')
            ->actions([
                Tables\Actions\EditAction::make()->label('Düzenle'),
                Tables\Actions\DeleteAction::make()->label('Sil'),
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
        if (! static::detayModu()) {
            return [];
        }

        if (! SaaSemaYardimcisi::yetkilerTablosuVarMi() || ! SaaSemaYardimcisi::rolYetkileriTablosuVarMi()) {
            return [];
        }

        return [
            YetkilerIliskiYoneticisi::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFirmaKullaniciGruplari::route('/'),
            'create' => Pages\CreateFirmaKullaniciGrubu::route('/create'),
            'edit' => Pages\EditFirmaKullaniciGrubu::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return static::yetkiYonetebilirMi();
    }

    public static function canCreate(): bool
    {
        return static::yetkiYonetebilirMi();
    }

    public static function canEdit(Model $record): bool
    {
        return static::yetkiYonetebilirMi() && static::kayitGecerliFirmayaAitMi($record);
    }

    public static function canDelete(Model $record): bool
    {
        return static::yetkiYonetebilirMi()
            && static::kayitGecerliFirmayaAitMi($record)
            && ! $record->firmaKullanicilari()->exists();
    }

    public static function firmaKodluGrupKodu(string $ad, int $firmaId): string
    {
        $prefix = FirmaIciRolKisitlayici::firmaGrupKodOnEki($firmaId);
        $slug = Str::slug($ad, '_');
        $slug = $slug !== '' ? $slug : 'grup';
        $base = $prefix.$slug;
        $code = $base;
        $i = 2;

        while (Rol::query()->where('kod', $code)->exists()) {
            $code = $base.'_'.$i;
            $i++;
        }

        return $code;
    }

    protected static function kayitGecerliFirmayaAitMi(Model $record): bool
    {
        $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);

        return $firmaId > 0
            && $record instanceof Rol
            && str_starts_with((string) $record->kod, FirmaIciRolKisitlayici::firmaGrupKodOnEki($firmaId));
    }

    protected static function yetkiYonetebilirMi(): bool
    {
        if (self::$yetkiYonetebilirCache !== null) {
            return self::$yetkiYonetebilirCache;
        }

        $kullanici = Auth::user();
        $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);

        if ($kullanici instanceof User && KullaniciRolYardimcisi::superAdminVeyaIsAdmin($kullanici)) {
            return self::$yetkiYonetebilirCache = true;
        }

        return self::$yetkiYonetebilirCache = $kullanici instanceof User
            && $firmaId > 0
            && app(YetkiService::class)->yetkiVarMi($kullanici, $firmaId, 'firma_yetki_yonetimi.yonet');
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
