<?php

namespace App\Filament\Clusters\Muhasebe\Resources;

use App\Filament\Clusters\Muhasebe;
use App\Filament\Clusters\Muhasebe\Resources\PosHesabiKaynagi\Pages;
use App\Filament\Clusters\Muhasebe\Resources\PosHesabiKaynagi\RelationManagers\PosHareketleriRelationManager;
use App\Models\Muhasebe\BankaHesabi;
use App\Models\Muhasebe\ParaBirimi;
use App\Models\Muhasebe\PosHesabi;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\HareketDurumu;
use App\Muhasebe\Enumlar\PosTipi;
use App\Muhasebe\Enumlar\SaglayiciTipi;
use App\Services\TenantContextService;
use App\Support\KullaniciRolYardimcisi;
use Filament\Forms;
use Filament\Forms\ComponentContainer;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PosHesabiKaynagi extends Resource
{
    /** @var array<string, string>|null */
    private static ?array $hesapDurumuSecenekleri = null;

    /** @var array<string, string>|null */
    private static ?array $posTipiSecenekleri = null;

    /** @var array<string, string>|null */
    private static ?array $saglayiciTipiSecenekleri = null;

    /** @var array<int, array<string, string>> */
    private static array $paraBirimiSecenekleri = [];

    /** @var array<int, array<int, string>> */
    private static array $bankaHesabiSecenekleri = [];

    protected static ?string $model = PosHesabi::class;

    protected static ?string $cluster = Muhasebe::class;

    protected static ?string $slug = 'finans/poslar';

    protected static bool $shouldRegisterNavigation = false;

    protected static bool $isScopedToTenant = false;

    protected static ?string $modelLabel = 'POS hesabı';

    protected static ?string $pluralModelLabel = 'POS’lar';

    protected static ?string $recordTitleAttribute = 'ad';

    /**
     * Firma bazlı banka hesabı: seçilen kayıt aynı firmaya ait olmalı.
     *
     * @param  array<string, mixed>  $veri
     */
    public static function dogrulaBankaHesabiFirma(int $firmaId, array $veri): void
    {
        $bid = $veri['banka_hesabi_id'] ?? null;
        if ($bid === null || $bid === '') {
            return;
        }

        $bid = (int) $bid;
        if ($bid < 1) {
            return;
        }

        $uygun = BankaHesabi::query()
            ->whereKey($bid)
            ->where('firma_id', $firmaId)
            ->exists();

        if (! $uygun) {
            throw ValidationException::withMessages([
                'banka_hesabi_id' => 'Seçilen banka hesabı bu firmaya ait değil.',
            ]);
        }
    }

    public static function kodBenzersizMi(int $firmaId, string $kod, ?int $haricId = null): bool
    {
        $sorgu = PosHesabi::query()->where('firma_id', $firmaId)->where('kod', $kod);
        if ($haricId !== null) {
            $sorgu->whereKeyNot($haricId);
        }

        return ! $sorgu->exists();
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

    /**
     * @return array<int, string>
     */
    private static function bankaHesabiSecenekleriForFirma(int $firmaId): array
    {
        if ($firmaId < 1) {
            return [];
        }

        return self::$bankaHesabiSecenekleri[$firmaId] ??= BankaHesabi::query()
            ->where('firma_id', $firmaId)
            ->orderBy('ad')
            ->pluck('ad', 'id')
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
                ])->columns(2),

            Forms\Components\Section::make('POS türü')
                ->schema([
                    Forms\Components\Select::make('pos_tipi')
                        ->label('POS tipi')
                        ->options(fn (): array => static::posTipiSecenekleri())
                        ->required()
                        ->default(PosTipi::FizikiPos->value),
                ])->columns(2),

            Forms\Components\Section::make('Sağlayıcı')
                ->schema([
                    Forms\Components\Select::make('saglayici_tipi')
                        ->label('Sağlayıcı tipi')
                        ->options(fn (): array => static::saglayiciTipiSecenekleri())
                        ->required()
                        ->default(SaglayiciTipi::BankaPosu->value)
                        ->live(),
                    Forms\Components\Select::make('banka_hesabi_id')
                        ->label('Banka hesabı')
                        ->options(function (Forms\Get $get, $livewire): array {
                            $fid = (int) ($get('firma_id') ?? $livewire->record?->firma_id ?? app(TenantContextService::class)->aktifFirmaId() ?? 0);
                            if ($fid < 1) {
                                return [];
                            }

                            return static::bankaHesabiSecenekleriForFirma($fid);
                        })
                        ->searchable()
                        ->nullable()
                        ->visible(fn (Forms\Get $get) => $get('saglayici_tipi') === SaglayiciTipi::BankaPosu->value)
                        ->afterStateUpdated(function ($state, Forms\Set $set): void {
                            if (! $state) {
                                return;
                            }
                            $b = BankaHesabi::query()->find($state);
                            if ($b) {
                                $set('banka_adi', $b->banka_adi ?? $b->ad);
                            }
                        }),
                    Forms\Components\TextInput::make('banka_adi')
                        ->label('Banka adı')
                        ->maxLength(191)
                        ->visible(fn (Forms\Get $get) => $get('saglayici_tipi') === SaglayiciTipi::BankaPosu->value),
                    Forms\Components\TextInput::make('saglayici_adi')
                        ->label('Sağlayıcı adı')
                        ->maxLength(191)
                        ->visible(fn (Forms\Get $get) => $get('saglayici_tipi') === SaglayiciTipi::OdemeKurulusu->value),
                ])->columns(2),

            ...([
            Forms\Components\Section::make('Bağlantı')
                ->schema([
                    Forms\Components\TextInput::make('terminal_no')
                        ->label('Terminal no')
                        ->maxLength(64),
                    Forms\Components\TextInput::make('uye_isyeri_no')
                        ->label('Üye işyeri no')
                        ->maxLength(64),
                    Forms\Components\TextInput::make('magaza_kodu')
                        ->label('Mağaza kodu')
                        ->maxLength(64),
                    Forms\Components\TextInput::make('sanal_pos_no')
                        ->label('Sanal POS no')
                        ->maxLength(64),
                ])->columns(2),

            Forms\Components\Section::make('Finans')
                ->schema([
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
                    Forms\Components\TextInput::make('komisyon_orani')
                        ->label('Komisyon oranı (%)')
                        ->numeric()
                        ->step('0.0001')
                        ->suffix('%'),
                    Forms\Components\TextInput::make('sabit_komisyon_tutari')
                        ->label('Sabit komisyon tutarı')
                        ->numeric()
                        ->prefix('₺'),
                    Forms\Components\TextInput::make('bloke_gun_sayisi')
                        ->label('Bloke gün sayısı')
                        ->numeric()
                        ->minValue(0),
                    Forms\Components\TextInput::make('valor_gun_sayisi')
                        ->label('Valör gün sayısı')
                        ->numeric()
                        ->minValue(0),
                    Forms\Components\Toggle::make('erken_odeme_destegi_var_mi')
                        ->label('Erken ödeme desteği var mı?')
                        ->default(false),
                ])->columns(2),

            Forms\Components\Section::make('Taksit')
                ->schema([
                    Forms\Components\Toggle::make('taksit_destegi_var_mi')
                        ->label('Taksit desteği var mı?')
                        ->default(false),
                    Forms\Components\TextInput::make('maksimum_taksit_sayisi')
                        ->label('Maksimum taksit sayısı')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(36),
                    Forms\Components\Toggle::make('tek_cekim_destegi_var_mi')
                        ->label('Tek çekim desteği var mı?')
                        ->default(true),
                ])->columns(2),

            Forms\Components\Section::make('Yönetim')
                ->schema([
                    Forms\Components\Toggle::make('varsayilan_mi')
                        ->label('Varsayılan POS')
                        ->helperText('İşaretlenirse bu firma için diğer POS’ların varsayılanı kaldırılır.')
                        ->default(false),
                    Forms\Components\Textarea::make('aciklama')
                        ->label('Açıklama')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
            ]),
        ]);
    }

    public static function detayModu(): bool
    {
        $routeName = (string) (request()->route()?->getName() ?? '');

        return request()->boolean('detay') || str_ends_with($routeName, '.view');
    }

    public static function getRelations(): array
    {
        return static::detayModu() ? [PosHareketleriRelationManager::class] : [];
    }

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        if (static::hizliDuzenlemeModu()) {
            return static::getEloquentQuery()
                ->whereKey($key)
                ->select([
                    'id',
                    'firma_id',
                    'kod',
                    'ad',
                    'pos_tipi',
                    'saglayici_tipi',
                    'banka_hesabi_id',
                    'banka_adi',
                    'saglayici_adi',
                    'terminal_no',
                    'uye_isyeri_no',
                    'magaza_kodu',
                    'sanal_pos_no',
                    'para_birimi',
                    'komisyon_orani',
                    'sabit_komisyon_tutari',
                    'bloke_gun_sayisi',
                    'valor_gun_sayisi',
                    'erken_odeme_destegi_var_mi',
                    'taksit_destegi_var_mi',
                    'maksimum_taksit_sayisi',
                    'tek_cekim_destegi_var_mi',
                    'varsayilan_mi',
                    'aciklama',
                    'durum',
                ])
                ->first();
        }

        if (static::hizliGorunumModu()) {
            return static::getEloquentQuery()
                ->whereKey($key)
                ->select([
                    'id',
                    'firma_id',
                    'kod',
                    'ad',
                    'pos_tipi',
                    'saglayici_tipi',
                    'banka_adi',
                    'saglayici_adi',
                    'durum',
                ])
                ->first();
        }

        return static::getEloquentQuery()
            ->whereKey($key)
            ->select([
                'id',
                'firma_id',
                'kod',
                'ad',
                'pos_tipi',
                'saglayici_tipi',
                'banka_hesabi_id',
                'banka_adi',
                'saglayici_adi',
                'terminal_no',
                'uye_isyeri_no',
                'magaza_kodu',
                'sanal_pos_no',
                'pos_saglayici',
                'para_birimi',
                'komisyon_orani',
                'sabit_komisyon_tutari',
                'bloke_gun_sayisi',
                'valor_gun_sayisi',
                'erken_odeme_destegi_var_mi',
                'taksit_destegi_var_mi',
                'maksimum_taksit_sayisi',
                'tek_cekim_destegi_var_mi',
                'varsayilan_mi',
                'aciklama',
                'durum',
                'created_at',
                'updated_at',
            ])
            ->with(['firma:id,ad', 'bankaHesabi:id,ad'])
            ->first();
    }

    public static function hizliGorunumModu(): bool
    {
        $routeName = (string) (request()->route()?->getName() ?? '');

        return str_ends_with($routeName, '.view') && ! static::detayModu();
    }

    public static function hizliDuzenlemeModu(): bool
    {
        $routeName = (string) (request()->route()?->getName() ?? '');

        return str_ends_with($routeName, '.edit') && ! static::detayModu();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->select([
                    'id',
                    'firma_id',
                    'ad',
                    'kod',
                    'pos_tipi',
                    'saglayici_tipi',
                    'banka_adi',
                    'saglayici_adi',
                    'pos_saglayici',
                    'para_birimi',
                    'komisyon_orani',
                    'valor_gun_sayisi',
                    'durum',
                    'varsayilan_mi',
                    'created_at',
                ])
                ->withSum([
                    'posHareketleri as aktif_bakiye' => fn (Builder $q) => $q->where('durum', HareketDurumu::Aktif),
                ], 'tutar'))
            ->columns([
                Tables\Columns\TextColumn::make('ad')
                    ->label('Ad')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('kod')
                    ->label('Kod')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('pos_tipi')
                    ->label('POS tipi')
                    ->formatStateUsing(fn (?PosTipi $state) => $state?->etiket() ?? '—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('saglayici_tipi')
                    ->label('Sağlayıcı tipi')
                    ->formatStateUsing(fn (?SaglayiciTipi $state) => $state?->etiket() ?? '—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('banka_saglayici')
                    ->label('Banka / sağlayıcı')
                    ->getStateUsing(fn (PosHesabi $kayit) => $kayit->bankaVeyaSaglayiciGorunenAdi()),
                Tables\Columns\TextColumn::make('para_birimi')
                    ->label('Para birimi')
                    ->sortable(),
                Tables\Columns\TextColumn::make('aktif_bakiye')
                    ->label('Bakiye (aktif)')
                    ->formatStateUsing(function ($state, PosHesabi $record): string {
                        return number_format((float) ($state ?? 0), 2, ',', '.').' '.($record->para_birimi ?? '');
                    })
                    ->placeholder(fn (PosHesabi $record): string => number_format(0, 2, ',', '.').' '.($record->para_birimi ?? ''))
                    ->sortable(),
                Tables\Columns\TextColumn::make('komisyon_orani')
                    ->label('Komisyon %')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),
                Tables\Columns\TextColumn::make('valor_gun_sayisi')
                    ->label('Valör (gün)')
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
                Tables\Columns\IconColumn::make('varsayilan_mi')
                    ->label('Varsayılan')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Oluşturulma')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('pos_tipi')
                    ->label('POS tipi')
                    ->options(fn (): array => static::posTipiSecenekleri())
                    ->placeholder('Tümü'),
                Tables\Filters\SelectFilter::make('durum')
                    ->label('Durum')
                    ->options([
                        HesapDurumu::Aktif->value => 'Aktif',
                        HesapDurumu::Pasif->value => 'Pasif',
                    ])
                    ->placeholder('Tümü'),
                Tables\Filters\TernaryFilter::make('varsayilan_mi')
                    ->label('Varsayılan')
                    ->placeholder('Tümü')
                    ->trueLabel('Varsayılan olanlar')
                    ->falseLabel('Varsayılan olmayanlar'),
                Tables\Filters\SelectFilter::make('saglayici_tipi')
                    ->label('Sağlayıcı tipi')
                    ->options(fn (): array => static::saglayiciTipiSecenekleri())
                    ->placeholder('Tümü'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->label('Sil')
                    ->visible(fn (PosHesabi $record): bool => ! DB::table('pos_hareketleri')->where('pos_hesap_id', (int) $record->getKey())->exists()),
                Tables\Actions\Action::make('pasiflestir')
                    ->label('Pasifleştir')
                    ->icon('heroicon-o-archive-box')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('POS hesabı pasifleştirilsin mi?')
                    ->modalDescription('Hesap korunur; yeni işlemlerde seçilemez, geçmiş hareketler etkilenmez.')
                    ->visible(fn (PosHesabi $record): bool => $record->durum === HesapDurumu::Aktif
                        && DB::table('pos_hareketleri')->where('pos_hesap_id', (int) $record->getKey())->exists())
                    ->action(fn (PosHesabi $record): bool => (bool) $record->update(['durum' => HesapDurumu::Pasif])),
            ])
            ->bulkActions([])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    /**
     * @return array<string, string>
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

    /**
     * @return array<string, string>
     */
    private static function posTipiSecenekleri(): array
    {
        return self::$posTipiSecenekleri ??= collect(PosTipi::cases())
            ->mapWithKeys(fn (PosTipi $e): array => [$e->value => $e->etiket()])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function saglayiciTipiSecenekleri(): array
    {
        return self::$saglayiciTipiSecenekleri ??= collect(SaglayiciTipi::cases())
            ->mapWithKeys(fn (SaglayiciTipi $e): array => [$e->value => $e->etiket()])
            ->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPosHesaplari::route('/'),
            'create' => Pages\CreatePosHesabi::route('/create'),
            'view' => Pages\ViewPosHesabi::route('/{record}'),
            'edit' => Pages\EditPosHesabi::route('/{record}/edit'),
        ];
    }
}
