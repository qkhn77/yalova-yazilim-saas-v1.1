<?php

namespace App\Muhasebe\Filament\AbstractKaynaklar;

use App\Filament\Clusters\Muhasebe;
use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeFilamentKaynakYetkileri;
use App\Filament\Clusters\Muhasebe\Resources\StokKartiKaynagi;
use App\Models\Muhasebe\StokKarti;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;

/**
 * @internal `App\Muhasebe` altında; Filament `discoverResources` bu yolu taramaz.
 * Somut kaynak: {@see StokKartiKaynagi}.
 */
abstract class StokKaynagi extends Resource
{
    use MuhasebeFilamentKaynakYetkileri;

    protected static ?string $cluster = Muhasebe::class;

    protected static ?string $model = StokKarti::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static function goruntuleYetkisi(): string
    {
        return MuhasebeYetkiSablonlari::STOK_GORUNTULE;
    }

    protected static function olusturYetkisi(): string
    {
        return MuhasebeYetkiSablonlari::STOK_OLUSTUR;
    }

    protected static function guncelleYetkisi(): string
    {
        return MuhasebeYetkiSablonlari::STOK_GUNCELLE;
    }

    protected static function silYetkisi(): string
    {
        return MuhasebeYetkiSablonlari::STOK_SIL;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([])->actions([])->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [];
    }
}
