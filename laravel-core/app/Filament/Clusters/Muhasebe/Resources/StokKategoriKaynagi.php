<?php

namespace App\Filament\Clusters\Muhasebe\Resources;

use App\Filament\Clusters\Muhasebe\Resources\StokKategoriKaynagi\Pages;
use App\Models\Firma;
use App\Models\Muhasebe\StokKategorisi;
use App\Muhasebe\Filament\AbstractKaynaklar\StokKategoriKaynagi as AbstractStokKategoriKaynagi;
use App\Services\TenantContextService;
use App\Support\KullaniciRolYardimcisi;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class StokKategoriKaynagi extends AbstractStokKategoriKaynagi
{
    private const MAX_HIYERARSI_DERINLIK = 4;

    /** @var array<string, array<int, string>> */
    private static array $formSecenekCache = [];

    protected static ?string $slug = 'stok/stok-kategorileri';

    protected static bool $isScopedToTenant = false;

    protected static ?string $modelLabel = 'Stok kategorisi';

    protected static ?string $pluralModelLabel = 'Stok kategorileri';

    protected static ?string $recordTitleAttribute = 'ad';

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        if (static::hizliDuzenlemeModu()) {
            return static::getModel()::query()
                ->select([
                    'id',
                    'firma_id',
                    'ad',
                    'is_sabit',
                ])
                ->whereKey($key)
                ->first();
        }

        return static::getModel()::query()
            ->select([
                'id',
                'firma_id',
                'parent_id',
                'kod',
                'ad',
                'aciklama',
                'aktif_mi',
                'is_sabit',
            ])
            ->whereKey($key)
            ->first();
    }

    public static function kategoriSeviyesi(StokKategorisi $kategori): int
    {
        $seviye = 1;
        $gecerli = $kategori;
        $guvenlik = 0;

        while ($gecerli->parent && $guvenlik < 16) {
            $seviye++;
            $guvenlik++;
            $gecerli = $gecerli->parent;
        }

        return $seviye;
    }

    public static function kategoriTamYolu(StokKategorisi $kategori): string
    {
        $adlar = [];
        $gecerli = $kategori;
        $guvenlik = 0;

        while ($gecerli && $guvenlik < 16) {
            $adlar[] = (string) $gecerli->ad;
            $guvenlik++;
            $gecerli = $gecerli->parent;
        }

        return implode(' > ', array_reverse($adlar));
    }

    public static function canEdit(Model $record): bool
    {
        if (! parent::canEdit($record)) {
            return false;
        }

        if ($record instanceof StokKategorisi && $record->is_sabit) {
            return KullaniciRolYardimcisi::superAdminVeyaIsAdmin(Auth::user());
        }

        return true;
    }

    public static function canDelete(Model $record): bool
    {
        if (! parent::canDelete($record)) {
            return false;
        }

        if ($record instanceof StokKategorisi && $record->is_sabit) {
            return KullaniciRolYardimcisi::superAdminVeyaIsAdmin(Auth::user());
        }

        return true;
    }

    public static function form(Form $form): Form
    {
        $superAdminMi = KullaniciRolYardimcisi::superAdminVeyaIsAdmin(Auth::user());

        if (static::hizliDuzenlemeModu()) {
            return $form->schema([
                Forms\Components\TextInput::make('ad')
                    ->label('Ad')
                    ->required()
                    ->maxLength(128),
            ]);
        }

        return $form->schema([
            Forms\Components\Section::make('Kategori')
                ->schema([
                    Forms\Components\Toggle::make('is_sabit')
                        ->label('Sistem sabit tanımı')
                        ->helperText('Tüm firmalar görür; yalnızca süper yönetici düzenleyebilir/silebilir.')
                        ->visible($superAdminMi)
                        ->live()
                        ->default(false),
                    Forms\Components\Select::make('firma_id')
                        ->label('Firma')
                        ->options(fn (): array => self::firmaSecenekleri())
                        ->searchable()
                        ->preload()
                        ->required(fn (Get $get): bool => $superAdminMi && ! (bool) $get('is_sabit'))
                        ->visible(fn (Get $get): bool => $superAdminMi && ! (bool) $get('is_sabit'))
                        ->default(fn () => app(TenantContextService::class)->aktifFirmaId())
                        ->dehydrated(fn (Get $get): bool => $superAdminMi && ! (bool) $get('is_sabit')),
                    Forms\Components\TextInput::make('kod')
                        ->label('Kod')
                        ->required()
                        ->maxLength(64),
                    Forms\Components\TextInput::make('ad')
                        ->label('Ad')
                        ->required()
                        ->maxLength(128),
                    Forms\Components\Select::make('parent_id')
                        ->label('Üst kategori')
                        ->getSearchResultsUsing(function (string $search, Get $get): array {
                            return self::parentKategoriSecenekleri(
                                (bool) $get('is_sabit'),
                                (int) ($get('firma_id') ?: app(TenantContextService::class)->aktifFirmaId()),
                                $search
                            );
                        })
                        ->getOptionLabelUsing(fn ($value): ?string => self::parentKategoriEtiketi((int) $value))
                        ->searchable()
                        ->nullable(),
                    Forms\Components\Toggle::make('aktif_mi')
                        ->label('Aktif')
                        ->default(true),
                    Forms\Components\Textarea::make('aciklama')
                        ->label('Açıklama')
                        ->rows(3)
                        ->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private static function firmaSecenekleri(): array
    {
        if (array_key_exists('firmalar', self::$formSecenekCache)) {
            return self::$formSecenekCache['firmalar'];
        }

        return self::$formSecenekCache['firmalar'] = Firma::query()
            ->orderBy('ad')
            ->pluck('ad', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function parentKategoriSecenekleri(bool $sabit, int $firmaId, string $search = ''): array
    {
        $search = trim($search);
        $cacheKey = 'parent|'.($sabit ? 'sabit' : 'firma:'.$firmaId).'|'.$search;

        if (array_key_exists($cacheKey, self::$formSecenekCache)) {
            return self::$formSecenekCache[$cacheKey];
        }

        if ($sabit) {
            $query = StokKategorisi::query()
                ->whereNull('firma_id')
                ->where('is_sabit', true)
                ->orderBy('ad');

            if ($search !== '') {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('ad', 'like', '%'.$search.'%')
                        ->orWhere('kod', 'like', '%'.$search.'%');
                });
            }

            return self::$formSecenekCache[$cacheKey] = $query
                ->limit(50)
                ->pluck('ad', 'id')
                ->all();
        }

        if ($firmaId < 1) {
            return self::$formSecenekCache[$cacheKey] = [];
        }

        $query = StokKategorisi::query()
            ->gorunurFirmaIle($firmaId)
            ->orderBy('ad');

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('ad', 'like', '%'.$search.'%')
                    ->orWhere('kod', 'like', '%'.$search.'%');
            });
        }

        return self::$formSecenekCache[$cacheKey] = $query
            ->limit(50)
            ->pluck('ad', 'id')
            ->all();
    }

    private static function parentKategoriEtiketi(int $kategoriId): ?string
    {
        if ($kategoriId < 1) {
            return null;
        }

        $cacheKey = 'parent-label|'.$kategoriId;
        if (array_key_exists($cacheKey, self::$formSecenekCache)) {
            return self::$formSecenekCache[$cacheKey][$kategoriId] ?? null;
        }

        $etiket = StokKategorisi::query()
            ->whereKey($kategoriId)
            ->value('ad');

        self::$formSecenekCache[$cacheKey] = $etiket === null ? [] : [$kategoriId => (string) $etiket];

        return $etiket === null ? null : (string) $etiket;
    }

    /**
     * @param  ?int  $childFirmaId  Firma tanımı için firma; sabit kategori için null
     */
    public static function parentKategoriIdHazirla(?int $childFirmaId, bool $childIsSabit, ?int $parentId, ?int $recordId = null): ?int
    {
        $pid = (int) ($parentId ?? 0);
        if ($pid < 1) {
            return null;
        }

        if ($recordId !== null && $pid === $recordId) {
            throw ValidationException::withMessages([
                'parent_id' => 'Kategori kendisini üst kategori seçemez.',
            ]);
        }

        $ust = StokKategorisi::tenantScopeOlmadan(fn () => StokKategorisi::query()->whereKey($pid)->first());
        if (! $ust instanceof StokKategorisi) {
            throw ValidationException::withMessages([
                'parent_id' => 'Üst kategori bulunamadı.',
            ]);
        }

        self::ustKategoriChildIcinUygunMu($ust, $childFirmaId, $childIsSabit);

        $ziyaret = [];
        $zincirDerinligi = 1;
        $gecerli = $ust;
        $derinlik = 0;
        while ($gecerli && $gecerli->parent_id) {
            $derinlik++;
            if ($derinlik > 64) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Kategori hiyerarşisi çok derin veya döngüsel görünüyor.',
                ]);
            }

            $guncelId = (int) $gecerli->id;
            if (in_array($guncelId, $ziyaret, true)) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Kategori hiyerarşisinde döngü tespit edildi.',
                ]);
            }
            $ziyaret[] = $guncelId;
            $zincirDerinligi++;
            if ($zincirDerinligi >= self::MAX_HIYERARSI_DERINLIK) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Kategori hiyerarşisi en fazla '.self::MAX_HIYERARSI_DERINLIK.' seviye olabilir.',
                ]);
            }

            if ($recordId !== null && (int) $gecerli->parent_id === $recordId) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Bu seçim döngüsel kategori ilişkisi oluşturur.',
                ]);
            }

            $gecerli = StokKategorisi::tenantScopeOlmadan(fn () => StokKategorisi::query()
                ->select(['id', 'parent_id', 'firma_id', 'is_sabit'])
                ->whereKey($gecerli->parent_id)
                ->first());
        }

        return $pid;
    }

    private static function ustKategoriChildIcinUygunMu(StokKategorisi $ust, ?int $childFirmaId, bool $childIsSabit): void
    {
        $ustSabit = (bool) $ust->is_sabit && $ust->firma_id === null;

        if ($childIsSabit) {
            if (! $ustSabit) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Sabit kategori yalnızca sabit üst kategori altında olabilir.',
                ]);
            }

            return;
        }

        if ($childFirmaId === null || $childFirmaId < 1) {
            throw ValidationException::withMessages([
                'firma_id' => 'Firma tanımı için firma zorunludur.',
            ]);
        }

        if ($ustSabit) {
            return;
        }

        if ((int) $ust->firma_id !== $childFirmaId) {
            throw ValidationException::withMessages([
                'parent_id' => 'Üst kategori bu firmaya ait veya sabit olmalıdır.',
            ]);
        }
    }

    public static function table(Table $table): Table
    {
        $superAdminMi = KullaniciRolYardimcisi::superAdminVeyaIsAdmin(Auth::user());
        $with = [
            'parent:id,parent_id,ad',
            'parent.parent:id,parent_id,ad',
            'parent.parent.parent:id,parent_id,ad',
        ];

        if ($superAdminMi) {
            $with[] = 'firma:id,ad';
        }

        return $table
            ->query(
                StokKategorisi::query()
                    ->select([
                        'id',
                        'firma_id',
                        'parent_id',
                        'ad',
                        'is_sabit',
                        'aktif_mi',
                    ])
                    ->with($with)
                    ->withCount([
                        'children as alt_kategori_sayisi',
                        'stokKartlari as kayit_sayisi',
                    ])
                    ->orderByRaw('COALESCE(parent_id, 0) asc')
                    ->orderBy('ad')
            )
            ->columns([
                Tables\Columns\TextColumn::make('firma.ad')
                    ->label('Firma')
                    ->placeholder('— (sabit)')
                    ->visible($superAdminMi),
                Tables\Columns\IconColumn::make('is_sabit')
                    ->label('Sabit')
                    ->boolean(),
                Tables\Columns\TextColumn::make('ad')
                    ->label('Ad')
                    ->getStateUsing(function (StokKategorisi $record): string {
                        $girinti = str_repeat('— ', max(0, self::kategoriSeviyesi($record) - 1));

                        return $girinti.(string) $record->ad;
                    })
                    ->description(fn (StokKategorisi $record): string => self::kategoriTamYolu($record))
                    ->limit(42)
                    ->tooltip(fn (StokKategorisi $record): string => self::kategoriTamYolu($record))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('seviye')
                    ->label('Seviye')
                    ->badge()
                    ->getStateUsing(fn (StokKategorisi $record): string => 'Seviye '.self::kategoriSeviyesi($record))
                    ->color('gray'),
                Tables\Columns\TextColumn::make('parent.ad')
                    ->label('Üst kategori')
                    ->placeholder('-')
                    ->limit(32)
                    ->tooltip(fn (StokKategorisi $record): ?string => $record->parent?->ad),
                Tables\Columns\TextColumn::make('tam_yol')
                    ->label('Tam yol')
                    ->getStateUsing(fn (StokKategorisi $record): string => self::kategoriTamYolu($record))
                    ->limit(48)
                    ->tooltip(fn (StokKategorisi $record): string => self::kategoriTamYolu($record))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('alt_kategori_sayisi')
                    ->label('Alt kategori')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('kayit_sayisi')
                    ->label('Kayıt sayısı')
                    ->badge()
                    ->sortable(),
                Tables\Columns\IconColumn::make('aktif_mi')
                    ->label('Durum')
                    ->boolean(),
            ])
            ->defaultSort('ad')
            ->filters([
                Tables\Filters\TernaryFilter::make('aktif_mi')
                    ->label('Durum')
                    ->placeholder('Tümü')
                    ->trueLabel('Aktif')
                    ->falseLabel('Pasif'),
                Tables\Filters\TernaryFilter::make('ana_kategori')
                    ->label('Ana kategori')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNull('parent_id'),
                        false: fn (Builder $query) => $query->whereNotNull('parent_id'),
                        blank: fn (Builder $query) => $query,
                    ),
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
            ->deferLoading()
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStokKategorileri::route('/'),
            'create' => Pages\CreateStokKategorisi::route('/create'),
            'edit' => Pages\EditStokKategorisi::route('/{record}/edit'),
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

    protected static function webUrunKategoriContext(): bool
    {
        return false;
    }
}
