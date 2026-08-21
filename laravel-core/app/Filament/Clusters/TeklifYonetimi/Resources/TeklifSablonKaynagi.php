<?php

namespace App\Filament\Clusters\TeklifYonetimi\Resources;

use App\Filament\Clusters\TeklifYonetimi;
use App\Filament\Clusters\TeklifYonetimi\Resources\TeklifSablonKaynagi\Pages\CreateTeklifSablonu;
use App\Filament\Clusters\TeklifYonetimi\Resources\TeklifSablonKaynagi\Pages\EditTeklifSablonu;
use App\Filament\Clusters\TeklifYonetimi\Resources\TeklifSablonKaynagi\Pages\ListTeklifSablonlari;
use App\Filament\Clusters\TeklifYonetimi\Resources\TeklifSablonKaynagi\Pages\PreviewTeklifSablonu;
use App\Models\TeklifYonetimi\TeklifBaskiSablonu;
use App\Services\TenantContextService;
use App\TeklifYonetimi\Servisler\TeklifBaskiSablonuServisi;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class TeklifSablonKaynagi extends Resource
{
    protected static ?string $cluster = TeklifYonetimi::class;

    protected static ?string $model = TeklifBaskiSablonu::class;

    /** @var array<string, bool> */
    private static array $yetkiCache = [];

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'sablonlar';

    protected static ?string $modelLabel = 'Teklif şablonu';

    protected static ?string $pluralModelLabel = 'Teklif şablonları';

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        $kolonlar = [
            'id',
            'firma_id',
            'ad',
            'varsayilan_mi',
        ];

        if (! static::hizliDuzenlemeModu()) {
            $kolonlar = [
                'id',
                'firma_id',
                'ad',
                'kod',
                'sayfa_tipi',
                'sablon_logo',
                'sablon_html',
                'sablon_css',
                'varsayilan_mi',
                'aktif',
                'updated_at',
            ];
        }

        return static::getModel()::query()
            ->select($kolonlar)
            ->whereKey($key)
            ->first();
    }

    public static function canViewAny(): bool
    {
        return static::yetkiIzni('viewAny', TeklifBaskiSablonu::class);
    }

    public static function canCreate(): bool
    {
        return static::yetkiIzni('create', TeklifBaskiSablonu::class);
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof TeklifBaskiSablonu && static::yetkiIzni('view', $record);
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof TeklifBaskiSablonu && static::yetkiIzni('update', $record);
    }

    public static function canDelete(Model $record): bool
    {
        return $record instanceof TeklifBaskiSablonu && static::yetkiIzni('delete', $record);
    }

    public static function canDeleteAny(): bool
    {
        return static::yetkiIzni('deleteAny', TeklifBaskiSablonu::class);
    }

    public static function form(Form $form): Form
    {
        if (static::hizliDuzenlemeModu()) {
            return $form
                ->schema([
                    Forms\Components\TextInput::make('ad')
                        ->label('Şablon adı')
                        ->required()
                        ->maxLength(191),
                ]);
        }

        return $form
            ->schema([
                Forms\Components\Section::make('Şablon bilgileri')
                    ->schema([
                        Forms\Components\TextInput::make('ad')
                            ->label('Şablon adı')
                            ->required()
                            ->maxLength(191),
                        Forms\Components\TextInput::make('kod')
                            ->label('Kod')
                            ->helperText('Benzersiz olmalıdır. Örnek: teklif-a4')
                            ->required()
                            ->maxLength(64)
                            ->regex('/^[a-z0-9-]+$/'),
                        Forms\Components\Select::make('sayfa_tipi')
                            ->label('Sayfa boyutu')
                            ->options(static::sayfaTipiSecenekleri())
                            ->required(),
                        Forms\Components\FileUpload::make('sablon_logo')
                            ->label('Şablon logosu')
                            ->helperText('Sol üstteki logo alanında kullanılır. Boş bırakılırsa firma logosu, o da yoksa varsayılan YB logosu kullanılır.')
                            ->disk('public')
                            ->directory('teklif-sablon-logolari')
                            ->image()
                            ->maxSize(4096),
                        Forms\Components\Toggle::make('aktif')
                            ->label('Aktif')
                            ->default(true),
                        Forms\Components\Toggle::make('varsayilan_mi')
                            ->label('Varsayılan şablon'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Şablon içeriği')
                    ->schema(fn (string $operation): array => [
                        Forms\Components\ViewField::make('canli_onizleme')
                            ->label('Canlı önizleme')
                            ->view('filament.clusters.teklif-yonetimi.resources.teklif-sablon-kaynagi.fields.canli-onizleme')
                            ->dehydrated(false)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('sablon_html')
                            ->label('Son kullanıcı içeriği (HTML)')
                            ->helperText('Son kullanıcı ve PDF görünümünde işlenecek teklif gövdesidir. Buradan doğrudan düzenleyebilirsiniz.')
                            ->rows(18)
                            ->required()
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('sablon_css')
                            ->label('Son kullanıcı stilleri (CSS)')
                            ->helperText('HTML içeriğinin son kullanıcı ve PDF görünümündeki tasarım kurallarıdır. HTML’den ayrı olarak düzenlenir.')
                            ->rows(14)
                            ->columnSpanFull(),
                        Forms\Components\Placeholder::make('anahtarlar')
                            ->label('Kullanılabilir anahtarlar')
                            ->content('{{FIRMA_UNVAN}}, {{FIRMA_TELEFON}}, {{FIRMA_EPOSTA}}, {{FIRMA_ADRES}}, {{FIRMA_LOGO}}, {{TEKLIF_NO}}, {{TEKLIF_TARIHI}}, {{GECERLILIK_TARIHI}}, {{MUSTERI_AD}}, {{MUSTERI_TELEFON}}, {{MUSTERI_EPOSTA}}, {{MUSTERI_ADRES}}, {{MUSTERI_VERGI_TC}}, {{MUSTERI_YETKILI}}, {{TEKLIF_BASLIGI}}, {{TEKLIF_ACIKLAMA}}, {{TESLIM_SURESI}}, {{PARA_BIRIMI}}, {{KALEMLER_TABLOSU}}, {{KALEMLER_TABLOSU_NUMARALI}}, {{ARA_TOPLAM}}, {{TOPLAM_INDIRIM}}, {{KDV_TOPLAM}}, {{GENEL_TOPLAM}}, {{NOTLAR}}, {{KOSULLAR}}, {{ODEME_PLANI}}'),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->select([
                    'id',
                    'firma_id',
                    'ad',
                    'kod',
                    'sayfa_tipi',
                    'varsayilan_mi',
                    'aktif',
                    'updated_at',
                ]))
            ->columns([
                Tables\Columns\TextColumn::make('ad')
                    ->label('Şablon adı')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('kod')
                    ->label('Kod')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sayfa_tipi')
                    ->label('Sayfa tipi')
                    ->badge(),
                Tables\Columns\IconColumn::make('varsayilan_mi')
                    ->label('Varsayılan')
                    ->boolean(),
                Tables\Columns\IconColumn::make('aktif')
                    ->label('Aktif')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Güncellendi')
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('onizleme')
                    ->label('Ön izleme')
                    ->icon('heroicon-o-eye')
                    ->visible(fn (TeklifBaskiSablonu $record): bool => static::canView($record))
                    ->url(fn (TeklifBaskiSablonu $record): string => static::getUrl('preview', ['record' => $record])),
                Tables\Actions\Action::make('duzenle')
                    ->label('Düzenle')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (TeklifBaskiSablonu $record): bool => static::canEdit($record))
                    ->url(fn (TeklifBaskiSablonu $record): string => static::getUrl('edit', ['record' => $record])),
                Tables\Actions\Action::make('kopyala')
                    ->label('Kopyala')
                    ->icon('heroicon-o-document-duplicate')
                    ->visible(fn (TeklifBaskiSablonu $record): bool => static::canEdit($record))
                    ->action(function (TeklifBaskiSablonu $record): void {
                        $kopya = app(TeklifBaskiSablonuServisi::class)->kopyala($record);

                        \Filament\Notifications\Notification::make()
                            ->title('Şablon kopyalandı')
                            ->body('Yeni şablon: '.$kopya->ad)
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('varsayilan')
                    ->label('Varsayılan Yap')
                    ->icon('heroicon-o-star')
                    ->color('success')
                    ->visible(fn (TeklifBaskiSablonu $record): bool => ! $record->varsayilan_mi && static::canEdit($record))
                    ->action(function (TeklifBaskiSablonu $record): void {
                        app(TeklifBaskiSablonuServisi::class)->varsayilanYap($record);
                    }),
                Tables\Actions\DeleteAction::make()
                    ->label('Sil')
                    ->visible(fn (TeklifBaskiSablonu $record): bool => ! $record->varsayilan_mi && static::canDelete($record)),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->latest('id');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTeklifSablonlari::route('/'),
            'create' => CreateTeklifSablonu::route('/create'),
            'edit' => EditTeklifSablonu::route('/{record}/edit'),
            'preview' => PreviewTeklifSablonu::route('/{record}/preview'),
        ];
    }

    public static function aktifFirmaId(): int
    {
        return (int) app(TenantContextService::class)->aktifFirmaId();
    }

    public static function detayModu(): bool
    {
        // Şablon düzenleme bağlantıları tam ayar formunu açmalıdır.
        // Hızlı mod eski kayıtlarla uyumluluk için yalnızca açıkça istenirse kullanılabilir.
        $routeName = (string) (request()->route()?->getName() ?? '');

        return ! request()->boolean('hizli')
            && (request()->boolean('detay') || str_ends_with($routeName, '.edit'));
    }

    private static function hizliDuzenlemeModu(): bool
    {
        $routeName = (string) (request()->route()?->getName() ?? '');

        return str_ends_with($routeName, '.edit') && ! static::detayModu();
    }

    protected static function yetkiIzni(string $ability, Model|string $target): bool
    {
        $userId = (int) (auth()->id() ?? 0);
        $firmaId = static::aktifFirmaId();
        $targetKey = is_string($target)
            ? $target
            : $target::class.':'.(string) $target->getKey();
        $cacheKey = $ability.'|'.$userId.'|'.$firmaId.'|'.$targetKey;

        return self::$yetkiCache[$cacheKey] ??= Gate::allows($ability, $target);
    }

    /**
     * @return array<string, string>
     */
    public static function sayfaTipiSecenekleri(): array
    {
        return [
            'a4' => 'A4',
            'a5' => 'A5',
            '80mm' => '80mm',
        ];
    }
}
