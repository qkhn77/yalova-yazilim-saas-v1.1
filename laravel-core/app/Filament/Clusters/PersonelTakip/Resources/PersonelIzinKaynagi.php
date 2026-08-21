<?php

namespace App\Filament\Clusters\PersonelTakip\Resources;

use App\Filament\Clusters\PersonelTakip as PersonelTakipCluster;
use App\Filament\Clusters\PersonelTakip\Kaynaklar\PersonelTakipFilamentErisimYardimcisi;
use App\Filament\Clusters\PersonelTakip\Kaynaklar\PersonelTakipKaynakErisimi;
use App\Filament\Clusters\PersonelTakip\Resources\PersonelIzinKaynagi\Pages;
use App\Models\Personel\Personel;
use App\Models\Personel\PersonelIzni;
use App\Services\PersonelTakip\PersonelIzinOnayServisi;
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

class PersonelIzinKaynagi extends Resource
{
    use PersonelTakipKaynakErisimi;

    protected static ?string $model = PersonelIzni::class;

    protected static ?string $cluster = PersonelTakipCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationLabel = 'İzinler';

    protected static ?string $modelLabel = 'İzin';

    protected static ?string $pluralModelLabel = 'İzinler';

    protected static ?string $slug = 'izinler';

    protected static function goruntuleYetkisi(): string
    {
        return PersonelTakipYetkiSablonlari::IZIN_GORUNTULE;
    }

    protected static function olusturYetkisi(): string
    {
        return PersonelTakipYetkiSablonlari::IZIN_OLUSTUR;
    }

    protected static function guncelleYetkisi(): string
    {
        return PersonelTakipYetkiSablonlari::IZIN_DUZENLE;
    }

    protected static function silYetkisi(): string
    {
        return PersonelTakipYetkiSablonlari::IZIN_DUZENLE;
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
                        'onay_bekliyor' => 'Onay bekliyor',
                        'onaylandi' => 'Onaylandı',
                        'reddedildi' => 'Reddedildi',
                    ])
                    ->default('onay_bekliyor'),
            ]);
        }

        return $form->schema([
            Forms\Components\Hidden::make('firma_id')
                ->default(fn (): ?int => app(TenantContextService::class)->aktifFirmaId())
                ->dehydrated(),
            Forms\Components\Section::make('İzin bilgileri')
                ->schema([
                    Forms\Components\Select::make('personel_id')
                        ->label('Personel')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => static::personelAramaSonuclari($search))
                        ->getOptionLabelUsing(fn ($value): ?string => static::personelSecimEtiketi((int) $value))
                        ->required(),
                    Forms\Components\Select::make('izin_turu')
                        ->label('İzin türü')
                        ->options([
                            'yillik' => 'Yıllık izin',
                            'ucretsiz' => 'Ücretsiz izin',
                            'raporlu' => 'Raporlu',
                            'mazeret' => 'Mazeret izni',
                            'haftalik' => 'Haftalık izin',
                            'devamsizlik' => 'Devamsızlık',
                        ])
                        ->required(),
                    Forms\Components\DateTimePicker::make('baslangic_at')->label('Başlangıç')->seconds(false)->required(),
                    Forms\Components\DateTimePicker::make('bitis_at')->label('Bitiş')->seconds(false)->required(),
                    Forms\Components\TextInput::make('gun_sayisi')->label('Gün')->numeric()->default(0),
                    Forms\Components\TextInput::make('saat_sayisi')->label('Saat')->numeric(),
                    Forms\Components\Select::make('durum')
                        ->label('Durum')
                        ->options([
                            'onay_bekliyor' => 'Onay bekliyor',
                            'onaylandi' => 'Onaylandı',
                            'reddedildi' => 'Reddedildi',
                        ])
                        ->default('onay_bekliyor'),
                    Forms\Components\FileUpload::make('belge_path')
                        ->label('Belge')
                        ->directory('personel/izin-belgeleri')
                        ->preserveFilenames()
                        ->visible(fn (string $operation): bool => $operation !== 'create'),
                    Forms\Components\Textarea::make('aciklama')->label('Açıklama')->columnSpanFull(),
                ])
                ->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('baslangic_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('personel.ad_soyad')->label('Personel'),
                Tables\Columns\TextColumn::make('izin_turu')->label('Tür')->badge(),
                Tables\Columns\TextColumn::make('baslangic_at')->label('Başlangıç')->dateTime('d.m.Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('bitis_at')->label('Bitiş')->dateTime('d.m.Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('gun_sayisi')->label('Gün')->sortable(),
                Tables\Columns\TextColumn::make('durum')->label('Durum')->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('durum')
                    ->label('Durum')
                    ->options([
                        'onay_bekliyor' => 'Onay bekliyor',
                        'onaylandi' => 'Onaylandı',
                        'reddedildi' => 'Reddedildi',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('onayla')
                    ->label('Onayla')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (PersonelIzni $record): bool => static::izinOnayYetkisiVarMi($record) && $record->onay_durumu !== 'onaylandi')
                    ->action(function (PersonelIzni $record): void {
                        $firmaId = app(TenantContextService::class)->aktifFirmaId();
                        if (! $firmaId) {
                            return;
                        }

                        try {
                            app(PersonelIzinOnayServisi::class)->onayla($firmaId, (int) $record->id, auth()->id());
                            Notification::make()->title('İzin onaylandı')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('İzin onaylanamadı')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('reddet')
                    ->label('Reddet')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (PersonelIzni $record): bool => static::izinOnayYetkisiVarMi($record) && $record->onay_durumu !== 'reddedildi')
                    ->form([
                        Forms\Components\Textarea::make('aciklama')
                            ->label('Açıklama')
                            ->rows(3),
                    ])
                    ->action(function (PersonelIzni $record, array $data): void {
                        $firmaId = app(TenantContextService::class)->aktifFirmaId();
                        if (! $firmaId) {
                            return;
                        }

                        app(PersonelIzinOnayServisi::class)->reddet(
                            $firmaId,
                            (int) $record->id,
                            auth()->id(),
                            (string) ($data['aciklama'] ?? '')
                        );
                        Notification::make()->title('İzin reddedildi')->success()->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('topluOnayla')
                        ->label('Seçili izinleri onayla')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (): bool => PersonelTakipFilamentErisimYardimcisi::personelYetkisiVarMi(PersonelTakipYetkiSablonlari::IZIN_ONAYLA))
                        ->action(function (Collection $records): void {
                            $firmaId = app(TenantContextService::class)->aktifFirmaId();
                            if (! $firmaId || ! PersonelTakipFilamentErisimYardimcisi::personelYetkisiVarMi(PersonelTakipYetkiSablonlari::IZIN_ONAYLA)) {
                                return;
                            }

                            $adet = 0;
                            foreach ($records as $record) {
                                if (! $record instanceof PersonelIzni || ! PersonelTakipFilamentErisimYardimcisi::kayitAktifFirmayaAitMi($record)) {
                                    continue;
                                }

                                app(PersonelIzinOnayServisi::class)->onayla($firmaId, (int) $record->id, auth()->id());
                                $adet++;
                            }

                            Notification::make()->title($adet.' izin onaylandı')->success()->send();
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPersonelIzinleri::route('/'),
            'create' => Pages\CreatePersonelIzin::route('/create'),
            'edit' => Pages\EditPersonelIzin::route('/{record}/edit'),
        ];
    }

    private static function izinOnayYetkisiVarMi(PersonelIzni $record): bool
    {
        return PersonelTakipFilamentErisimYardimcisi::kayitAktifFirmayaAitMi($record)
            && PersonelTakipFilamentErisimYardimcisi::personelYetkisiVarMi(PersonelTakipYetkiSablonlari::IZIN_ONAYLA);
    }

    private static function personelAramaSonuclari(string $search): array
    {
        return Personel::query()
            ->where('durum', Personel::DURUM_AKTIF)
            ->when($search !== '', fn (Builder $query): Builder => $query->where('ad_soyad', 'like', "%{$search}%"))
            ->orderBy('ad_soyad')
            ->limit(50)
            ->pluck('ad_soyad', 'id')
            ->all();
    }

    private static function personelSecimEtiketi(int $personelId): ?string
    {
        if ($personelId < 1) {
            return null;
        }

        return Personel::query()
            ->whereKey($personelId)
            ->value('ad_soyad');
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
