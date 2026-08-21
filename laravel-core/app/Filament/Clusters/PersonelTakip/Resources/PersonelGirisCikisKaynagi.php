<?php

namespace App\Filament\Clusters\PersonelTakip\Resources;

use App\Filament\Clusters\PersonelTakip as PersonelTakipCluster;
use App\Filament\Clusters\PersonelTakip\Kaynaklar\PersonelTakipFilamentErisimYardimcisi;
use App\Filament\Clusters\PersonelTakip\Kaynaklar\PersonelTakipKaynakErisimi;
use App\Filament\Clusters\PersonelTakip\Resources\PersonelGirisCikisKaynagi\Pages;
use App\Models\Personel\Personel;
use App\Models\Personel\PersonelGirisCikisi;
use App\Models\Personel\PersonelVardiyasi;
use App\Models\Sube;
use App\Services\PersonelTakip\PersonelPuantajOnayServisi;
use App\Services\TenantContextService;
use App\Support\PersonelTakip\PersonelTakipYetkiSablonlari;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PersonelGirisCikisKaynagi extends Resource
{
    use PersonelTakipKaynakErisimi;

    protected static ?string $model = PersonelGirisCikisi::class;

    protected static ?string $cluster = PersonelTakipCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Giriş Çıkış';

    protected static ?string $modelLabel = 'Giriş çıkış kaydı';

    protected static ?string $pluralModelLabel = 'Giriş çıkış kayıtları';

    protected static ?string $slug = 'giris-cikis';

    protected static function goruntuleYetkisi(): string
    {
        return PersonelTakipYetkiSablonlari::GIRIS_CIKIS_GORUNTULE;
    }

    protected static function olusturYetkisi(): string
    {
        return PersonelTakipYetkiSablonlari::GIRIS_CIKIS_DUZENLE;
    }

    protected static function guncelleYetkisi(): string
    {
        return PersonelTakipYetkiSablonlari::GIRIS_CIKIS_DUZENLE;
    }

    protected static function silYetkisi(): string
    {
        return PersonelTakipYetkiSablonlari::GIRIS_CIKIS_DUZENLE;
    }

    public static function form(Form $form): Form
    {
        if (static::hizliDuzenlemeModu()) {
            return $form->schema([
                Forms\Components\Select::make('onay_durumu')
                    ->label('Onay')
                    ->options([
                        'onay_bekliyor' => 'Onay bekliyor',
                        'onaylandi' => 'Onaylandi',
                        'reddedildi' => 'Reddedildi',
                    ])
                    ->default('onay_bekliyor')
                    ->native(),
            ]);
        }

        return $form->schema([
            Forms\Components\Hidden::make('firma_id')
                ->default(fn (): ?int => app(TenantContextService::class)->aktifFirmaId())
                ->dehydrated(),
            Forms\Components\Section::make('Giriş çıkış bilgileri')
                ->schema([
                    Forms\Components\Select::make('sube_id')
                        ->label('Şube')
                        ->options(fn (): array => static::subeSecenekleri())
                        ->searchable()
                        ->preload(),
                    Forms\Components\Select::make('personel_id')
                        ->label('Personel')
                        ->options(fn (): array => Personel::query()
                            ->where('durum', Personel::DURUM_AKTIF)
                            ->orderBy('ad_soyad')
                            ->pluck('ad_soyad', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\Select::make('vardiya_id')
                        ->label('Vardiya')
                        ->options(fn (): array => PersonelVardiyasi::query()->with('personel')->latest('tarih')->limit(100)->get()->mapWithKeys(fn (PersonelVardiyasi $vardiya): array => [$vardiya->id => $vardiya->tarih?->format('d.m.Y').' - '.$vardiya->personel?->ad_soyad])->all())
                        ->searchable(),
                    Forms\Components\DateTimePicker::make('giris_at')->label('Giriş zamanı')->seconds(false),
                    Forms\Components\DateTimePicker::make('cikis_at')->label('Çıkış zamanı')->seconds(false),
                    Forms\Components\Select::make('kaynak')
                        ->label('Kaynak')
                        ->options([
                            'panel' => 'Panel',
                            'pin' => 'PIN',
                            'qr' => 'QR',
                            'pdks' => 'PDKS',
                        ])
                        ->default('panel'),
                    Forms\Components\TextInput::make('giris_tipi')->label('Giriş tipi')->maxLength(40),
                    Forms\Components\TextInput::make('cikis_tipi')->label('Çıkış tipi')->maxLength(40),
                    Forms\Components\TextInput::make('gec_kalma_dakika')->label('Geç kalma (dk)')->numeric()->default(0),
                    Forms\Components\TextInput::make('erken_cikis_dakika')->label('Erken çıkış (dk)')->numeric()->default(0),
                    Forms\Components\TextInput::make('fazla_mesai_dakika')->label('Fazla mesai (dk)')->numeric()->default(0),
                    Forms\Components\TextInput::make('eksik_calisma_dakika')->label('Eksik çalışma (dk)')->numeric()->default(0),
                    Forms\Components\Select::make('onay_durumu')
                        ->label('Onay')
                        ->options([
                            'onay_bekliyor' => 'Onay bekliyor',
                            'onaylandi' => 'Onaylandı',
                            'reddedildi' => 'Reddedildi',
                        ])
                        ->default('onay_bekliyor'),
                    Forms\Components\Textarea::make('aciklama')->label('Açıklama')->columnSpanFull(),
                ])
                ->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('giris_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->select([
                    'id',
                    'firma_id',
                    'sube_id',
                    'personel_id',
                    'giris_at',
                    'cikis_at',
                    'gec_kalma_dakika',
                    'fazla_mesai_dakika',
                    'onay_durumu',
                ])
                ->with([
                    'sube:id,ad',
                    'personel:id,ad_soyad',
                ]))
            ->columns([
                Tables\Columns\TextColumn::make('sube.ad')->label('Şube')->sortable(),
                Tables\Columns\TextColumn::make('personel.ad_soyad')->label('Personel'),
                Tables\Columns\TextColumn::make('giris_at')->label('Giriş')->dateTime('d.m.Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('cikis_at')->label('Çıkış')->dateTime('d.m.Y H:i'),
                Tables\Columns\TextColumn::make('gec_kalma_dakika')->label('Geç (dk)')->sortable(),
                Tables\Columns\TextColumn::make('fazla_mesai_dakika')->label('FM (dk)')->sortable(),
                Tables\Columns\TextColumn::make('onay_durumu')->label('Onay')->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('onay_durumu')
                    ->label('Onay')
                    ->options([
                        'onay_bekliyor' => 'Onay bekliyor',
                        'onaylandi' => 'Onaylandı',
                        'reddedildi' => 'Reddedildi',
                    ]),
                Tables\Filters\SelectFilter::make('sube_id')
                    ->label('Şube')
                    ->options(fn (): array => static::subeSecenekleri()),
            ])
            ->actions([
                Tables\Actions\Action::make('onayla')
                    ->label('Onayla')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (PersonelGirisCikisi $record): bool => static::puantajOnayYetkisiVarMi($record)
                        && $record->onay_durumu !== 'onaylandi'
                        && filled($record->cikis_at))
                    ->action(function (PersonelGirisCikisi $record): void {
                        $firmaId = app(TenantContextService::class)->aktifFirmaId();
                        if (! $firmaId) {
                            return;
                        }

                        app(PersonelPuantajOnayServisi::class)->onayla($firmaId, (int) $record->id, auth()->id());
                        Notification::make()->title('Puantaj onaylandı')->success()->send();
                    }),
                Tables\Actions\Action::make('reddet')
                    ->label('Reddet')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (PersonelGirisCikisi $record): bool => static::puantajOnayYetkisiVarMi($record)
                        && $record->onay_durumu !== 'reddedildi')
                    ->form([
                        Forms\Components\Textarea::make('aciklama')
                            ->label('Açıklama')
                            ->rows(3),
                    ])
                    ->action(function (PersonelGirisCikisi $record, array $data): void {
                        $firmaId = app(TenantContextService::class)->aktifFirmaId();
                        if (! $firmaId) {
                            return;
                        }

                        app(PersonelPuantajOnayServisi::class)->reddet(
                            $firmaId,
                            (int) $record->id,
                            auth()->id(),
                            (string) ($data['aciklama'] ?? '')
                        );
                        Notification::make()->title('Puantaj reddedildi')->success()->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('topluOnayla')
                        ->label('Seçili kayıtları onayla')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (): bool => PersonelTakipFilamentErisimYardimcisi::personelYetkisiVarMi(PersonelTakipYetkiSablonlari::GIRIS_CIKIS_ONAYLA))
                        ->action(function (Collection $records): void {
                            $firmaId = app(TenantContextService::class)->aktifFirmaId();
                            if (! $firmaId || ! PersonelTakipFilamentErisimYardimcisi::personelYetkisiVarMi(PersonelTakipYetkiSablonlari::GIRIS_CIKIS_ONAYLA)) {
                                return;
                            }

                            $adet = 0;
                            foreach ($records as $record) {
                                if (
                                    ! $record instanceof PersonelGirisCikisi
                                    || ! PersonelTakipFilamentErisimYardimcisi::kayitAktifFirmayaAitMi($record)
                                    || ! $record->cikis_at
                                ) {
                                    continue;
                                }

                                app(PersonelPuantajOnayServisi::class)->onayla($firmaId, (int) $record->id, auth()->id());
                                $adet++;
                            }

                            Notification::make()
                                ->title($adet.' puantaj kaydı onaylandı')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPersonelGirisCikislari::route('/'),
            'create' => Pages\CreatePersonelGirisCikis::route('/create'),
            'edit' => Pages\EditPersonelGirisCikis::route('/{record}/edit'),
        ];
    }

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        if (static::hizliDuzenlemeModu()) {
            return PersonelGirisCikisi::query()
                ->select(['id', 'firma_id', 'onay_durumu'])
                ->whereKey($key)
                ->first();
        }

        return parent::resolveRecordRouteBinding($key);
    }

    private static function puantajOnayYetkisiVarMi(PersonelGirisCikisi $record): bool
    {
        return PersonelTakipFilamentErisimYardimcisi::kayitAktifFirmayaAitMi($record)
            && PersonelTakipFilamentErisimYardimcisi::personelYetkisiVarMi(PersonelTakipYetkiSablonlari::GIRIS_CIKIS_ONAYLA);
    }

    /**
     * @return array<int, string>
     */
    private static function subeSecenekleri(): array
    {
        return Cache::remember(
            'personel:giris-cikis:sube-secenekleri:v1',
            now()->addMinutes(5),
            fn (): array => Sube::query()
                ->orderBy('ad')
                ->pluck('ad', 'id')
                ->all()
        );
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
