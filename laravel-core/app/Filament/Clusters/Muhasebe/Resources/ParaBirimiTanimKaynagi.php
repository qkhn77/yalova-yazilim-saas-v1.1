<?php

namespace App\Filament\Clusters\Muhasebe\Resources;

use App\Filament\Clusters\Muhasebe\Resources\ParaBirimiTanimKaynagi\Pages;
use App\Models\Firma;
use App\Models\Muhasebe\ParaBirimi;
use App\Muhasebe\Filament\AbstractKaynaklar\ParaBirimiKaynagi as AbstractKaynak;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Filament\Notifications\Notification;

class ParaBirimiTanimKaynagi extends AbstractKaynak
{
    /** @var array<int,string> */
    private static array $firmaSecenekleriCache = [];

    private static ?bool $superAdminMiCache = null;

    protected static ?string $slug = 'tanimlar/para-birimleri';

    protected static bool $isScopedToTenant = false;

    protected static ?string $modelLabel = 'Para birimi';

    protected static ?string $pluralModelLabel = 'Para birimleri';

    public static function canEdit(Model $record): bool
    {
        if (! parent::canEdit($record)) {
            return false;
        }

        if ($record instanceof ParaBirimi && $record->is_sabit) {
            return static::superAdminMi();
        }

        return true;
    }

    public static function canDelete(Model $record): bool
    {
        if (! parent::canDelete($record)) {
            return false;
        }

        if ($record instanceof ParaBirimi && $record->is_sabit) {
            return static::superAdminMi();
        }

        return true;
    }

    public static function form(Form $form): Form
    {
        if (static::hizliDuzenlemeModu()) {
            return $form->schema([
                Forms\Components\Checkbox::make('aktif_mi')
                    ->label('Aktif')
                    ->default(true),
            ]);
        }

        $superAdminMi = static::superAdminMi();

        return $form->schema([
            Forms\Components\Section::make()
                ->schema([
                    Forms\Components\Toggle::make('is_sabit')
                        ->label('Sistem sabit tanımı')
                        ->helperText('Tüm firmalar görür; yalnızca süper yönetici düzenleyebilir/silebilir.')
                        ->visible($superAdminMi)
                        ->live()
                        ->default(false),
                    Forms\Components\Select::make('firma_id')
                        ->label('Firma')
                        ->options(fn (): array => static::firmaSecenekleri())
                        ->searchable()
                        ->preload()
                        ->required(fn (Get $get): bool => $superAdminMi && ! (bool) $get('is_sabit'))
                        ->visible(fn (Get $get): bool => $superAdminMi && ! (bool) $get('is_sabit'))
                        ->default(fn () => app(TenantContextService::class)->aktifFirmaId())
                        ->dehydrated(fn (Get $get): bool => $superAdminMi && ! (bool) $get('is_sabit'))
                        ->helperText(fn () => $superAdminMi ? null : 'Aktif firma oturumuna kaydedilir.'),
                    Forms\Components\TextInput::make('kod')
                        ->label('Kod')
                        ->required()
                        ->maxLength(3)
                        ->minLength(3)
                        ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                        ->dehydrateStateUsing(fn (?string $state) => $state ? Str::upper(trim($state)) : $state)
                        ->helperText('ISO 4217 benzeri 3 harf (örn. TRY, USD).'),
                    Forms\Components\TextInput::make('ad')
                        ->label('Ad')
                        ->maxLength(64),
                    Forms\Components\Toggle::make('aktif_mi')
                        ->label('Aktif')
                        ->default(true),
                ])->columns(2),
        ]);
    }

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        return ParaBirimi::query()
            ->select([
                'id',
                'firma_id',
                'is_sabit',
                'kod',
                'ad',
                'aktif_mi',
            ])
            ->whereKey($key)
            ->first();
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
     * @return array<int,string>
     */
    private static function firmaSecenekleri(): array
    {
        if (self::$firmaSecenekleriCache !== []) {
            return self::$firmaSecenekleriCache;
        }

        return self::$firmaSecenekleriCache = Firma::query()
            ->orderBy('ad')
            ->pluck('ad', 'id')
            ->all();
    }

    private static function superAdminMi(): bool
    {
        if (self::$superAdminMiCache !== null) {
            return self::$superAdminMiCache;
        }

        return self::$superAdminMiCache = KullaniciRolYardimcisi::superAdminVeyaIsAdmin(Auth::user());
    }

    /** @return array<int, string> */
    private static function paraBirimiKullanimlari(ParaBirimi $record): array
    {
        $kod = strtoupper((string) $record->kod);
        $firmaId = (int) $record->firma_id;
        $kullanimlar = [];

        foreach ([
            'cariler' => 'Cari hesap',
            'faturalar' => 'Fatura',
            'fatura_kalemleri' => 'Fatura kalemi',
            'cari_hareketleri' => 'Cari hareketi',
            'finans_hareketleri' => 'Finans hareketi',
            'fatura_finans_kapatmalari' => 'Fatura finans kapaması',
            'kasa_hesaplari' => 'Kasa hesabı',
            'banka_hesaplari' => 'Banka hesabı',
            'pos_hesaplari' => 'POS hesabı',
            'stok_kartlari' => 'Stok kartı',
        ] as $tablo => $etiket) {
            if (! Schema::hasTable($tablo) || ! Schema::hasColumn($tablo, 'para_birimi')) {
                continue;
            }

            $q = DB::table($tablo)->whereRaw('UPPER(para_birimi) = ?', [$kod]);
            if (Schema::hasColumn($tablo, 'firma_id') && $firmaId > 0) {
                $q->where('firma_id', $firmaId);
            }

            $say = (int) $q->count();
            if ($say > 0) {
                $ornek = Schema::hasColumn($tablo, 'id')
                    ? $q->limit(5)->pluck('id')->implode(', ')
                    : '';
                $kullanimlar[] = $etiket.': '.$say.' kayıt'.($ornek !== '' ? ' (ID: '.$ornek.')' : '');
            }
        }

        if (Schema::hasTable('muhasebe_doviz_kurlari')) {
            $q = DB::table('muhasebe_doviz_kurlari')
                ->where(function ($q) use ($kod): void {
                    $q->whereRaw('UPPER(kaynak_para_birimi) = ?', [$kod])
                        ->orWhereRaw('UPPER(hedef_para_birimi) = ?', [$kod]);
                });
            if ($firmaId > 0) {
                $q->where(function ($q) use ($firmaId): void {
                    $q->where('firma_id', $firmaId)->orWhere('tanim_firma_kapsami', $firmaId);
                });
            }
            $say = (int) $q->count();
            if ($say > 0) {
                $ornek = Schema::hasColumn('muhasebe_doviz_kurlari', 'id')
                    ? $q->limit(5)->pluck('id')->implode(', ')
                    : '';
                $kullanimlar[] = 'Döviz kuru: '.$say.' kayıt'.($ornek !== '' ? ' (ID: '.$ornek.')' : '');
            }
        }

        return $kullanimlar;
    }

    public static function table(Table $table): Table
    {
        $superAdminMi = static::superAdminMi();

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->select([
                    'id',
                    'firma_id',
                    'is_sabit',
                    'kod',
                    'ad',
                    'aktif_mi',
                    'updated_at',
                ])
                ->with('firma:id,ad'))
            ->columns([
                Tables\Columns\TextColumn::make('firma.ad')
                    ->label('Firma')
                    ->placeholder('— (sabit)')
                    ->sortable()
                    ->visible($superAdminMi),
                Tables\Columns\IconColumn::make('is_sabit')
                    ->label('Sabit')
                    ->boolean(),
                Tables\Columns\TextColumn::make('kod')
                    ->label('Kod')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('ad')
                    ->label('Ad')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\IconColumn::make('aktif_mi')
                    ->label('Aktif')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Güncellendi')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('kod')
            ->filters([
                Tables\Filters\TernaryFilter::make('aktif_mi')
                    ->label('Durum')
                    ->placeholder('Tümü')
                    ->trueLabel('Aktif')
                    ->falseLabel('Pasif'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('sil')
                    ->label('Sil')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (ParaBirimi $record): bool => static::canDelete($record))
                    ->requiresConfirmation()
                    ->modalHeading('Para birimini sil')
                    ->modalDescription('Kullanılmış para birimleri silinemez; eski kayıtlar kontrol edilecektir.')
                    ->action(function (ParaBirimi $record): void {
                        $kullanimlar = static::paraBirimiKullanimlari($record);
                        if ($kullanimlar !== []) {
                            Notification::make()
                                ->danger()
                                ->title('Para birimi silinemedi')
                                ->body(implode(' · ', $kullanimlar).'. Eski kayıtlar bulunduğu için para birimini pasife çekin.')
                                ->persistent()
                                ->send();

                            return;
                        }

                        $record->delete();
                        Notification::make()->success()->title('Para birimi silindi')->send();
                    }),
            ])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListParaBirimleri::route('/'),
            'create' => Pages\CreateParaBirimi::route('/create'),
            'edit' => Pages\EditParaBirimi::route('/{record}/edit'),
        ];
    }
}
