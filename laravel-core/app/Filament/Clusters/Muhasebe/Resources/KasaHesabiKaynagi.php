<?php

namespace App\Filament\Clusters\Muhasebe\Resources;

use App\Filament\Clusters\Muhasebe;
use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeFilamentKaynakYetkileri;
use App\Filament\Clusters\Muhasebe\Resources\KasaHesabiKaynagi\Pages;
use App\Filament\Clusters\Muhasebe\Resources\KasaHesabiKaynagi\RelationManagers\KasaHareketleriRelationManager;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\ParaBirimi;
use App\Muhasebe\Enumlar\HareketDurumu;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Services\TenantContextService;
use App\Support\KullaniciRolYardimcisi;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Forms;
use Filament\Forms\ComponentContainer;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class KasaHesabiKaynagi extends Resource
{
    use MuhasebeFilamentKaynakYetkileri;

    /** @var array<string, string>|null */
    private static ?array $hesapDurumuSecenekleri = null;

    /** @var array<int, array<string, string>> */
    private static array $paraBirimiSecenekleri = [];

    protected static ?string $model = KasaHesabi::class;

    protected static ?string $cluster = Muhasebe::class;

    protected static ?string $slug = 'finans/kasa-hesaplari';

    protected static bool $shouldRegisterNavigation = false;

    protected static bool $isScopedToTenant = false;

    protected static ?string $modelLabel = 'Kasa hesabı';

    protected static ?string $pluralModelLabel = 'Kasa hesapları';

    protected static ?string $recordTitleAttribute = 'ad';

    protected static function goruntuleYetkisi(): string
    {
        return MuhasebeYetkiSablonlari::FINANS_GORUNTULE;
    }

    protected static function olusturYetkisi(): string
    {
        return MuhasebeYetkiSablonlari::FINANS_OLUSTUR;
    }

    protected static function guncelleYetkisi(): string
    {
        return MuhasebeYetkiSablonlari::FINANS_GUNCELLE;
    }

    protected static function silYetkisi(): string
    {
        return MuhasebeYetkiSablonlari::FINANS_SIL;
    }

    public static function kodBenzersizMi(int $firmaId, string $kod, ?int $haricId = null): bool
    {
        $sorgu = KasaHesabi::query()->where('firma_id', $firmaId)->where('kod', $kod);
        if ($haricId !== null) {
            $sorgu->whereKeyNot($haricId);
        }

        return ! $sorgu->exists();
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();
        $superAdminMi = KullaniciRolYardimcisi::superAdminVeyaIsAdmin($user);
        $fid = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);

        $query = parent::getEloquentQuery()
            ->select([
                'id',
                'firma_id',
                'ad',
                'kod',
                'para_birimi',
                'durum',
                'created_at',
            ])
            ->withSum([
                'kasaHareketleri as aktif_bakiye' => function (Builder $q) use ($superAdminMi, $fid): void {
                    $q->where('durum', HareketDurumu::Aktif);

                    if (! $superAdminMi) {
                        $q->where('firma_id', $fid);
                    }
                },
            ], 'tutar');

        if ($superAdminMi) {
            return $query->with('firma:id,ad');
        }

        if ($fid < 1) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('firma_id', $fid);
    }

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        $query = KasaHesabi::query();

        if (static::hizliGorunumModu() || static::hizliDuzenlemeModu()) {
            $query->select([
                'id',
                'firma_id',
                'ad',
                'kod',
                'para_birimi',
                'durum',
                'sorumlu',
                'aciklama',
                'created_at',
                'updated_at',
            ]);
        } else {
            $query
                ->select([
                'id',
                'firma_id',
                'ad',
                'kod',
                'para_birimi',
                'durum',
                'sorumlu',
                'aciklama',
                'created_at',
                'updated_at',
                ])
                ->with('firma:id,ad')
                ->withSum([
                    'kasaHareketleri as aktif_bakiye' => fn (Builder $q) => $q->where('durum', HareketDurumu::Aktif),
                ], 'tutar');
        }

        $user = Auth::user();
        if (! KullaniciRolYardimcisi::superAdminVeyaIsAdmin($user)) {
            $fid = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);

            if ($fid < 1) {
                return null;
            }

            $query->where('firma_id', $fid);
        }

        return app(static::getModel())
            ->resolveRouteBindingQuery($query, $key, static::getRecordRouteKeyName())
            ->first();
    }

    /**
     * @return array<string, string>
     */
    public static function paraBirimiSecenekleriForFirma(int $firmaId): array
    {
        if ($firmaId < 1) {
            return [];
        }

        return self::$paraBirimiSecenekleri[$firmaId] ??= ParaBirimi::query()
            ->gorunurFirmaIle($firmaId)
            ->where('aktif_mi', true)
            ->orderBy('kod')
            ->get()
            ->mapWithKeys(function (ParaBirimi $kayit): array {
                $kod = strtoupper((string) $kayit->kod);
                $etiket = $kod.($kayit->ad ? ' - '.$kayit->ad : '');

                return [$kod => $etiket];
            })
            ->all();
    }

    public static function form(Form $form): Form
    {
        $superAdminMi = KullaniciRolYardimcisi::superAdminVeyaIsAdmin(Auth::user());

        return $form->schema([
            Forms\Components\Section::make('Temel bilgiler')
                ->schema([
                    Forms\Components\Select::make('firma_id')
                        ->label('Firma')
                        ->relationship('firma', 'ad', fn (Builder $q) => $q->orderBy('ad'))
                        ->searchable()
                        ->required($superAdminMi)
                        ->visible($superAdminMi)
                        ->live()
                        ->default(fn () => app(TenantContextService::class)->aktifFirmaId())
                        ->dehydrated(fn () => $superAdminMi)
                        ->afterStateUpdated(function (Forms\Set $set, $state) use ($superAdminMi): void {
                            if (! $superAdminMi) {
                                return;
                            }

                            $fid = (int) $state;
                            if ($fid < 1) {
                                $set('para_birimi', null);

                                return;
                            }

                            $secenekler = static::paraBirimiSecenekleriForFirma($fid);
                            $set('para_birimi', array_key_exists('TRY', $secenekler) ? 'TRY' : ($secenekler === [] ? null : array_key_first($secenekler)));
                        })
                        ->helperText(fn () => $superAdminMi ? null : 'Firma oturumdaki aktif firmadan atanır; değiştirilemez.'),
                    Forms\Components\TextInput::make('ad')
                        ->label('Ad')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('kod')
                        ->label('Kod')
                        ->hiddenOn('create')
                        ->required(false)
                        ->maxLength(64)
                        ->helperText('Firma içinde benzersiz tanımlayıcı.'),
                    Forms\Components\Select::make('durum')
                        ->label('Durum')
                        ->options(fn (): array => static::hesapDurumuSecenekleri())
                        ->required()
                        ->default(HesapDurumu::Aktif->value),
                    Forms\Components\Select::make('para_birimi')
                        ->label('Para birimi')
                        ->options(function (Get $get) use ($superAdminMi): array {
                            $fid = $superAdminMi
                                ? (int) ($get('firma_id') ?: 0)
                                : (int) (app(TenantContextService::class)->aktifFirmaId() ?: 0);

                            return static::paraBirimiSecenekleriForFirma($fid);
                        })
                        ->default(function (Get $get) use ($superAdminMi): ?string {
                            $fid = $superAdminMi
                                ? (int) ($get('firma_id') ?: 0)
                                : (int) (app(TenantContextService::class)->aktifFirmaId() ?: 0);
                            $secenekler = static::paraBirimiSecenekleriForFirma($fid);
                            if (array_key_exists('TRY', $secenekler)) {
                                return 'TRY';
                            }

                            return $secenekler === [] ? null : array_key_first($secenekler);
                        })
                        ->required()
                        ->searchable()
                        ->disabled(fn (Get $get) => $superAdminMi && (int) ($get('firma_id') ?: 0) < 1)
                        ->dehydrateStateUsing(fn (?string $state) => $state ? strtoupper($state) : $state)
                        ->createOptionForm([
                            Forms\Components\TextInput::make('kod')
                                ->label('Kod')
                                ->required()
                                ->maxLength(3)
                                ->minLength(3)
                                ->extraInputAttributes(['style' => 'text-transform:uppercase']),
                            Forms\Components\TextInput::make('ad')
                                ->label('Ad')
                                ->maxLength(64),
                        ])
                        ->createOptionUsing(function (array $data, ComponentContainer $form) use ($superAdminMi): string {
                            $firmaId = (int) (data_get($form->getRawState(), 'firma_id') ?: app(TenantContextService::class)->aktifFirmaId() ?? 0);
                            if (! $superAdminMi) {
                                $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
                            }
                            if ($firmaId < 1) {
                                throw ValidationException::withMessages([
                                    'para_birimi' => 'Önce firma seçin veya aktif firma oturumu açın.',
                                ]);
                            }

                            $kod = Str::upper(trim((string) ($data['kod'] ?? '')));
                            $ad = trim((string) ($data['ad'] ?? ''));
                            if (strlen($kod) !== 3 || ! ctype_alpha($kod)) {
                                throw ValidationException::withMessages([
                                    'kod' => 'Kod tam 3 harf olmalıdır (örn. TRY).',
                                ]);
                            }

                            $kapsam = $firmaId;
                            $var = ParaBirimi::tenantScopeOlmadan(fn () => ParaBirimi::query()
                                ->where('tanim_firma_kapsami', $kapsam)
                                ->whereRaw('UPPER(kod) = ?', [$kod])
                                ->exists());
                            if ($var) {
                                throw ValidationException::withMessages([
                                    'kod' => 'Bu para birimi kodu bu firma için zaten tanımlı.',
                                ]);
                            }

                            ParaBirimi::query()->create([
                                'firma_id' => $firmaId,
                                'kod' => $kod,
                                'ad' => ($ad !== '' ? $ad : null),
                                'aktif_mi' => true,
                                'is_sabit' => false,
                            ]);

                            return $kod;
                        })
                        ->helperText(
                            fn (): HtmlString => new HtmlString(
                                'Tanımlar: <a href="'.e(ParaBirimiTanimKaynagi::getUrl()).'" target="_blank" rel="noopener" class="text-primary-600 underline">Para birimleri</a>'
                            )
                        ),
                    Forms\Components\TextInput::make('sorumlu')
                        ->label('Sorumlu')
                        ->maxLength(191),
                    Forms\Components\Textarea::make('aciklama')
                        ->label('Açıklama')
                        ->rows(3)
                        ->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('firma.ad')
                    ->label('Firma')
                    ->searchable()
                    ->sortable()
                    ->visible(fn () => KullaniciRolYardimcisi::superAdminVeyaIsAdmin(Auth::user()))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('ad')
                    ->label('Ad')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('kod')
                    ->label('Kod')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('para_birimi')
                    ->label('Para birimi')
                    ->sortable(),
                Tables\Columns\TextColumn::make('aktif_bakiye')
                    ->label('Bakiye (aktif)')
                    ->formatStateUsing(function ($state, KasaHesabi $record) {
                        $t = (float) ($state ?? 0);

                        return number_format($t, 2, ',', '.').' '.($record->para_birimi ?? '');
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('durum')
                    ->label('Durum')
                    ->badge()
                    ->formatStateUsing(fn (?HesapDurumu $state) => match ($state) {
                        HesapDurumu::Aktif => 'Aktif',
                        HesapDurumu::Pasif => 'Pasif',
                        default => '—',
                    })
                    ->color(fn (?HesapDurumu $state) => match ($state) {
                        HesapDurumu::Aktif => 'success',
                        HesapDurumu::Pasif => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Oluşturulma')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('durum')
                    ->label('Durum')
                    ->options([
                        HesapDurumu::Aktif->value => 'Aktif',
                        HesapDurumu::Pasif->value => 'Pasif',
                    ])
                    ->placeholder('Tümü'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->label('Sil')
                    ->visible(fn (KasaHesabi $record): bool => ! DB::table('kasa_hareketleri')->where('kasa_hesap_id', (int) $record->getKey())->exists()),
                Tables\Actions\Action::make('pasiflestir')
                    ->label('Pasifleştir')
                    ->icon('heroicon-o-archive-box')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Kasa hesabı pasifleştirilsin mi?')
                    ->modalDescription('Hesap korunur; yeni işlemlerde seçilemez, geçmiş hareketler etkilenmez.')
                    ->visible(fn (KasaHesabi $record): bool => $record->durum === HesapDurumu::Aktif
                        && DB::table('kasa_hareketleri')->where('kasa_hesap_id', (int) $record->getKey())->exists())
                    ->action(fn (KasaHesabi $record): bool => (bool) $record->update(['durum' => HesapDurumu::Pasif])),
            ])
            ->bulkActions([])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKasaHesaplari::route('/'),
            'create' => Pages\CreateKasaHesabi::route('/create'),
            'view' => Pages\ViewKasaHesabi::route('/{record}'),
            'edit' => Pages\EditKasaHesabi::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        if (! static::detayModu()) {
            return [];
        }

        return [
            KasaHareketleriRelationManager::class,
        ];
    }

    public static function detayModu(): bool
    {
        $routeName = (string) (request()->route()?->getName() ?? '');

        return request()->boolean('detay') || str_ends_with($routeName, '.view');
    }

    private static function hizliGorunumModu(): bool
    {
        $routeName = (string) (request()->route()?->getName() ?? '');

        return str_ends_with($routeName, '.view') && ! static::detayModu();
    }

    public static function hizliDuzenlemeModu(): bool
    {
        $routeName = (string) (request()->route()?->getName() ?? '');

        return str_ends_with($routeName, '.edit') && ! static::detayModu();
    }

    /**
     * @return array<string,string>
     */
    private static function hesapDurumuSecenekleri(): array
    {
        return self::$hesapDurumuSecenekleri ??= collect(HesapDurumu::cases())
            ->mapWithKeys(fn (HesapDurumu $d): array => [$d->value => match ($d) {
                HesapDurumu::Aktif => 'Aktif',
                HesapDurumu::Pasif => 'Pasif',
            }])
            ->all();
    }
}
