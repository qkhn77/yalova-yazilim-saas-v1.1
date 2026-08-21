<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeSayfaErisimleri;
use App\Models\Muhasebe\StokHareketi;
use App\Filament\Clusters\Muhasebe as MuhasebeCluster;
use App\Muhasebe\Enumlar\StokBelgeTuru;
use App\Muhasebe\Enumlar\StokHareketDurumu;
use App\Muhasebe\Enumlar\StokHareketIslemTuru;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class StokDepoSayimGecmisiSayfasi extends Page implements HasTable
{
    use InteractsWithTable;
    use MuhasebeSayfaErisimleri;

    protected static ?string $cluster = MuhasebeCluster::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string $view = 'filament.clusters.muhasebe.pages.stok-depo-sayim-gecmisi';

    protected static ?string $title = 'Depo sayım geçmişi';

    protected static ?string $slug = 'stok/depo-sayim-gecmisi';

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
        return 'Depo sayım geçmişi';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Depo sayım geçmişi';
    }

    public function getSubheading(): ?string
    {
        return 'Depo sayımlarında oluşan fark düzeltmelerini ve stok etkisini izleyin.';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(static::sayimSorgusu())
            ->columns([
                Tables\Columns\TextColumn::make('islem_tarihi')
                    ->label('Tarih')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('stokKarti.kod')
                    ->label('Stok kodu')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('stokKarti.ad')
                    ->label('Stok')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('depo.ad')
                    ->label('Depo')
                    ->placeholder('Deposuz')
                    ->sortable(),
                Tables\Columns\TextColumn::make('onceki_miktar')
                    ->label('Önceki miktar')
                    ->numeric(decimalPlaces: 4),
                Tables\Columns\TextColumn::make('sonraki_miktar')
                    ->label('Sonraki miktar')
                    ->numeric(decimalPlaces: 4),
                Tables\Columns\TextColumn::make('fark')
                    ->label('Fark')
                    ->getStateUsing(fn (StokHareketi $record): string => $record->islem_turu === StokHareketIslemTuru::Satis
                        ? '-'.number_format((float) $record->miktar, 4, ',', '.')
                        : '+'.number_format((float) $record->miktar, 4, ',', '.'))
                    ->badge()
                    ->color(fn (string $state): string => str_starts_with($state, '-') ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('aciklama')
                    ->label('Açıklama')
                    ->limit(45)
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('stok_id')
                    ->label('Stok')
                    ->relationship('stokKarti', 'ad')
                    ->searchable(),
                Tables\Filters\SelectFilter::make('depo_id')
                    ->label('Depo')
                    ->relationship('depo', 'ad')
                    ->searchable(),
                Tables\Filters\Filter::make('islem_tarihi')
                    ->label('Tarih aralığı')
                    ->form([
                        Forms\Components\DatePicker::make('baslangic')->label('Başlangıç'),
                        Forms\Components\DatePicker::make('bitis')->label('Bitiş'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['baslangic'] ?? null, fn (Builder $q, $d): Builder => $q->where('islem_tarihi', '>=', (string) $d.' 00:00:00'))
                        ->when($data['bitis'] ?? null, fn (Builder $q, $d): Builder => $q->where('islem_tarihi', '<=', (string) $d.' 23:59:59'))),
            ])
            ->defaultSort('islem_tarihi', 'desc')
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    public static function sayimSorgusu(): Builder
    {
        return StokHareketi::query()
            ->where('belge_turu', StokBelgeTuru::Sayim)
            ->where('durum', StokHareketDurumu::Aktif)
            ->with([
                'stokKarti:id,firma_id,kod,ad',
                'depo:id,firma_id,kod,ad',
            ]);
    }
}
