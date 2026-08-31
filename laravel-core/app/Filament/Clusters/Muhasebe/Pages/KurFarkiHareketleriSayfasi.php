<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe as MuhasebeCluster;
use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeSayfaErisimleri;
use App\Models\Muhasebe\KurFarkiHareketi;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class KurFarkiHareketleriSayfasi extends Page implements HasTable
{
    use InteractsWithTable;
    use MuhasebeSayfaErisimleri;

    protected static ?string $cluster = MuhasebeCluster::class;

    protected static ?string $title = 'Kur farkı hareketleri';

    protected static ?string $slug = 'finans/kur-farki-hareketleri';

    protected static ?string $navigationLabel = 'Kur farkı hareketleri';

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?int $navigationSort = 27;

    protected static string $view = 'filament.clusters.muhasebe.pages.kur-farki-hareketleri';

    protected static function gerekliYetkiKodu(): string
    {
        return MuhasebeYetkiSablonlari::FINANS_GORUNTULE;
    }

    public function getHeading(): string|Htmlable
    {
        return 'Kur farkı hareketleri';
    }

    public function getSubheading(): ?string
    {
        return 'Fatura kapamalarında oluşan kur kazanç ve kayıpları.';
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Kur Farkı Hareketleri')
            ->query(KurFarkiHareketi::query()
                ->select(['id', 'firma_id', 'fatura_id', 'finans_hareket_id', 'tutar', 'yon', 'para_birimi', 'durum', 'created_at'])
                ->with([
                    'fatura:id,firma_id,fatura_no,belge_no,cari_id,para_birimi',
                    'fatura.cari:id,ad,kod',
                ]))
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Tarih')->dateTime('d.m.Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('yon')
                    ->label('Yön')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'kazanc' ? 'Kazanç' : 'Zarar')
                    ->color(fn (string $state): string => $state === 'kazanc' ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('tutar')
                    ->label('Tutar')
                    ->formatStateUsing(fn ($state, KurFarkiHareketi $record): string => number_format(abs((float) $state), 2, ',', '.').' '.strtoupper((string) ($record->para_birimi ?: 'TRY')))
                    ->sortable(),
                Tables\Columns\TextColumn::make('fatura.fatura_no')
                    ->label('Fatura')
                    ->formatStateUsing(fn ($state, KurFarkiHareketi $record): string => (string) ($state ?: $record->fatura?->belge_no ?: '#'.$record->fatura_id))
                    ->searchable(),
                Tables\Columns\TextColumn::make('fatura.cari.ad')->label('Cari')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('finans_hareket_id')->label('Finans #')->sortable(),
                Tables\Columns\TextColumn::make('durum')->label('Durum')->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('yon')->label('Yön')->options(['kazanc' => 'Kazanç', 'zarar' => 'Zarar']),
                Tables\Filters\SelectFilter::make('durum')->options(['aktif' => 'Aktif', 'iptal' => 'İptal']),
                Tables\Filters\SelectFilter::make('para_birimi')->label('Baz para birimi')->options(['TRY' => 'TRY']),
                Tables\Filters\Filter::make('tarih')->form([
                    Forms\Components\DatePicker::make('baslangic')->label('Başlangıç'),
                    Forms\Components\DatePicker::make('bitis')->label('Bitiş'),
                ])->query(fn ($query, array $data) => $query
                    ->when($data['baslangic'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                    ->when($data['bitis'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 20, 50, 100]);
    }
}
