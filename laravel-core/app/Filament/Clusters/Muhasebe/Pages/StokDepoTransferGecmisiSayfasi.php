<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe as MuhasebeCluster;
use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeSayfaErisimleri;
use App\Models\Muhasebe\StokTransferi;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class StokDepoTransferGecmisiSayfasi extends Page implements HasTable
{
    use InteractsWithTable;
    use MuhasebeSayfaErisimleri;

    protected static ?string $cluster = MuhasebeCluster::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string $view = 'filament.clusters.muhasebe.pages.stok-depo-transfer-gecmisi';

    protected static ?string $title = 'Depo Transfer Geçmişi';

    protected static ?string $slug = 'stok/depo-transfer-gecmisi';

    protected static function gerekliYetkiKodu(): string
    {
        return MuhasebeYetkiSablonlari::DEPO_GORUNTULE;
    }

    public function getSubNavigation(): array
    {
        return [];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Depo transfer geçmişi';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Depo transfer geçmişi';
    }

    public function getSubheading(): ?string
    {
        return 'Depolar arasındaki tamamlanan stok transferlerini izleyin.';
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Transfer Kayıtları')
            ->emptyStateHeading('Transfer Kaydı Yok')
            ->query(static::transferSorgusu())
            ->columns([
                Tables\Columns\TextColumn::make('tarih')
                    ->label('Tarih')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('transfer_no')
                    ->label('Transfer no')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('cikisHareketi.stokKarti.ad')
                    ->label('Stok')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('kaynakDepo.ad')
                    ->label('Kaynak depo')
                    ->sortable(),
                Tables\Columns\TextColumn::make('hedefDepo.ad')
                    ->label('Hedef depo')
                    ->sortable(),
                Tables\Columns\TextColumn::make('cikisHareketi.miktar')
                    ->label('Miktar')
                    ->numeric(decimalPlaces: 4),
                Tables\Columns\TextColumn::make('durum')
                    ->label('Durum')
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('aciklama')
                    ->label('Açıklama')
                    ->limit(45)
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('kaynak_depo_id')
                    ->label('Kaynak depo')
                    ->relationship('kaynakDepo', 'ad')
                    ->searchable(),
                Tables\Filters\SelectFilter::make('hedef_depo_id')
                    ->label('Hedef depo')
                    ->relationship('hedefDepo', 'ad')
                    ->searchable(),
                Tables\Filters\Filter::make('tarih')
                    ->label('Tarih aralığı')
                    ->form([
                        Forms\Components\DatePicker::make('baslangic')->label('Başlangıç'),
                        Forms\Components\DatePicker::make('bitis')->label('Bitiş'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['baslangic'] ?? null, fn (Builder $q, $d): Builder => $q->where('tarih', '>=', (string) $d.' 00:00:00'))
                        ->when($data['bitis'] ?? null, fn (Builder $q, $d): Builder => $q->where('tarih', '<=', (string) $d.' 23:59:59'))),
            ])
            ->defaultSort('tarih', 'desc')
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    public static function transferSorgusu(): Builder
    {
        return StokTransferi::query()->with([
            'kaynakDepo:id,firma_id,kod,ad',
            'hedefDepo:id,firma_id,kod,ad',
            'cikisHareketi.stokKarti:id,firma_id,kod,ad',
        ]);
    }

}
