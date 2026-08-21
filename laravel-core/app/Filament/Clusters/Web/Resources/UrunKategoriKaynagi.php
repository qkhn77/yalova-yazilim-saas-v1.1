<?php

namespace App\Filament\Clusters\Web\Resources;

use App\Filament\Clusters\Muhasebe\Resources\StokKategoriKaynagi;
use App\Filament\Clusters\Web;
use App\Filament\Clusters\Web\Resources\UrunKategoriKaynagi\Pages;
use App\Models\User;
use App\Services\SidebarService;
use App\Services\TenantContextService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class UrunKategoriKaynagi extends StokKategoriKaynagi
{
    protected static ?string $cluster = Web::class;

    protected static ?string $slug = 'urunler/urun-kategorileri';

    protected static ?string $modelLabel = 'Ürün kategorisi';

    protected static ?string $pluralModelLabel = 'Ürün kategorileri';

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
        return app(SidebarService::class)->menuGorunurMu(
            static::kullanici(),
            static::firmaId(),
            'web',
            $yetkiKodu
        );
    }

    public static function canViewAny(): bool
    {
        return static::izinVarMi('urun_kategori.goruntule')
            || static::izinVarMi('urun_kategori.olustur')
            || static::izinVarMi('urun_kategori.guncelle')
            || static::izinVarMi('urun_kategori.sil');
    }

    public static function canView(Model $record): bool
    {
        return static::izinVarMi('urun_kategori.goruntule')
            || static::izinVarMi('urun_kategori.guncelle');
    }

    public static function canCreate(): bool
    {
        return static::izinVarMi('urun_kategori.olustur');
    }

    public static function canEdit(Model $record): bool
    {
        return static::izinVarMi('urun_kategori.guncelle');
    }

    public static function canDelete(Model $record): bool
    {
        return static::izinVarMi('urun_kategori.sil');
    }

    protected static function webUrunKategoriContext(): bool
    {
        return true;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUrunKategorileri::route('/'),
            'create' => Pages\CreateUrunKategorisi::route('/create'),
            'edit' => Pages\EditUrunKategorisi::route('/{record}/edit'),
        ];
    }
}
