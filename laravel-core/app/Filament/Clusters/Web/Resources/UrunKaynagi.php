<?php

namespace App\Filament\Clusters\Web\Resources;

use App\Filament\Clusters\Muhasebe\Resources\StokKartiKaynagi;
use App\Filament\Clusters\Web;
use App\Filament\Clusters\Web\Resources\UrunKaynagi\Pages;
use App\Models\User;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Services\SidebarService;
use App\Services\TenantContextService;
use Filament\Forms;
use Filament\Forms\Form;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class UrunKaynagi extends StokKartiKaynagi
{
    /** @var array<string, bool> */
    private static array $izinCache = [];

    protected static ?string $cluster = Web::class;

    protected static ?string $slug = 'urunler/urun-listesi';

    protected static ?string $modelLabel = 'Ürün';

    protected static ?string $pluralModelLabel = 'Ürünler';

    protected static function kullanici(): ?User
    {
        $kullanici = Auth::user();

        return $kullanici instanceof User ? $kullanici : null;
    }

    protected static function firmaId(): ?int
    {
        return app(TenantContextService::class)->aktifFirmaId();
    }

    protected static function izinVarMi(string $yetkiKodu): bool
    {
        $kullanici = static::kullanici();
        $firmaId = static::firmaId();
        $cacheKey = ((int) ($kullanici?->id ?? 0)).'|'.((int) ($firmaId ?? 0)).'|'.$yetkiKodu;

        if (array_key_exists($cacheKey, self::$izinCache)) {
            return self::$izinCache[$cacheKey];
        }

        if ($kullanici && ((bool) ($kullanici->super_admin_mi ?? false) || (bool) ($kullanici->is_admin ?? false))) {
            return self::$izinCache[$cacheKey] = true;
        }

        return self::$izinCache[$cacheKey] = app(SidebarService::class)->menuGorunurMu(
            $kullanici,
            $firmaId,
            'web',
            $yetkiKodu
        );
    }

    public static function canViewAny(): bool
    {
        return static::izinVarMi('urun.goruntule')
            || static::izinVarMi('urun.olustur')
            || static::izinVarMi('urun.guncelle')
            || static::izinVarMi('urun.sil');
    }

    public static function canView(Model $record): bool
    {
        return static::izinVarMi('urun.goruntule')
            || static::izinVarMi('urun.guncelle');
    }

    public static function canCreate(): bool
    {
        return static::izinVarMi('urun.olustur');
    }

    public static function canEdit(Model $record): bool
    {
        return static::izinVarMi('urun.guncelle');
    }

    public static function canDelete(Model $record): bool
    {
        return static::izinVarMi('urun.sil');
    }

    public static function isWebUrunContext(): bool
    {
        return true;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        if (filled(request()->route('record')) && ! static::detayModu()) {
            $routeName = (string) (request()->route()?->getName() ?? '');

            if (str_ends_with($routeName, '.edit')) {
                return static::getModel()::query()
                    ->select([
                        'id',
                        'firma_id',
                        'kod',
                        'durum',
                    ])
                    ->whereKey($key)
                    ->first();
            }

            return static::getModel()::query()
                ->select([
                    'id',
                    'ad',
                    'satis_fiyati',
                    'para_birimi',
                ])
                ->whereKey($key)
                ->first();
        }

        return parent::resolveRecordRouteBinding($key);
    }

    public static function form(Form $form): Form
    {
        if ($form->getOperation() !== 'create' && ! static::detayModu()) {
            return $form->schema([
                Forms\Components\Select::make('durum')
                    ->label('Durum')
                    ->options([
                        HesapDurumu::Aktif->value => 'Aktif',
                        HesapDurumu::Pasif->value => 'Pasif',
                    ])
                    ->required()
                    ->default(HesapDurumu::Aktif->value)
                    ->native(),
            ]);
        }

        return parent::form($form);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUrunler::route('/'),
            'create' => Pages\CreateUrun::route('/create'),
            'view' => Pages\ViewUrun::route('/{record}'),
            'edit' => Pages\EditUrun::route('/{record}/edit'),
        ];
    }
}
