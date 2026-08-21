<?php

namespace App\Filament\Clusters\Sekreter\Resources;

use App\Filament\Clusters\Sekreter as SekreterCluster;
use App\Filament\Clusters\Sekreter\Resources\RandevuKaynagi\Pages;
use App\Filament\Support\HasTenantVisibility;
use App\Models\Muhasebe\Cari;
use App\Models\SekreterRandevusu;
use App\Services\ModulErisimService;
use App\Services\TenantContextService;
use App\Support\SekreterYetkiSablonlari;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RandevuKaynagi extends Resource
{
    use HasTenantVisibility;

    protected static ?string $model = SekreterRandevusu::class;
    protected static ?string $cluster = SekreterCluster::class;
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'Randevular';
    protected static ?string $modelLabel = 'Randevu';
    protected static ?string $pluralModelLabel = 'Randevular';
    protected static ?string $slug = 'randevular';
    protected static string $modulKodu = 'sekreter';
    protected static string $goruntuleYetkiKodu = SekreterYetkiSablonlari::GORUNTULE;
    protected static string $olusturYetkiKodu = SekreterYetkiSablonlari::OLUSTUR;
    protected static string $guncelleYetkiKodu = SekreterYetkiSablonlari::GUNCELLE;
    protected static string $silYetkiKodu = SekreterYetkiSablonlari::SIL;

    public static function form(Form $form): Form
    {
        $firmaId = app(TenantContextService::class)->aktifFirmaId();
        $cariAktif = $firmaId && app(ModulErisimService::class)->modulErisilebilirMi((int) $firmaId, 'muhasebe');

        return $form->schema([
            Forms\Components\TextInput::make('baslik')->label('Başlık')->required()->maxLength(255),
            Forms\Components\DatePicker::make('baslangic_tarihi')->label('Başlangıç tarihi')->required()->default(today()),
            Forms\Components\TimePicker::make('baslangic_saati')->label('Başlangıç saati')->required()->seconds(false),
            Forms\Components\DatePicker::make('bitis_tarihi')->label('Bitiş tarihi')->required()->default(today()),
            Forms\Components\TimePicker::make('bitis_saati')->label('Bitiş saati')->required()->seconds(false),
            Forms\Components\Textarea::make('aciklama')->label('Açıklama')->rows(4)->columnSpanFull(),
            Forms\Components\Select::make('cari_id')->label('Cari')->options($cariAktif ? Cari::query()->orderBy('ad')->limit(500)->pluck('ad', 'id')->all() : [])->searchable()->visible($cariAktif)->dehydrated($cariAktif),
            Forms\Components\Select::make('hatirlatma_tipi')->label('Hatırlatma')->options(['yok' => 'Yok', 'etkinlik' => 'Etkinlik zamanında', '5_dk' => '5 dakika önce', '15_dk' => '15 dakika önce', '30_dk' => '30 dakika önce', '1_saat' => '1 saat önce', '1_gun' => '1 gün önce', '1_hafta' => '1 hafta önce'])->default('yok'),
            Forms\Components\Select::make('tekrar_tipi')->label('Tekrar')->options(['yok' => 'Tekrar yok', 'gunluk' => 'Her gün', 'haftalik' => 'Her hafta', 'aylik' => 'Her ay', 'yillik' => 'Her yıl'])->default('yok'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([Tables\Columns\TextColumn::make('baslik')->label('Başlık'), Tables\Columns\TextColumn::make('baslangic_tarihi')->label('Tarih')->date('d.m.Y'), Tables\Columns\TextColumn::make('baslangic_saati')->label('Saat'), Tables\Columns\TextColumn::make('cari.ad')->label('Cari')])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListRandevular::route('/'), 'create' => Pages\CreateRandevu::route('/create'), 'edit' => Pages\EditRandevu::route('/{record}/edit')];
    }
}
