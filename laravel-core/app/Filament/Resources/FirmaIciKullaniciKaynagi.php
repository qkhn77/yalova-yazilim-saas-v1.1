<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FirmaIciKullaniciKaynagi\Pages;
use App\Filament\Resources\FirmaIciKullaniciKaynagi\RelationManagers;
use App\Models\FirmaKullanici;
use App\Models\Rol;
use App\Models\User;
use App\Services\TenantContextService;
use App\Support\FirmaIciRolKisitlayici;
use App\Support\RolYardimcisi;
use App\Support\SaaSemaYardimcisi;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class FirmaIciKullaniciKaynagi extends Resource
{
    /** @var array<string, array<int, string>> */
    private static array $rolSecenekleriCache = [];

    /** @var array<string, bool> */
    private static array $politikaYetkiCache = [];

    /** @var array<string, bool> */
    private static array $kayitPolitikaYetkiCache = [];

    private static ?int $varsayilanYoneticiRolIdCache = null;

    private static ?bool $saasYapisiHazirMiCache = null;

    private static ?bool $onayDurumuKolonuVarMiCache = null;

    private static ?bool $kullaniciYetkileriTablosuVarMiCache = null;

    private static ?bool $superAdminMiCache = null;

    private static ?int $aktifFirmaIdCache = null;

    protected static ?string $model = FirmaKullanici::class;

    protected static ?string $slug = 'firma-ici-kullanicilar';

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationLabel(): string
    {
        return 'Firma kullanıcıları';
    }

    public static function getModelLabel(): string
    {
        return 'Firma kullanıcısı';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Firma kullanıcıları';
    }

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        if (static::hizliDuzenlemeModu()) {
            $kullanici = Auth::user();
            if (! $kullanici) {
                return null;
            }

            $sorgu = FirmaKullanici::query()
                ->withoutGlobalScopes()
                ->select([
                    'id',
                    'firma_id',
                    'kullanici_id',
                    'durum',
                ])
                ->whereKey($key);

            if (! ((bool) ($kullanici->super_admin_mi ?? false) || (bool) ($kullanici->is_admin ?? false))) {
                $fid = static::aktifFirmaId();
                if (! $fid) {
                    return null;
                }

                $sorgu->where('firma_id', $fid);
            }

            return $sorgu->first();
        }

        return static::getEloquentQuery()
            ->whereKey($key)
            ->first();
    }

    public static function canAccess(): bool
    {
        if (static::superAdminMi()) {
            return true;
        }

        if (! static::saasYapisiHazirMi()) {
            return false;
        }

        return static::politikaYetkisiVarMi('viewAny');
    }

    public static function getEloquentQuery(): Builder
    {
        $kolonlar = static::hizliDuzenlemeModu()
            ? [
                'id',
                'firma_id',
                'kullanici_id',
                'durum',
            ]
            : [
                'id',
                'firma_id',
                'kullanici_id',
                'rol_id',
                'durum',
                'onay_durumu',
                'varsayilan_firma_mi',
            ];

        $sorgu = parent::getEloquentQuery()
            ->withoutGlobalScopes()
            ->select($kolonlar);

        if (! static::hizliDuzenlemeModu()) {
            $sorgu->with([
                'kullanici:id,name,ad_soyad,kullanici_adi,email',
                'rol:id,ad,kod',
                'firma:id,ad',
            ]);
        }

        $kullanici = Auth::user();
        if (! $kullanici) {
            return $sorgu->whereRaw('1 = 0');
        }

        if ((bool) ($kullanici->super_admin_mi ?? false) || (bool) ($kullanici->is_admin ?? false)) {
            return $sorgu;
        }

        $fid = static::aktifFirmaId();
        if (! $fid) {
            return $sorgu->whereRaw('1 = 0');
        }

        return $sorgu->where('firma_id', $fid);
    }

    public static function form(Form $form): Form
    {
        $hizliDuzenleme = static::hizliDuzenlemeModu();

        if ($hizliDuzenleme) {
            return $form->schema([
                Forms\Components\Checkbox::make('durum_aktif')
                    ->label('Aktif')
                    ->default(true),
            ]);
        }

        return $form->schema([
            Forms\Components\Section::make('Kullanıcı')
                ->schema([
                    Forms\Components\Select::make('hedef_firma_id')
                        ->label('Firma')
                        ->relationship('firma', 'ad', fn (Builder $query) => $query->orderBy('ad'))
                        ->searchable()
                        ->live()
                        ->visible(fn (?FirmaKullanici $record): bool => $record === null && static::superAdminMi())
                        ->required(fn (?FirmaKullanici $record): bool => $record === null && static::superAdminMi()),
                    Forms\Components\TextInput::make('email')
                        ->label('E-posta')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->disabled(fn (?FirmaKullanici $record): bool => $record !== null),
                    Forms\Components\TextInput::make('password')
                        ->label('Şifre')
                        ->password()
                        ->revealable()
                        ->minLength(6)
                        ->maxLength(255)
                        ->required(fn (?FirmaKullanici $record): bool => $record === null)
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->helperText(fn (?FirmaKullanici $record): string => $record ? 'Boş bırakılırsa şifre değişmez.' : 'Yeni kullanıcı için zorunlu.'),
                    Forms\Components\TextInput::make('kullanici_adi')
                        ->label('Kullanıcı adı')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('ad_soyad')
                        ->label('Ad soyad')
                        ->maxLength(255),
                ])->columns(2),
            Forms\Components\Section::make('Firma bağlantısı')
                ->schema([
                    Forms\Components\Select::make('rol_id')
                        ->label('Rol')
                        ->options(fn (): array => static::rolSecenekleri())
                        ->searchable()
                        ->default(fn (): ?int => static::varsayilanYoneticiRolId()),
                    ...static::firmaBaglantisiDurumAlanlari(),
                ])->columns(2),
        ]);
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    private static function firmaBaglantisiDurumAlanlari(): array
    {
        return [
            Forms\Components\Select::make('durum')
                ->label('Durum')
                ->options([
                    'aktif' => 'Aktif',
                    'pasif' => 'Pasif',
                ])
                ->required()
                ->default('aktif'),
            Forms\Components\Select::make('onay_durumu')
                ->label('Onay durumu')
                ->options([
                    'aktif' => 'Aktif',
                    'beklemede' => 'Beklemede',
                ])
                ->default('aktif')
                ->visible(fn (): bool => static::onayDurumuKolonuVarMi()),
            Forms\Components\Toggle::make('varsayilan_firma_mi')
                ->label('Varsayılan firma'),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('firma.ad')
                    ->label('Firma')
                    ->visible(fn (): bool => static::superAdminMi())
                    ->sortable(),
                Tables\Columns\TextColumn::make('kullanici.ad_soyad')
                    ->label('Ad soyad')
                    ->placeholder(fn (FirmaKullanici $record): string => (string) ($record->kullanici?->name ?? ''))
                    ->searchable(),
                Tables\Columns\TextColumn::make('kullanici.kullanici_adi')
                    ->label('Kullanıcı adı')
                    ->searchable(),
                Tables\Columns\TextColumn::make('kullanici.email')
                    ->label('E-posta')
                    ->searchable(),
                Tables\Columns\TextColumn::make('rol.ad')
                    ->label('Rol')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('durum')->label('Durum')->badge(),
                Tables\Columns\TextColumn::make('onay_durumu')
                    ->label('Onay')
                    ->badge()
                    ->placeholder('aktif')
                    ->visible(fn (): bool => static::onayDurumuKolonuVarMi()),
                Tables\Columns\IconColumn::make('varsayilan_firma_mi')->label('Varsayılan')->boolean(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('durum')
                    ->label('Durum')
                    ->options(['aktif' => 'Aktif', 'pasif' => 'Pasif']),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Düzenle'),
                Tables\Actions\DeleteAction::make()->label('Ayır'),
            ])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    public static function getRelations(): array
    {
        if (! request()->boolean('detay')) {
            return [];
        }

        if (! static::kullaniciYetkileriTablosuVarMi()) {
            return [];
        }

        return [
            RelationManagers\OzelYetkilerleIliskiYoneticisi::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\FirmaIciKullaniciListesi::route('/'),
            'create' => Pages\FirmaIciKullaniciOlustur::route('/create'),
            'edit' => Pages\FirmaIciKullaniciDuzenle::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        if (static::superAdminMi()) {
            return true;
        }

        if (! static::saasYapisiHazirMi()) {
            return false;
        }

        return static::politikaYetkisiVarMi('viewAny');
    }

    public static function canCreate(): bool
    {
        if (static::superAdminMi()) {
            return true;
        }

        if (! static::saasYapisiHazirMi()) {
            return false;
        }

        return static::politikaYetkisiVarMi('create');
    }

    public static function canEdit(Model $kayit): bool
    {
        if (static::superAdminMi()) {
            return true;
        }

        if (! static::saasYapisiHazirMi()) {
            return false;
        }

        $kullanici = Auth::user();
        if (! $kullanici instanceof User) {
            return false;
        }
        $cacheKey = (int) $kullanici->id.'|update|'.(int) $kayit->getKey();

        return self::$kayitPolitikaYetkiCache[$cacheKey] ??= $kullanici->can('update', $kayit);
    }

    public static function canDelete(Model $kayit): bool
    {
        if (static::superAdminMi()) {
            return true;
        }

        if (! static::saasYapisiHazirMi()) {
            return false;
        }

        return Auth::check() && Auth::user()->can('delete', $kayit);
    }

    protected static function superAdminMi(): bool
    {
        if (self::$superAdminMiCache !== null) {
            return self::$superAdminMiCache;
        }

        $k = Auth::user();

        return self::$superAdminMiCache = $k instanceof User
            && ((bool) ($k->super_admin_mi ?? false) || (bool) ($k->is_admin ?? false));
    }

    protected static function varsayilanYoneticiRolId(): ?int
    {
        return self::$varsayilanYoneticiRolIdCache ??= RolYardimcisi::varsayilanFirmaYoneticisiRolId();
    }

    /**
     * @return array<int, string>
     */
    protected static function rolSecenekleri(): array
    {
        $kullanici = Auth::user();
        if (! $kullanici) {
            return [];
        }

        if (static::superAdminMi()) {
            $cacheKey = 'super-admin';

            return self::$rolSecenekleriCache[$cacheKey] ??= Cache::remember(
                'firma-ici-kullanici:rol-secenekleri:v1:'.$cacheKey,
                now()->addSeconds(60),
                fn (): array => Rol::query()
                    ->where('sistem_rolu_mu', true)
                    ->orderBy('ad')
                    ->get(['id', 'ad', 'kod'])
                    ->mapWithKeys(fn (Rol $rol): array => [(int) $rol->id => $rol->ad.' ('.$rol->kod.')'])
                    ->all()
            );
        }

        $fid = (int) (static::aktifFirmaId() ?? 0);
        if ($fid <= 0) {
            return [];
        }

        $cacheKey = (int) $kullanici->id.'|'.$fid;

        return self::$rolSecenekleriCache[$cacheKey] ??= Cache::remember(
            'firma-ici-kullanici:rol-secenekleri:v1:'.$cacheKey,
            now()->addSeconds(60),
            fn (): array => FirmaIciRolKisitlayici::atanabilirRollerSorgusu($kullanici, $fid)
                ->get(['id', 'ad', 'kod'])
                ->mapWithKeys(fn (Rol $rol): array => [(int) $rol->id => $rol->ad.' ('.$rol->kod.')'])
                ->all()
        );
    }

    private static function saasYapisiHazirMi(): bool
    {
        return self::$saasYapisiHazirMiCache ??= SaaSemaYardimcisi::firmaKullanicilariTablosuVarMi()
            && SaaSemaYardimcisi::rollerTablosuVarMi();
    }

    private static function onayDurumuKolonuVarMi(): bool
    {
        return self::$onayDurumuKolonuVarMiCache ??= SaaSemaYardimcisi::firmaKullanicilariOnayDurumuKolonuVarMi();
    }

    private static function kullaniciYetkileriTablosuVarMi(): bool
    {
        return self::$kullaniciYetkileriTablosuVarMiCache ??= SaaSemaYardimcisi::tabloVarMi('kullanici_yetkileri');
    }

    private static function politikaYetkisiVarMi(string $yetki): bool
    {
        $kullanici = Auth::user();
        if (! $kullanici instanceof User) {
            return false;
        }

        $cacheKey = (int) $kullanici->id.'|'.$yetki;

        return self::$politikaYetkiCache[$cacheKey] ??= $kullanici->can($yetki, FirmaKullanici::class);
    }

    private static function aktifFirmaId(): ?int
    {
        $firmaId = self::$aktifFirmaIdCache ??= app(TenantContextService::class)->aktifFirmaId();

        return $firmaId ? (int) $firmaId : null;
    }

    private static function hizliDuzenlemeModu(): bool
    {
        return filled(request()->route('record')) && ! request()->boolean('detay');
    }
}
