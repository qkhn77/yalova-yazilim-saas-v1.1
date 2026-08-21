<?php

namespace App\Filament\Clusters\Sekreter\Resources;

use App\Filament\Clusters\Sekreter as SekreterCluster;
use App\Filament\Clusters\Sekreter\Resources\NotKaynagi\Pages;
use App\Filament\Support\HasTenantVisibility;
use App\Models\Muhasebe\Cari;
use App\Models\SekreterNotu;
use App\Services\ModulErisimService;
use App\Services\TenantContextService;
use App\Support\SekreterYetkiSablonlari;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NotKaynagi extends Resource
{
    use HasTenantVisibility;

    protected static ?string $model = SekreterNotu::class;
    protected static ?string $cluster = SekreterCluster::class;
    protected static bool $shouldRegisterNavigation = true;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Notlar';
    protected static ?string $navigationGroup = 'Ajanda ve Görevler';
    protected static ?int $navigationSort = 4;
    protected static ?string $modelLabel = 'Not';
    protected static ?string $pluralModelLabel = 'Notlar';
    protected static ?string $slug = 'notlar';
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
            Forms\Components\Textarea::make('icerik')->label('İçerik')->required()->rows(10)->columnSpanFull(),
            Forms\Components\TextInput::make('etiket')->label('Etiket')->maxLength(100),
            Forms\Components\Toggle::make('sabit_mi')->label('Sabitle'),
            Forms\Components\Select::make('cari_id')->label('Cari')->options($cariAktif ? Cari::query()->orderBy('ad')->limit(500)->pluck('ad', 'id')->all() : [])->searchable()->visible($cariAktif)->dehydrated($cariAktif),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\IconColumn::make('sabit_mi')->label('Sabit')->boolean(),
            Tables\Columns\TextColumn::make('baslik')->label('Başlık')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('etiket')->label('Etiket')->placeholder('-'),
            Tables\Columns\TextColumn::make('cari.ad')->label('Cari')->placeholder('-'),
            Tables\Columns\TextColumn::make('created_at')->label('Tarih')->dateTime('d.m.Y H:i')->sortable(),
        ])->defaultSort('sabit_mi', 'desc')->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])->headerActions([Tables\Actions\CreateAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListNotlar::route('/'), 'create' => Pages\CreateNot::route('/create'), 'edit' => Pages\EditNot::route('/{record}/edit')];
    }
}
