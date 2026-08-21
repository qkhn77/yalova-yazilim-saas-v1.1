<?php

namespace App\Filament\Clusters\PersonelTakip\Resources;

use App\Filament\Clusters\PersonelTakip as PersonelTakipCluster;
use App\Filament\Clusters\PersonelTakip\Kaynaklar\PersonelTakipFilamentErisimYardimcisi;
use App\Filament\Clusters\PersonelTakip\Kaynaklar\PersonelTakipKaynakErisimi;
use App\Filament\Clusters\PersonelTakip\Resources\PersonelAvansKaynagi\Pages;
use App\Models\Muhasebe\BankaHesabi;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\PosHesabi;
use App\Models\Personel\Personel;
use App\Models\Personel\PersonelAvansi;
use App\Services\PersonelTakip\PersonelAvansOnayServisi;
use App\Services\PersonelTakip\PersonelFinansHareketServisi;
use App\Services\TenantContextService;
use App\Support\PersonelTakip\PersonelTakipYetkiSablonlari;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class PersonelAvansKaynagi extends Resource
{
    use PersonelTakipKaynakErisimi;

    /** @var array<int, string>|null */
    private static ?array $personelSecenekleri = null;

    /** @var array<string, array<int, array<int, string>>> */
    private static array $hesapSecenekleri = [];

    protected static ?string $model = PersonelAvansi::class;

    protected static ?string $cluster = PersonelTakipCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Avanslar';

    protected static ?string $modelLabel = 'Avans';

    protected static ?string $pluralModelLabel = 'Avanslar';

    protected static ?string $slug = 'avanslar';

    protected static function goruntuleYetkisi(): string
    {
        return PersonelTakipYetkiSablonlari::AVANS_GORUNTULE;
    }

    protected static function olusturYetkisi(): string
    {
        return PersonelTakipYetkiSablonlari::AVANS_OLUSTUR;
    }

    protected static function guncelleYetkisi(): string
    {
        return PersonelTakipYetkiSablonlari::AVANS_ONAYLA;
    }

    protected static function silYetkisi(): string
    {
        return PersonelTakipYetkiSablonlari::AVANS_ONAYLA;
    }

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        if (static::hizliDuzenlemeModu()) {
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

    public static function form(Form $form): Form
    {
        if (static::hizliDuzenlemeModu()) {
            return $form->schema([
                Forms\Components\Select::make('durum')
                    ->label('Durum')
                    ->options([
                        'taslak' => 'Taslak',
                        'onaylandi' => 'Onaylandı',
                        'reddedildi' => 'Reddedildi',
                        'mahsup_edildi' => 'Mahsup edildi',
                        'iptal' => 'İptal',
                    ])
                    ->default('taslak')
                    ->native(),
            ]);
        }

        return $form->schema([
            Forms\Components\Hidden::make('firma_id')
                ->default(fn (): ?int => app(TenantContextService::class)->aktifFirmaId())
                ->dehydrated(),
            Forms\Components\Section::make('Avans bilgileri')
                ->schema([
                    Forms\Components\Select::make('personel_id')
                        ->label('Personel')
                        ->options(fn (): array => self::personelSecenekleri())
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\DatePicker::make('tarih')->label('Tarih')->required()->default(now()),
                    Forms\Components\TextInput::make('tutar')->label('Tutar')->numeric()->required(),
                    Forms\Components\TextInput::make('para_birimi')->label('Para birimi')->default('TRY')->maxLength(3),
                    Forms\Components\Select::make('odeme_kanali')
                        ->label('Ödeme kaynağı')
                        ->options([
                            'kasa' => 'Kasa',
                            'banka' => 'Banka',
                            'pos' => 'POS',
                            'diger' => 'Diğer',
                        ])
                        ->live()
                        ->afterStateUpdated(function (Forms\Set $set): void {
                            $set('kasa_hesap_id', null);
                            $set('banka_hesap_id', null);
                            $set('pos_hesap_id', null);
                        }),
                    Forms\Components\Select::make('kasa_hesap_id')
                        ->label('Kasa hesabı')
                        ->options(fn (Get $get): array => ($get('odeme_kanali') ?? '') === 'kasa' ? self::hesapSecenekleri('kasa') : [])
                        ->visible(fn (Get $get): bool => ($get('odeme_kanali') ?? '') === 'kasa')
                        ->searchable()
                        ->preload(),
                    Forms\Components\Select::make('banka_hesap_id')
                        ->label('Banka hesabı')
                        ->options(fn (Get $get): array => ($get('odeme_kanali') ?? '') === 'banka' ? self::hesapSecenekleri('banka') : [])
                        ->visible(fn (Get $get): bool => ($get('odeme_kanali') ?? '') === 'banka')
                        ->searchable()
                        ->preload(),
                    Forms\Components\Select::make('pos_hesap_id')
                        ->label('POS hesabı')
                        ->options(fn (Get $get): array => ($get('odeme_kanali') ?? '') === 'pos' ? self::hesapSecenekleri('pos') : [])
                        ->visible(fn (Get $get): bool => ($get('odeme_kanali') ?? '') === 'pos')
                        ->searchable()
                        ->preload(),
                    Forms\Components\TextInput::make('kalan_tutar')->label('Kalan tutar')->numeric()->default(0),
                    Forms\Components\Toggle::make('maastan_dusuldu_mu')->label('Maaştan düşüldü')->default(false),
                    Forms\Components\Select::make('durum')
                        ->label('Durum')
                        ->options([
                            'taslak' => 'Taslak',
                            'onaylandi' => 'Onaylandı',
                            'reddedildi' => 'Reddedildi',
                            'mahsup_edildi' => 'Mahsup edildi',
                            'iptal' => 'İptal',
                        ])
                        ->default('taslak'),
                    Forms\Components\Textarea::make('aciklama')->label('Açıklama')->columnSpanFull(),
                ])
                ->columns(3),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private static function personelSecenekleri(): array
    {
        return self::$personelSecenekleri ??= Personel::query()
            ->where('durum', Personel::DURUM_AKTIF)
            ->orderBy('ad_soyad')
            ->pluck('ad_soyad', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function hesapSecenekleri(string $tip): array
    {
        $firmaId = app(TenantContextService::class)->aktifFirmaId();

        if (! $firmaId) {
            return [];
        }

        if (isset(self::$hesapSecenekleri[$tip][$firmaId])) {
            return self::$hesapSecenekleri[$tip][$firmaId];
        }

        $query = match ($tip) {
            'kasa' => KasaHesabi::query(),
            'banka' => BankaHesabi::query(),
            'pos' => PosHesabi::query(),
            default => null,
        };

        if (! $query) {
            return [];
        }

        return self::$hesapSecenekleri[$tip][$firmaId] = $query
            ->where('firma_id', $firmaId)
            ->orderBy('ad')
            ->pluck('ad', 'id')
            ->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('tarih', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('tarih')->label('Tarih')->date('d.m.Y')->sortable(),
                Tables\Columns\TextColumn::make('personel.ad_soyad')->label('Personel'),
                Tables\Columns\TextColumn::make('tutar')->label('Tutar')->money('TRY')->sortable(),
                Tables\Columns\TextColumn::make('kalan_tutar')->label('Kalan')->money('TRY')->sortable(),
                Tables\Columns\IconColumn::make('maastan_dusuldu_mu')->label('Düşüldü')->boolean(),
                Tables\Columns\TextColumn::make('durum')->label('Durum')->badge(),
                Tables\Columns\TextColumn::make('odeme_kanali')->label('Kaynak')->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('durum')
                    ->label('Durum')
                    ->options([
                        'taslak' => 'Taslak',
                        'onaylandi' => 'Onaylandı',
                        'reddedildi' => 'Reddedildi',
                        'mahsup_edildi' => 'Mahsup edildi',
                        'iptal' => 'İptal',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('onayla')
                    ->label('Onayla')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (PersonelAvansi $record): bool => static::canEdit($record) && $record->onay_durumu !== 'onaylandi')
                    ->form([
                        Forms\Components\Toggle::make('finansa_isle')
                            ->label('Finans hareketi oluştur')
                            ->default(true)
                            ->visible(fn (PersonelAvansi $record): bool => in_array((string) $record->odeme_kanali, ['kasa', 'banka'], true)),
                    ])
                    ->action(function (PersonelAvansi $record, array $data): void {
                        $firmaId = app(TenantContextService::class)->aktifFirmaId();
                        if (! $firmaId) {
                            return;
                        }

                        try {
                            $avans = app(PersonelAvansOnayServisi::class)->onayla(
                                $firmaId,
                                (int) $record->id,
                                auth()->id(),
                                (bool) ($data['finansa_isle'] ?? false)
                            );

                            Notification::make()
                                ->title('Avans onaylandı')
                                ->body($avans->finans_hareketi_id ? 'Finans hareketi #'.$avans->finans_hareketi_id : null)
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Avans onaylanamadı')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('reddet')
                    ->label('Reddet')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (PersonelAvansi $record): bool => static::canEdit($record) && $record->onay_durumu !== 'reddedildi')
                    ->form([
                        Forms\Components\Textarea::make('aciklama')
                            ->label('Açıklama')
                            ->rows(3),
                    ])
                    ->action(function (PersonelAvansi $record, array $data): void {
                        $firmaId = app(TenantContextService::class)->aktifFirmaId();
                        if (! $firmaId) {
                            return;
                        }

                        app(PersonelAvansOnayServisi::class)->reddet(
                            $firmaId,
                            (int) $record->id,
                            auth()->id(),
                            (string) ($data['aciklama'] ?? '')
                        );
                        Notification::make()->title('Avans reddedildi')->success()->send();
                    }),
                Tables\Actions\Action::make('finansa_isle')
                    ->label('Finansa İşle')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (PersonelAvansi $record): bool => static::canEdit($record)
                        && ! $record->finans_hareketi_id
                        && in_array((string) $record->odeme_kanali, ['kasa', 'banka'], true)
                    )
                    ->action(function (PersonelAvansi $record): void {
                        try {
                            $finans = app(PersonelFinansHareketServisi::class)->avansOdemesiniFinansaIsle($record);

                            Notification::make()
                                ->title('Avans finans hareketine işlendi')
                                ->body('Finans hareketi #'.$finans->id)
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Avans finans hareketine işlenemedi')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('iptal')
                    ->label('İptal et')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (PersonelAvansi $record): bool => static::canEdit($record)
                        && filled($record->finans_hareketi_id)
                        && (string) $record->durum !== 'iptal')
                    ->action(function (PersonelAvansi $record): void {
                        app(PersonelFinansHareketServisi::class)->avansOdemesiniIptalEt($record, 'Personel avansı iptali');
                        Notification::make()->title('Personel avansı iptal edildi')->success()->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (PersonelAvansi $record): bool => blank($record->finans_hareketi_id)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('topluOnayla')
                        ->label('Seçili avansları onayla')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (): bool => PersonelTakipFilamentErisimYardimcisi::personelYetkisiVarMi(PersonelTakipYetkiSablonlari::AVANS_ONAYLA))
                        ->action(function (Collection $records): void {
                            $firmaId = app(TenantContextService::class)->aktifFirmaId();
                            if (! $firmaId || ! PersonelTakipFilamentErisimYardimcisi::personelYetkisiVarMi(PersonelTakipYetkiSablonlari::AVANS_ONAYLA)) {
                                return;
                            }

                            $adet = 0;
                            foreach ($records as $record) {
                                if (! $record instanceof PersonelAvansi || ! PersonelTakipFilamentErisimYardimcisi::kayitAktifFirmayaAitMi($record)) {
                                    continue;
                                }

                                app(PersonelAvansOnayServisi::class)->onayla($firmaId, (int) $record->id, auth()->id(), false);
                                $adet++;
                            }

                            Notification::make()->title($adet.' avans onaylandı')->success()->send();
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPersonelAvanslari::route('/'),
            'create' => Pages\CreatePersonelAvans::route('/create'),
            'edit' => Pages\EditPersonelAvans::route('/{record}/edit'),
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
}
