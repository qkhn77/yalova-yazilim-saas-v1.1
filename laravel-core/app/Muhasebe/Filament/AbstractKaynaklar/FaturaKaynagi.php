<?php

namespace App\Muhasebe\Filament\AbstractKaynaklar;

use App\Filament\Clusters\Muhasebe;
use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeFilamentKaynakYetkileri;
use App\Models\Muhasebe\Fatura;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;

/**
 * Fatura kaynağı iskeleti. Modelde `tur` alanı ile gelen / giden / iade / proforma / gider vb. ayrılacak.
 *
 * @internal `App\Muhasebe` altında; Filament `discoverResources` bu yolu taramaz. Somut kayıt STEP 10+.
 */
abstract class FaturaKaynagi extends Resource
{
    use MuhasebeFilamentKaynakYetkileri;

    protected static ?string $cluster = Muhasebe::class;

    protected static ?string $model = Fatura::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static function goruntuleYetkisi(): string
    {
        return MuhasebeYetkiSablonlari::FATURA_GORUNTULE;
    }

    protected static function olusturYetkisi(): string
    {
        return MuhasebeYetkiSablonlari::FATURA_OLUSTUR;
    }

    protected static function guncelleYetkisi(): string
    {
        return MuhasebeYetkiSablonlari::FATURA_GUNCELLE;
    }

    protected static function silYetkisi(): string
    {
        return MuhasebeYetkiSablonlari::FATURA_SIL;
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
