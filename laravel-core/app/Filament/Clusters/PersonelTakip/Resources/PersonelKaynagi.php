<?php

namespace App\Filament\Clusters\PersonelTakip\Resources;

use App\Filament\Clusters\PersonelTakip as PersonelTakipCluster;
use App\Filament\Clusters\PersonelTakip\Kaynaklar\PersonelTakipKaynakErisimi;
use App\Filament\Clusters\PersonelTakip\Resources\PersonelKaynagi\Pages;
use App\Filament\Clusters\PersonelTakip\Resources\PersonelKaynagi\RelationManagers\BelgelerRelationManager;
use App\Models\FirmaKullanici;
use App\Models\Personel\Personel;
use App\Models\Personel\PersonelDepartmani;
use App\Models\Personel\PersonelGorevi;
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

class PersonelKaynagi extends Resource
{
    use PersonelTakipKaynakErisimi;

    /** @var array<string,array<int,string>> */
    private static array $formSecenekCache = [];

    protected static ?string $model = Personel::class;

    protected static ?string $cluster = PersonelTakipCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Personeller';

    protected static ?string $modelLabel = 'Personel';

    protected static ?string $pluralModelLabel = 'Personeller';

    protected static ?string $slug = 'personeller';

    protected static function goruntuleYetkisi(): string
    {
        return PersonelTakipYetkiSablonlari::GORUNTULE;
    }

    protected static function olusturYetkisi(): string
    {
        return PersonelTakipYetkiSablonlari::OLUSTUR;
    }

    protected static function guncelleYetkisi(): string
    {
        return PersonelTakipYetkiSablonlari::GUNCELLE;
    }

    protected static function silYetkisi(): string
    {
        return PersonelTakipYetkiSablonlari::SIL;
    }

    public static function form(Form $form): Form
    {
        $hizliDuzenleme = filled(request()->route('record')) && ! request()->boolean('detay');

        if ($hizliDuzenleme) {
            return $form->schema([
                Forms\Components\Select::make('durum')
                    ->label('Durum')
                    ->options(Personel::durumSecenekleri())
                    ->default('aktif')
                    ->native()
                    ->required(),
            ]);
        }

        return $form->schema([
            Forms\Components\Hidden::make('firma_id')
                ->default(fn (): ?int => app(TenantContextService::class)->aktifFirmaId())
                ->dehydrated(),
            Forms\Components\Section::make('Kimlik ve iletişim')
                ->schema([
                    Forms\Components\TextInput::make('personel_no')->label('Personel no')->maxLength(64),
                    Forms\Components\TextInput::make('ad_soyad')->label('Ad soyad')->required()->maxLength(191),
                    Forms\Components\TextInput::make('tc_kimlik_no')->label('TC / Kimlik no')->maxLength(32),
                    Forms\Components\TextInput::make('telefon')->label('Telefon')->tel()->maxLength(64),
                    Forms\Components\TextInput::make('email')->label('E-posta')->email()->maxLength(191),
                    Forms\Components\Select::make('kullanici_id')
                        ->label('Bağlı kullanıcı')
                        ->options(fn (): array => static::kullaniciSecenekleri())
                        ->searchable()
                        ->preload(),
                    Forms\Components\Textarea::make('adres')->label('Adres')->columnSpanFull(),
                    Forms\Components\TextInput::make('acil_durum_kisi')->label('Acil durum kişisi')->maxLength(191),
                    Forms\Components\TextInput::make('acil_durum_telefon')->label('Acil durum telefonu')->tel()->maxLength(64),
                ])
                ->columns(3),
            Forms\Components\Section::make('Çalışma bilgileri')
                ->schema([
                    Forms\Components\Select::make('sube_id')
                        ->label('Şube')
                        ->options(fn (): array => static::subeSecenekleri())
                        ->searchable()
                        ->preload(),
                    Forms\Components\Select::make('departman_id')
                        ->label('Departman')
                        ->options(fn (): array => static::departmanSecenekleri())
                        ->searchable()
                        ->preload(),
                    Forms\Components\Select::make('gorev_id')
                        ->label('Görev')
                        ->options(fn (): array => static::gorevSecenekleri())
                        ->searchable()
                        ->preload(),
                    Forms\Components\Select::make('calisma_tipi')
                        ->label('Çalışma tipi')
                        ->options(Personel::calismaTipiSecenekleri())
                        ->default('tam_zamanli')
                        ->required(),
                    Forms\Components\Select::make('maas_tipi')
                        ->label('Maaş tipi')
                        ->options(Personel::maasTipiSecenekleri())
                        ->default('aylik')
                        ->required(),
                    Forms\Components\TextInput::make('maas_tutari')
                        ->label('Maaş tutarı')
                        ->numeric()
                        ->default(0),
                    Forms\Components\TextInput::make('gunluk_ucret')
                        ->label('Günlük ücret')
                        ->numeric(),
                    Forms\Components\TextInput::make('saatlik_ucret')
                        ->label('Saatlik ücret')
                        ->numeric(),
                    Forms\Components\DatePicker::make('ise_giris_tarihi')->label('İşe giriş tarihi'),
                    Forms\Components\DatePicker::make('isten_cikis_tarihi')->label('İşten çıkış tarihi'),
                    Forms\Components\TextInput::make('pin_kodu')
                        ->label('Yeni PIN kodu')
                        ->password()
                        ->revealable()
                        ->numeric()
                        ->minLength(4)
                        ->maxLength(12)
                        ->dehydrated(fn ($state): bool => filled($state)),
                    Forms\Components\Select::make('durum')
                        ->label('Durum')
                        ->options(Personel::durumSecenekleri())
                        ->default('aktif')
                        ->required(),
                    Forms\Components\Textarea::make('notlar')->label('Notlar')->columnSpanFull(),
                ])
                ->columns(3),
        ]);
    }

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        if (filled(request()->route('record')) && ! request()->boolean('detay')) {
            return static::getModel()::query()
                ->select([
                    'id',
                    'durum',
                ])
                ->whereKey($key)
                ->first();
        }

        return parent::resolveRecordRouteBinding($key);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->select([
                    'id',
                    'personel_no',
                    'ad_soyad',
                    'sube_id',
                    'departman_id',
                    'gorev_id',
                    'telefon',
                    'calisma_tipi',
                    'durum',
                ])
                ->with([
                    'sube:id,ad',
                    'departman:id,ad',
                    'gorev:id,ad',
                ]))
            ->columns([
                Tables\Columns\TextColumn::make('personel_no')->label('No')->searchable(),
                Tables\Columns\TextColumn::make('ad_soyad')->label('Personel')->searchable(),
                Tables\Columns\TextColumn::make('sube.ad')->label('Şube')->sortable(),
                Tables\Columns\TextColumn::make('departman.ad')->label('Departman')->sortable(),
                Tables\Columns\TextColumn::make('gorev.ad')->label('Görev')->sortable(),
                Tables\Columns\TextColumn::make('telefon')->label('Telefon')->searchable(),
                Tables\Columns\TextColumn::make('calisma_tipi')->label('Çalışma tipi')->badge(),
                Tables\Columns\TextColumn::make('durum')->label('Durum')->badge()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('durum')
                    ->label('Durum')
                    ->options([
                        'aktif' => 'Aktif',
                        'pasif' => 'Pasif',
                        'isten_ayrildi' => 'İşten Ayrıldı',
                        'askida' => 'Askıda',
                    ]),
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
            'index' => Pages\ListPersoneller::route('/'),
            'create' => Pages\CreatePersonel::route('/create'),
            'edit' => Pages\EditPersonel::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        if (filled(request()->route('record')) && ! request()->boolean('detay')) {
            return [];
        }

        return [
            BelgelerRelationManager::class,
        ];
    }

    /**
     * @return array<int,string>
     */
    private static function kullaniciSecenekleri(): array
    {
        $firmaId = static::aktifFirmaId();
        if ($firmaId < 1) {
            return [];
        }

        return static::formSecenekleri('kullanici|'.$firmaId, fn (): array => FirmaKullanici::query()
            ->with('kullanici:id,name,ad_soyad,email')
            ->where('firma_id', $firmaId)
            ->where('durum', 'aktif')
            ->get()
            ->mapWithKeys(function (FirmaKullanici $firmaKullanici): array {
                $kullanici = $firmaKullanici->kullanici;
                $etiket = trim((string) ($kullanici?->ad_soyad ?: $kullanici?->name ?: $kullanici?->email ?: 'Kullanıcı #'.$firmaKullanici->kullanici_id));

                return [$firmaKullanici->kullanici_id => $etiket];
            })
            ->all());
    }

    /**
     * @return array<int,string>
     */
    private static function subeSecenekleri(): array
    {
        return static::formSecenekleri('sube|'.static::aktifFirmaId(), fn (): array => Sube::query()
            ->orderBy('ad')
            ->pluck('ad', 'id')
            ->all());
    }

    /**
     * @return array<int,string>
     */
    private static function departmanSecenekleri(): array
    {
        return static::formSecenekleri('departman|'.static::aktifFirmaId(), fn (): array => PersonelDepartmani::query()
            ->orderBy('ad')
            ->pluck('ad', 'id')
            ->all());
    }

    /**
     * @return array<int,string>
     */
    private static function gorevSecenekleri(): array
    {
        return static::formSecenekleri('gorev|'.static::aktifFirmaId(), fn (): array => PersonelGorevi::query()
            ->orderBy('ad')
            ->pluck('ad', 'id')
            ->all());
    }

    /**
     * @param  callable(): array<int,string>  $olusturucu
     * @return array<int,string>
     */
    private static function formSecenekleri(string $anahtar, callable $olusturucu): array
    {
        if (array_key_exists($anahtar, self::$formSecenekCache)) {
            return self::$formSecenekCache[$anahtar];
        }

        return self::$formSecenekCache[$anahtar] = Cache::remember(
            'personel:personel:form-secenekleri:v1:'.$anahtar,
            now()->addMinutes(5),
            $olusturucu
        );
    }

    private static function aktifFirmaId(): int
    {
        return (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
    }
}
