<?php

namespace App\Filament\Clusters\PersonelTakip\Resources;

use App\Filament\Clusters\PersonelTakip as PersonelTakipCluster;
use App\Filament\Clusters\PersonelTakip\Kaynaklar\PersonelTakipKaynakErisimi;
use App\Filament\Clusters\PersonelTakip\Resources\PersonelVardiyaKaynagi\Pages;
use App\Models\Personel\Personel;
use App\Models\Personel\PersonelVardiyaSablonu;
use App\Models\Personel\PersonelVardiyasi;
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

class PersonelVardiyaKaynagi extends Resource
{
    use PersonelTakipKaynakErisimi;

    private static ?array $subeSecenekleri = null;

    private static ?array $personelSecenekleri = null;

    private static ?array $vardiyaSablonuSecenekleri = null;

    private static ?array $durumSecenekleri = null;

    protected static ?string $model = PersonelVardiyasi::class;

    protected static ?string $cluster = PersonelTakipCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Vardiya Planı';

    protected static ?string $modelLabel = 'Vardiya';

    protected static ?string $pluralModelLabel = 'Vardiya Planı';

    protected static ?string $slug = 'vardiyalar';

    protected static function goruntuleYetkisi(): string
    {
        return PersonelTakipYetkiSablonlari::VARDIYA_GORUNTULE;
    }

    protected static function olusturYetkisi(): string
    {
        return PersonelTakipYetkiSablonlari::VARDIYA_DUZENLE;
    }

    protected static function guncelleYetkisi(): string
    {
        return PersonelTakipYetkiSablonlari::VARDIYA_DUZENLE;
    }

    protected static function silYetkisi(): string
    {
        return PersonelTakipYetkiSablonlari::VARDIYA_DUZENLE;
    }

    public static function form(Form $form): Form
    {
        if (static::hizliDuzenlemeModu()) {
            return $form->schema([
                Forms\Components\Select::make('durum')
                    ->label('Durum')
                    ->options(static::durumSecenekleri())
                    ->default('planlandi')
                    ->native()
                    ->required(),
            ]);
        }

        return $form->schema([
            Forms\Components\Hidden::make('firma_id')
                ->default(fn (): ?int => app(TenantContextService::class)->aktifFirmaId())
                ->dehydrated(),
            Forms\Components\Section::make('Vardiya bilgileri')
                ->schema([
                    Forms\Components\Select::make('sube_id')
                        ->label('Şube')
                        ->options(fn (): array => static::subeSecenekleri())
                        ->searchable()
                        ->preload(),
                    Forms\Components\Select::make('personel_id')
                        ->label('Personel')
                        ->options(fn (): array => static::personelSecenekleri())
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\Select::make('vardiya_sablonu_id')
                        ->label('Vardiya şablonu')
                        ->options(fn (): array => static::vardiyaSablonuSecenekleri())
                        ->searchable()
                        ->preload()
                        ->helperText('Seçilirse tarih ile birlikte başlangıç ve bitiş saatleri otomatik doldurulur.'),
                    Forms\Components\DatePicker::make('tarih')
                        ->label('Tarih')
                        ->required()
                        ->default(now()),
                    Forms\Components\DateTimePicker::make('baslangic_at')
                        ->label('Başlangıç')
                        ->seconds(false)
                        ->required(),
                    Forms\Components\DateTimePicker::make('bitis_at')
                        ->label('Bitiş')
                        ->seconds(false)
                        ->required(),
                    Forms\Components\TextInput::make('mola_dakika')
                        ->label('Mola (dk)')
                        ->numeric()
                        ->default(0),
                    Forms\Components\Select::make('durum')
                        ->label('Durum')
                        ->options(static::durumSecenekleri())
                        ->default('planlandi')
                        ->required(),
                    Forms\Components\Textarea::make('notlar')->label('Notlar')->columnSpanFull(),
                ])
                ->columns(3),
        ]);
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

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->select([
                    'id',
                    'firma_id',
                    'sube_id',
                    'personel_id',
                    'vardiya_sablonu_id',
                    'tarih',
                    'baslangic_at',
                    'bitis_at',
                    'durum',
                ])
                ->with([
                    'sube:id,ad',
                    'personel:id,ad_soyad',
                    'sablon:id,ad',
                ]))
            ->defaultSort('tarih', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('tarih')->label('Tarih')->date('d.m.Y')->sortable(),
                Tables\Columns\TextColumn::make('sube.ad')->label('Şube')->sortable(),
                Tables\Columns\TextColumn::make('personel.ad_soyad')->label('Personel'),
                Tables\Columns\TextColumn::make('sablon.ad')->label('Sablon')->toggleable(),
                Tables\Columns\TextColumn::make('baslangic_at')->label('Başlangıç')->dateTime('d.m.Y H:i'),
                Tables\Columns\TextColumn::make('bitis_at')->label('Bitiş')->dateTime('d.m.Y H:i'),
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
            'index' => Pages\ListPersonelVardiyalari::route('/'),
            'create' => Pages\CreatePersonelVardiya::route('/create'),
            'edit' => Pages\EditPersonelVardiya::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected static function subeSecenekleri(): array
    {
        return self::$subeSecenekleri ??= Cache::remember(
            'personel:vardiya:sube-secenekleri:v1',
            now()->addMinutes(5),
            fn (): array => Sube::query()
                ->orderBy('ad')
                ->pluck('ad', 'id')
                ->all()
        );
    }

    /**
     * @return array<int, string>
     */
    protected static function personelSecenekleri(): array
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
    protected static function vardiyaSablonuSecenekleri(): array
    {
        return self::$vardiyaSablonuSecenekleri ??= PersonelVardiyaSablonu::query()
            ->where('aktif_mi', true)
            ->orderBy('ad')
            ->pluck('ad', 'id')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected static function durumSecenekleri(): array
    {
        return self::$durumSecenekleri ??= [
            'planlandi' => 'Planlandı',
            'onaylandi' => 'Onaylandı',
            'tamamlandi' => 'Tamamlandı',
            'iptal' => 'İptal',
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
