<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar;

use App\Filament\Clusters\Muhasebe;
use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeFilamentKaynakYetkileri;
use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\DovizKuruTanimKaynagi\Pages;
use App\Models\Firma;
use App\Models\Muhasebe\DovizKuru;
use App\Models\Muhasebe\ParaBirimi;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Servisler\DovizKurServisi;
use App\Services\TenantContextService;
use App\Support\KullaniciRolYardimcisi;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Notifications\Notification;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class DovizKuruTanimKaynagi extends Resource
{
    use MuhasebeFilamentKaynakYetkileri;

    protected static ?string $model = DovizKuru::class;

    protected static ?string $cluster = Muhasebe::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static bool $isScopedToTenant = false;

    protected static ?string $slug = 'tanimlar/doviz-kurlari';

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $modelLabel = 'Doviz kuru';

    protected static ?string $pluralModelLabel = 'Doviz kurlari';

    protected static function goruntuleYetkisi(): string
    {
        return MuhasebeYetkiSablonlari::TANIM_GORUNTULE;
    }

    protected static function olusturYetkisi(): string
    {
        return MuhasebeYetkiSablonlari::TANIM_GUNCELLE;
    }

    protected static function guncelleYetkisi(): string
    {
        return MuhasebeYetkiSablonlari::TANIM_GUNCELLE;
    }

    protected static function silYetkisi(): string
    {
        return MuhasebeYetkiSablonlari::TANIM_GUNCELLE;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->select([
                'id',
                'firma_id',
                'is_sabit',
                'tarih',
                'kaynak_para_birimi',
                'hedef_para_birimi',
                'kur',
                'saglayici',
                'manuel_mi',
                'updated_at',
            ])
            ->with('firma:id,ad')
            ->orderByDesc('tarih')
            ->orderBy('kaynak_para_birimi')
            ->orderBy('hedef_para_birimi');
    }

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        if (static::hizliDuzenlemeModu()) {
            return static::getModel()::query()
                ->select([
                    'id',
                    'firma_id',
                    'is_sabit',
                    'tarih',
                    'kaynak_para_birimi',
                    'hedef_para_birimi',
                    'kur',
                    'saglayici',
                    'manuel_mi',
                ])
                ->whereKey($key)
                ->first();
        }

        return parent::resolveRecordRouteBinding($key);
    }

    public static function form(Form $form): Form
    {
        $superAdminMi = KullaniciRolYardimcisi::superAdminVeyaIsAdmin(Auth::user());

        if (static::hizliDuzenlemeModu()) {
            return $form->schema([
                Forms\Components\TextInput::make('kur')
                    ->label('Kur')
                    ->numeric()
                    ->minValue(0.00000001)
                    ->step(0.00000001)
                    ->required(),
                Forms\Components\Checkbox::make('manuel_mi')
                    ->label('Manuel giris')
                    ->default(true),
            ]);
        }

        return $form->schema([
            Forms\Components\Section::make('Kur bilgisi')
                ->schema([
                    Forms\Components\Toggle::make('is_sabit')
                        ->label('Sabit tanim')
                        ->helperText('Sabit ise tum firmalar gorur. Firma ozel kayitlar sabitin uzerine yazar.')
                        ->default(false)
                        ->live()
                        ->visible($superAdminMi),
                    Forms\Components\Hidden::make('firma_id')
                        ->default(fn () => app(TenantContextService::class)->aktifFirmaId())
                        ->visible(fn () => ! $superAdminMi),
                    Forms\Components\Select::make('firma_id')
                        ->label('Firma')
                        ->options(fn (): array => Firma::query()->orderBy('ad')->pluck('ad', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->required(fn (Get $get): bool => $superAdminMi && ! (bool) ($get('is_sabit') ?? false))
                        ->visible(fn (Get $get): bool => $superAdminMi && ! (bool) ($get('is_sabit') ?? false))
                        ->default(fn () => app(TenantContextService::class)->aktifFirmaId()),
                    Forms\Components\Select::make('kaynak_para_birimi')
                        ->label('Kaynak para birimi')
                        ->options(fn (): array => static::paraBirimiSecenekleri())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->createOptionForm(static::paraBirimiHizliEklemeFormu())
                        ->createOptionUsing(fn (array $data): string => static::paraBirimiHizliEklemeKaydet($data)),
                    Forms\Components\Select::make('hedef_para_birimi')
                        ->label('Hedef para birimi')
                        ->options(fn (): array => static::paraBirimiSecenekleri())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->createOptionForm(static::paraBirimiHizliEklemeFormu())
                        ->createOptionUsing(fn (array $data): string => static::paraBirimiHizliEklemeKaydet($data)),
                    Forms\Components\DatePicker::make('tarih')
                        ->label('Kur tarihi')
                        ->default(now()->toDateString())
                        ->required()
                        ->native(false),
                    Forms\Components\Select::make('saglayici')
                        ->label('Saglayici')
                        ->options([
                            'manuel' => 'Manuel',
                            'tcmb' => 'TCMB',
                            'sistem' => 'Sistem',
                        ])
                        ->default('manuel')
                        ->required(),
                    Forms\Components\TextInput::make('kur')
                        ->label('Kur')
                        ->numeric()
                        ->minValue(0.00000001)
                        ->step(0.00000001)
                        ->required()
                        ->suffixAction(
                            Forms\Components\Actions\Action::make('otomatik_cek')
                                ->label('Otomatik çek')
                                ->icon('heroicon-m-arrow-down-tray')
                                ->action(function (Get $get, Set $set): void {
                                    try {
                                        $firmaId = (int) (($get('firma_id') ?? app(TenantContextService::class)->aktifFirmaId()) ?? 0);
                                        $sabitMi = (bool) ($get('is_sabit') ?? false);
                                        if (! $sabitMi && $firmaId < 1) {
                                            throw new IsKuraliIstisnasi('Firma secilmeden otomatik kur cekilemez.');
                                        }
                                        $kaynak = (string) ($get('kaynak_para_birimi') ?? '');
                                        $hedef = (string) ($get('hedef_para_birimi') ?? '');
                                        $tarih = (string) ($get('tarih') ?? now()->toDateString());
                                        if ($kaynak === '' || $hedef === '') {
                                            throw new IsKuraliIstisnasi('Once kaynak ve hedef para birimi secilmelidir.');
                                        }

                                        $sonuc = app(DovizKurServisi::class)->otomatikKurGetir($kaynak, $hedef, $tarih);
                                        $set('kur', $sonuc['kur']);
                                        $set('tarih', $sonuc['tarih']);
                                        $set('manuel_mi', false);
                                        $set('saglayici', 'tcmb');
                                        $set('aciklama', $sonuc['aciklama']);

                                        Notification::make()->title('Kur otomatik cekildi')->success()->send();
                                    } catch (\Throwable $e) {
                                        Notification::make()->title('Kur cekilemedi')->body($e->getMessage())->danger()->send();
                                    }
                                })
                        ),
                    Forms\Components\Toggle::make('manuel_mi')
                        ->label('Manuel giris')
                        ->default(true)
                        ->live()
                        ->afterStateUpdated(function (Set $set, $state): void {
                            $set('saglayici', $state ? 'manuel' : 'tcmb');
                        }),
                    Forms\Components\Textarea::make('aciklama')
                        ->label('Aciklama')
                        ->rows(2)
                        ->maxLength(1000),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        $superAdminMi = KullaniciRolYardimcisi::superAdminVeyaIsAdmin(Auth::user());

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('firma.ad')
                    ->label('Firma')
                    ->placeholder('Sabit')
                    ->visible($superAdminMi)
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_sabit')->label('Sabit')->boolean(),
                Tables\Columns\TextColumn::make('tarih')->label('Tarih')->date('d.m.Y')->sortable(),
                Tables\Columns\TextColumn::make('kaynak_para_birimi')->label('Kaynak')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('hedef_para_birimi')->label('Hedef')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('kur')->label('Kur')->numeric(decimalPlaces: 8)->sortable(),
                Tables\Columns\TextColumn::make('saglayici')->label('Saglayici')->badge(),
                Tables\Columns\IconColumn::make('manuel_mi')->label('Manuel')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->label('Guncellendi')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('manuel_mi')
                    ->label('Kaynak')
                    ->placeholder('Tumu')
                    ->trueLabel('Manuel')
                    ->falseLabel('Otomatik'),
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
            'index' => Pages\ListDovizKurlari::route('/'),
            'create' => Pages\CreateDovizKuru::route('/create'),
            'edit' => Pages\EditDovizKuru::route('/{record}/edit'),
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

    /**
     * @return array<string, string>
     */
    private static function paraBirimiSecenekleri(): array
    {
        return ParaBirimi::query()
            ->where('aktif_mi', true)
            ->orderBy('kod')
            ->get(['kod', 'ad'])
            ->mapWithKeys(function (ParaBirimi $pb): array {
                $kod = strtoupper((string) $pb->kod);
                $ad = trim((string) ($pb->ad ?? ''));

                return [$kod => $ad !== '' ? $kod.' - '.$ad : $kod];
            })
            ->all();
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private static function paraBirimiHizliEklemeFormu(): array
    {
        return [
            Forms\Components\TextInput::make('kod')
                ->label('Kod')
                ->required()
                ->minLength(3)
                ->maxLength(3)
                ->dehydrateStateUsing(fn (?string $state): ?string => $state ? Str::upper(trim($state)) : null),
            Forms\Components\TextInput::make('ad')
                ->label('Ad')
                ->maxLength(64),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function paraBirimiHizliEklemeKaydet(array $data): string
    {
        $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
        if ($firmaId < 1) {
            throw new IsKuraliIstisnasi('Aktif firma secilmeden para birimi eklenemez.');
        }

        $kod = Str::upper(trim((string) ($data['kod'] ?? '')));
        if (strlen($kod) !== 3 || ! ctype_alpha($kod)) {
            throw new IsKuraliIstisnasi('Kod tam 3 harf olmalidir.');
        }

        $kayit = ParaBirimi::tenantScopeOlmadan(function () use ($firmaId, $kod, $data): ParaBirimi {
            /** @var ParaBirimi $varOlan */
            $varOlan = ParaBirimi::query()
                ->where('tanim_firma_kapsami', $firmaId)
                ->whereRaw('UPPER(kod) = ?', [$kod])
                ->firstOrNew();
            if (! $varOlan->exists) {
                $varOlan->firma_id = $firmaId;
                $varOlan->is_sabit = false;
            }
            $varOlan->kod = $kod;
            $varOlan->ad = trim((string) ($data['ad'] ?? ''));
            $varOlan->aktif_mi = true;
            $varOlan->save();

            return $varOlan;
        });

        return strtoupper((string) $kayit->kod);
    }
}
