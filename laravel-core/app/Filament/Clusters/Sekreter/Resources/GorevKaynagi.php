<?php

namespace App\Filament\Clusters\Sekreter\Resources;

use App\Filament\Clusters\Sekreter as SekreterCluster;
use App\Filament\Clusters\Sekreter\Resources\GorevKaynagi\Pages;
use App\Filament\Support\HasTenantVisibility;
use App\Models\SekreterGorevi;
use App\Models\Muhasebe\Cari;
use App\Models\Personel\Personel;
use App\Services\ModulErisimService;
use App\Services\TenantContextService;
use App\Support\SekreterYetkiSablonlari;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GorevKaynagi extends Resource
{
    use HasTenantVisibility;

    protected static ?string $model = SekreterGorevi::class;
    protected static ?string $cluster = SekreterCluster::class;
    protected static bool $shouldRegisterNavigation = true;
    protected static ?string $navigationIcon = 'heroicon-o-check-circle';
    protected static ?string $navigationLabel = 'Görevler';
    protected static ?string $navigationGroup = 'Ajanda ve Görevler';
    protected static ?int $navigationSort = 3;
    protected static ?string $modelLabel = 'Görev';
    protected static ?string $pluralModelLabel = 'Görevler';
    protected static ?string $slug = 'gorevler';
    protected static string $modulKodu = 'sekreter';
    protected static string $goruntuleYetkiKodu = SekreterYetkiSablonlari::GORUNTULE;
    protected static string $olusturYetkiKodu = SekreterYetkiSablonlari::OLUSTUR;
    protected static string $guncelleYetkiKodu = SekreterYetkiSablonlari::GUNCELLE;
    protected static string $silYetkiKodu = SekreterYetkiSablonlari::SIL;

    public static function form(Form $form): Form
    {
        $firmaId = app(TenantContextService::class)->aktifFirmaId();
        $cariAktif = $firmaId && app(ModulErisimService::class)->modulErisilebilirMi((int) $firmaId, 'muhasebe');
        $personelAktif = $firmaId && app(ModulErisimService::class)->modulErisilebilirMi((int) $firmaId, 'personel_takip');

        return $form->schema([
            Forms\Components\TextInput::make('baslik')->label('Başlık')->required()->maxLength(255),
            Forms\Components\Textarea::make('aciklama')->label('Açıklama')->rows(3),
            Forms\Components\DatePicker::make('tarih')->label('Tarih')->required()->default(today()),
            Forms\Components\TimePicker::make('saat')->label('Saat')->seconds(false),
            Forms\Components\Select::make('durum')->label('Durum')->options(['bekliyor' => 'Bekliyor', 'devam_ediyor' => 'Devam Ediyor', 'tamamlandi' => 'Tamamlandı', 'iptal' => 'İptal'])->default('bekliyor')->required(),
            Forms\Components\Select::make('oncelik')->label('Öncelik')->options(['yuksek' => '🔴 Yüksek', 'normal' => '🟡 Normal', 'dusuk' => '🟢 Düşük'])->default('normal')->required(),
            Forms\Components\Select::make('atanan_kullanici_id')->label('Atanan kullanıcı')->relationship('atananKullanici', 'name')->searchable()->preload(),
            Forms\Components\Select::make('atanan_personel_id')->label('Atanan personel')->options($personelAktif ? Personel::query()->where('durum', 'aktif')->orderBy('ad_soyad')->pluck('ad_soyad', 'id')->all() : [])->searchable()->visible($personelAktif)->dehydrated($personelAktif),
            Forms\Components\Select::make('cari_id')->label('Cari')->options($cariAktif ? Cari::query()->orderBy('ad')->limit(500)->pluck('ad', 'id')->all() : [])->searchable()->visible($cariAktif)->dehydrated($cariAktif),
            Forms\Components\Select::make('hatirlatma_tipi')->label('Hatırlatma')->options(['yok' => 'Yok', 'etkinlik' => 'Etkinlik zamanında', '5_dk' => '5 dakika önce', '15_dk' => '15 dakika önce', '30_dk' => '30 dakika önce', '1_saat' => '1 saat önce', '1_gun' => '1 gün önce', '1_hafta' => '1 hafta önce'])->default('yok'),
            Forms\Components\Select::make('tekrar_tipi')->label('Tekrar')->options(['yok' => 'Tekrar yok', 'gunluk' => 'Her gün', 'haftalik' => 'Her hafta', 'aylik' => 'Her ay', 'yillik' => 'Her yıl'])->default('yok'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('baslik')->label('Görev')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('tarih')->label('Tarih')->date('d.m.Y')->sortable(),
            Tables\Columns\TextColumn::make('durum')->label('Durum')->badge()->formatStateUsing(fn (string $state): string => ['bekliyor' => 'Bekliyor', 'devam_ediyor' => 'Devam Ediyor', 'tamamlandi' => 'Tamamlandı', 'iptal' => 'İptal'][$state] ?? $state),
            Tables\Columns\TextColumn::make('oncelik')->label('Öncelik')->badge()->formatStateUsing(fn (string $state): string => ['yuksek' => 'Yüksek', 'normal' => 'Normal', 'dusuk' => 'Düşük'][$state] ?? $state),
            Tables\Columns\TextColumn::make('cari.ad')->label('Cari')->placeholder('-')->toggleable(),
            Tables\Columns\IconColumn::make('gecikti_mi')->label('Gecikmiş')->boolean(),
        ])->defaultSort('tarih')->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])->headerActions([
            Tables\Actions\CreateAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListGorevler::route('/'), 'create' => Pages\CreateGorev::route('/create'), 'edit' => Pages\EditGorev::route('/{record}/edit')];
    }
}
