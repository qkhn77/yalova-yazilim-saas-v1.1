<?php

namespace App\Muhasebe\Filament\AbstractKaynaklar;

use App\Filament\Clusters\Muhasebe;
use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeFilamentKaynakYetkileri;
use App\Models\Muhasebe\ParaBirimi;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;

/**
 * @internal `App\Muhasebe` altında; Filament `discoverResources` bu yolu taramaz. Somut kayıt STEP 10+.
 * TANIM_OLUSTUR / TANIM_SIL sabitleri olmadığından olustur/sil yetkileri geçici olarak TANIM_GUNCELLE ile eşlenir.
 */
abstract class ParaBirimiKaynagi extends Resource
{
    use MuhasebeFilamentKaynakYetkileri;

    protected static ?string $cluster = Muhasebe::class;

    protected static ?string $model = ParaBirimi::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static function goruntuleYetkisi(): string
    {
        return MuhasebeYetkiSablonlari::TANIM_GORUNTULE;
    }

    protected static function olusturYetkisi(): string
    {
        return MuhasebeYetkiSablonlari::TANIM_GUNCELLE;
    }

    protected static function guncelleYetkisi(): string
    {
        return MuhasebeYetkiSablonlari::TANIM_GUNCELLE;
    }

    protected static function silYetkisi(): string
    {
        return MuhasebeYetkiSablonlari::TANIM_GUNCELLE;
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
