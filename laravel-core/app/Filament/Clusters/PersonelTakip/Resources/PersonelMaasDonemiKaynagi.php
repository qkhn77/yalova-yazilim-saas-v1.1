<?php

namespace App\Filament\Clusters\PersonelTakip\Resources;

use App\Filament\Clusters\PersonelTakip as PersonelTakipCluster;
use App\Filament\Clusters\PersonelTakip\Kaynaklar\PersonelTakipKaynakErisimi;
use App\Filament\Clusters\PersonelTakip\Resources\PersonelMaasDonemiKaynagi\Pages;
use App\Filament\Clusters\PersonelTakip\Resources\PersonelMaasDonemiKaynagi\RelationManagers;
use App\Models\Personel\PersonelMaasDonemi;
use App\Services\PersonelTakip\PersonelMaasDonemiOnayServisi;
use App\Services\PersonelTakip\PersonelMaasHesaplamaServisi;
use App\Models\Sube;
use App\Services\TenantContextService;
use App\Support\PersonelTakip\PersonelTakipYetkiSablonlari;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class PersonelMaasDonemiKaynagi extends Resource
{
    use PersonelTakipKaynakErisimi;

    private static ?array $subeSecenekleri = null;

    private static ?array $durumSecenekleri = null;

    protected static ?string $model = PersonelMaasDonemi::class;

    protected static ?string $cluster = PersonelTakipCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = 'Maaş / Hakediş';

    protected static ?string $modelLabel = 'Maaş dönemi';

    protected static ?string $pluralModelLabel = 'Maaş dönemleri';

    protected static ?string $slug = 'maas-donemleri';

    protected static function goruntuleYetkisi(): string
    {
        return PersonelTakipYetkiSablonlari::MAAS_GORUNTULE;
    }

    protected static function olusturYetkisi(): string
    {
        return PersonelTakipYetkiSablonlari::MAAS_HESAPLA;
    }

    protected static function guncelleYetkisi(): string
    {
        return PersonelTakipYetkiSablonlari::MAAS_HESAPLA;
    }

    protected static function silYetkisi(): string
    {
        return PersonelTakipYetkiSablonlari::MAAS_HESAPLA;
    }

    public static function form(Form $form): Form
    {
        if (! static::detayModu()) {
            return $form->schema([
                Forms\Components\Select::make('durum')
                    ->label('Durum')
                    ->options(static::durumSecenekleri())
                    ->default('taslak')
                    ->native(),
            ]);
        }

        return $form->schema([
            Forms\Components\Hidden::make('firma_id')
                ->default(fn (): ?int => app(TenantContextService::class)->aktifFirmaId())
                ->dehydrated(),
            Forms\Components\Section::make('Dönem bilgileri')
                ->schema([
                    Forms\Components\TextInput::make('ad')->label('Dönem adı')->maxLength(191),
                    Forms\Components\Select::make('sube_id')
                        ->label('Şube')
                        ->options(fn (): array => static::subeSecenekleri())
                        ->searchable()
                        ->preload(),
                    Forms\Components\TextInput::make('donem_yil')->label('Yıl')->numeric()->required()->default((int) now()->year),
                    Forms\Components\TextInput::make('donem_ay')->label('Ay')->numeric()->required()->default((int) now()->month),
                    Forms\Components\DatePicker::make('baslangic_tarihi')->label('Başlangıç')->required(),
                    Forms\Components\DatePicker::make('bitis_tarihi')->label('Bitiş')->required(),
                    Forms\Components\Select::make('durum')
                        ->label('Durum')
                        ->options(static::durumSecenekleri())
                        ->default('taslak'),
                    Forms\Components\Textarea::make('aciklama')->label('Açıklama')->columnSpanFull(),
                ])
                ->columns(4),
        ]);
    }

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        if (! static::detayModu() && filled(request()->route('record'))) {
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
            ->defaultSort('baslangic_tarihi', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('ad')->label('Dönem')->searchable(),
                Tables\Columns\TextColumn::make('sube.ad')->label('Şube')->sortable(),
                Tables\Columns\TextColumn::make('donem_yil')->label('Yıl')->sortable(),
                Tables\Columns\TextColumn::make('donem_ay')->label('Ay')->sortable(),
                Tables\Columns\TextColumn::make('baslangic_tarihi')->label('Başlangıç')->date('d.m.Y')->sortable(),
                Tables\Columns\TextColumn::make('bitis_tarihi')->label('Bitiş')->date('d.m.Y')->sortable(),
                Tables\Columns\TextColumn::make('toplam_net')->label('Toplam net')->money('TRY')->sortable(),
                Tables\Columns\TextColumn::make('durum')->label('Durum')->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('durum')
                    ->label('Durum')
                    ->options(static::durumSecenekleri()),
                Tables\Filters\SelectFilter::make('sube_id')
                    ->label('Şube')
                    ->options(fn (): array => static::subeSecenekleri()),
            ])
            ->actions([
                Tables\Actions\Action::make('hesapla')
                    ->label('Hesapla')
                    ->icon('heroicon-o-calculator')
                    ->requiresConfirmation()
                    ->action(function (PersonelMaasDonemi $record): void {
                        app(PersonelMaasHesaplamaServisi::class)->donemiHesapla($record);

                        Notification::make()
                            ->title('Maaş dönemi hesaplandı')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (PersonelMaasDonemi $record): bool => static::canEdit($record)),
                Tables\Actions\Action::make('onayla')
                    ->label('Onayla')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (PersonelMaasDonemi $record): void {
                        app(PersonelMaasDonemiOnayServisi::class)->onayla(
                            (int) $record->firma_id,
                            (int) $record->id,
                            Auth::id()
                        );

                        Notification::make()
                            ->title('Maaş dönemi onaylandı')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (PersonelMaasDonemi $record): bool => static::canEdit($record) && in_array((string) $record->durum, ['hesaplandi', 'taslak'], true)),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPersonelMaasDonemleri::route('/'),
            'create' => Pages\CreatePersonelMaasDonemi::route('/create'),
            'edit' => Pages\EditPersonelMaasDonemi::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        if (! static::detayModu()) {
            return [];
        }

        return [
            RelationManagers\MaasHareketleriRelationManager::class,
        ];
    }

    public static function detayModu(): bool
    {
        return request()->boolean('detay');
    }

    /**
     * @return array<int, string>
     */
    protected static function subeSecenekleri(): array
    {
        return self::$subeSecenekleri ??= Cache::remember(
            'personel:maas-donemi:sube-secenekleri:v1',
            now()->addMinutes(5),
            fn (): array => Sube::query()
                ->orderBy('ad')
                ->pluck('ad', 'id')
                ->all()
        );
    }

    /**
     * @return array<string, string>
     */
    protected static function durumSecenekleri(): array
    {
        return self::$durumSecenekleri ??= [
            'taslak' => 'Taslak',
            'hesaplandi' => 'Hesaplandı',
            'onaylandi' => 'Onaylandı',
            'odendi' => 'Ödendi',
            'iptal' => 'İptal',
        ];
    }
}
