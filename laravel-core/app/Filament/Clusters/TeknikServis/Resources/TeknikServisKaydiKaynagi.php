<?php

namespace App\Filament\Clusters\TeknikServis\Resources;

use App\Filament\Clusters\TeknikServis as TeknikServisCluster;
use App\Filament\Clusters\TeknikServis\Concerns\TeknikServisKayitFormSchema;
use App\Filament\Clusters\TeknikServis\Concerns\TeknikServisKayitTabloTanimi;
use App\Filament\Clusters\TeknikServis\Resources\Concerns\TeknikServisKayitKaynakErisimi;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi\RelationManagers\YapilanTahsilatlarRelationManager;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi\Pages;
use App\Models\TeknikServis\TeknikServisKaydi;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TeknikServisKaydiKaynagi extends Resource
{
    use TeknikServisKayitKaynakErisimi;

    protected static ?string $model = TeknikServisKaydi::class;

    protected static ?string $cluster = TeknikServisCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = "T\u{00FC}m kay\u{0131}tlar";

    protected static ?string $modelLabel = "Servis kayd\u{0131}";

    protected static ?string $pluralModelLabel = "Servis kay\u{0131}tlar\u{0131}";

    protected static ?string $recordTitleAttribute = 'fis_no';

    protected static ?string $slug = 'servis-kayitlari';

    protected static ?string $navigationGroup = "Servis kay\u{0131}tlar\u{0131}";

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return TeknikServisKayitFormSchema::formuOlustur($form, false, null);
    }

    public static function table(Table $tablo): Table
    {
        return TeknikServisKayitTabloTanimi::tabloyuUygula($tablo)
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Düzenle')
                    ->icon('heroicon-o-pencil-square')
                    ->iconButton()
                    ->tooltip('Düzenle'),
            ], ActionsPosition::BeforeColumns)
            ->bulkActions([]);
    }

    public static function resolveRecordRouteBinding(int | string $key): ?Model
    {
        $sorgu = static::getEloquentQuery();

        if (static::hizliKayitBaglamaModu()) {
            $sorgu = $sorgu->select([
                'id',
                'firma_id',
                'fis_no',
                'servis_durumu_id',
            ]);
        } else {
            $sorgu = $sorgu
                ->with([
                    'cari:id,ad,telefon,gsm,para_birimi',
                    'cihaz:id,ad',
                    'marka:id,ad',
                    'ariza:id,ad',
                    'servisDurumu:id,ad,kod,is_fiyat_verildi,is_teslim_edildi,is_iptal,is_iade',
                ])
                ->withExists([
                    'alacakPlanlari as aktif_alacak_plani_var_mi' => fn ($query) => $query
                        ->whereColumn('muhasebe_alacak_planlari.firma_id', 'teknik_servis_kayitlari.firma_id')
                        ->whereIn('durum', ['aktif', 'kismi_odendi', 'gecikti']),
                ]);
        }

        return app(static::getModel())
            ->resolveRouteBindingQuery(
                $sorgu,
                $key,
                static::getRecordRouteKeyName()
            )
            ->first();
    }

    public static function getRelations(): array
    {
        if (static::hizliKayitBaglamaModu()) {
            return [];
        }

        return [
            YapilanTahsilatlarRelationManager::class,
        ];
    }

    private static function hizliKayitBaglamaModu(): bool
    {
        $routeName = (string) (request()->route()?->getName() ?? '');

        return (
            str_ends_with($routeName, '.view')
            || str_ends_with($routeName, '.view-detail')
        )
            && ! request()->boolean('detay');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeknikServisKayitlari::route('/'),
            'yeni' => Pages\TeknikServisKayitlariYeniSayfasi::route('/liste/yeni'),
            'acik' => Pages\TeknikServisKayitlariAcikSayfasi::route('/liste/acik'),
            'tezgahta' => Pages\TeknikServisKayitlariTezgahtaSayfasi::route('/liste/tezgahta'),
            'parca_bekleyen' => Pages\TeknikServisKayitlariParcaBekleyenSayfasi::route('/liste/parca-bekleyen'),
            'garantiye_gonderilen' => Pages\TeknikServisKayitlariGarantiyeGonderilenSayfasi::route('/liste/garantiye-gonderilen'),
            'fiyat_verilen' => Pages\TeknikServisKayitlariFiyatVerilenSayfasi::route('/liste/fiyat-verilen'),
            'teslim_bekleyen' => Pages\TeknikServisKayitlariTeslimBekleyenSayfasi::route('/liste/teslim-bekleyen'),
            'tamamlanan_dis_servis' => Pages\TeknikServisKayitlariTamamlananDisServisSayfasi::route('/liste/tamamlanan-dis-servis'),
            'teslim_edilen' => Pages\TeknikServisKayitlariTeslimEdilenSayfasi::route('/liste/teslim-edilen'),
            'iptal' => Pages\TeknikServisKayitlariIptalSayfasi::route('/liste/iptal'),
            'iade' => Pages\TeknikServisKayitlariIadeSayfasi::route('/liste/iade'),
            'create_arizali_detail' => Pages\ArizaliCihazKaydiOlusturSayfasi::route('/olustur/arizali-cihaz/detay'),
            'create_arizali' => Pages\ArizaliCihazKaydiOlusturSayfasi::route('/olustur/arizali-cihaz'),
            'create_dis_servis_detail' => Pages\DisServisKaydiOlusturSayfasi::route('/olustur/dis-servis/detay'),
            'create_dis_servis' => Pages\DisServisKaydiOlusturSayfasi::route('/olustur/dis-servis'),
            'create_bakim_detail' => Pages\BakimKaydiOlusturSayfasi::route('/olustur/bakim/detay'),
            'create_bakim' => Pages\BakimKaydiOlusturSayfasi::route('/olustur/bakim'),
            'muhasebe_kontrol' => Pages\TeknikServisMuhasebeKontrolRaporuSayfasi::route('/muhasebe-kontrol'),
            'view-detail' => Pages\TeknikServisKaydiGoruntule::route('/{record}/detay'),
            'view' => Pages\TeknikServisKaydiHizliGoruntule::route('/{record}'),
            'edit' => Pages\TeknikServisKaydiDuzenle::route('/{record}/duzenle'),
        ];
    }
}
