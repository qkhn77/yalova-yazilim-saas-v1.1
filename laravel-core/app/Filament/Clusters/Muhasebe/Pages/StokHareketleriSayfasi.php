<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe as MuhasebeCluster;
use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeSayfaErisimleri;
use App\Models\Muhasebe\Depo;
use App\Models\Muhasebe\StokHareketi;
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

class StokHareketleriSayfasi extends Page implements HasTable
{
    use InteractsWithTable;
    use MuhasebeSayfaErisimleri;

    protected static ?string $cluster = MuhasebeCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Stok Hareketleri';

    protected static ?string $slug = 'stok/stok-hareketleri';

    protected static string $view = 'filament.clusters.muhasebe.pages.stok-hareketleri';

    public function getTitle(): string|Htmlable
    {
        return 'Stok Hareketleri';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Stok Hareketleri';
    }

    public function getSubheading(): ?string
    {
        return 'Stok giriş/çıkış hareketleri, maliyet ve referans alanlarıyla birlikte listelenir.';
    }

    protected static function gerekliYetkiKodu(): string
    {
        return MuhasebeYetkiSablonlari::STOK_GORUNTULE;
    }

    /**
     * @return array<int, string>
     */
    protected static function muhasebeSayfasiYetkiKodlari(): array
    {
        return [
            MuhasebeYetkiSablonlari::STOK_GORUNTULE,
            MuhasebeYetkiSablonlari::STOK_GUNCELLE,
            MuhasebeYetkiSablonlari::STOK_SIL,
        ];
    }

    public function getSubNavigation(): array
    {
        return [];
    }

    public function table(Table $table): Table
    {
        $compactCell = ['class' => '!px-2 !py-2 align-middle'];
        $compactHeader = ['class' => '!px-2 !py-2 text-xs leading-tight'];

        return $table
            ->heading('Stok Hareketleri')
            ->query(static::stokHareketSorgusu())
            ->columns([
                Tables\Columns\TextColumn::make('islem_tarihi')
                    ->label('İşlem tarihi')
                    ->wrapHeader()
                    ->extraHeaderAttributes($compactHeader)
                    ->extraCellAttributes($compactCell)
                    ->size('sm')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('stokKarti.ad')
                    ->label('Stok')
                    ->width('16rem')
                    ->wrapHeader()
                    ->extraHeaderAttributes($compactHeader)
                    ->extraCellAttributes($compactCell)
                    ->size('sm')
                    ->lineClamp(1)
                    ->tooltip(fn ($state): ?string => filled($state) ? (string) $state : null)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('depo.ad')
                    ->label('Depo')
                    ->placeholder('Genel stok')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('cari.kod')
                    ->label('Cari bilgisi')
                    ->width('18rem')
                    ->wrapHeader()
                    ->extraHeaderAttributes($compactHeader)
                    ->extraHeaderAttributes(['style' => 'width: 18rem; max-width: 18rem;'], merge: true)
                    ->extraCellAttributes($compactCell)
                    ->extraCellAttributes(['style' => 'width: 18rem; max-width: 18rem;'], merge: true)
                    ->size('sm')
                    ->limit(32)
                    ->lineClamp(1)
                    ->extraAttributes([
                        'class' => '!min-w-0 !max-w-[18rem] !overflow-hidden',
                        'style' => 'width: 18rem; max-width: 18rem; min-width: 0;',
                    ])
                    ->formatStateUsing(fn ($state, StokHareketi $record): string => $record->cari
                        ? trim((string) $state.' - '.(string) $record->cari->ad)
                        : '—')
                    ->tooltip(fn (StokHareketi $record): ?string => $record->cari
                        ? trim((string) $record->cari->kod.' - '.(string) $record->cari->ad)
                        : null)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('yon')
                    ->label('Yön')
                    ->wrapHeader()
                    ->extraHeaderAttributes($compactHeader)
                    ->extraCellAttributes($compactCell)
                    ->size('sm')
                    ->getStateUsing(fn (StokHareketi $record): string => match ($record->islem_turu) {
                        StokHareketIslemTuru::Acilis => 'Giriş',
                        StokHareketIslemTuru::Alis,
                        StokHareketIslemTuru::Iade,
                        StokHareketIslemTuru::SatisIadesi,
                        StokHareketIslemTuru::TransferGiris => 'Giriş',
                        StokHareketIslemTuru::AcilisIptali,
                        StokHareketIslemTuru::Satis,
                        StokHareketIslemTuru::AlisIadesi,
                        StokHareketIslemTuru::TransferCikis => 'Çıkış',
                        default => '—',
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Giriş' => 'success',
                        'Çıkış' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'Giriş' => 'heroicon-m-arrow-down',
                        'Çıkış' => 'heroicon-m-arrow-up',
                        default => 'heroicon-m-minus',
                    }),
                Tables\Columns\TextColumn::make('islem_turu')
                    ->label('Hareket tipi')
                    ->wrapHeader()
                    ->extraHeaderAttributes($compactHeader)
                    ->extraCellAttributes($compactCell)
                    ->size('sm')
                    ->formatStateUsing(fn (?StokHareketIslemTuru $state) => $state?->value ?? '—')
                    ->badge(),
                Tables\Columns\TextColumn::make('miktar')
                    ->label('Miktar')
                    ->wrapHeader()
                    ->extraHeaderAttributes($compactHeader)
                    ->extraCellAttributes($compactCell)
                    ->size('sm')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),
                Tables\Columns\TextColumn::make('takip_ozeti')
                    ->label('Seri numaraları')
                    ->getStateUsing(fn (StokHareketi $record): string => static::takipOzeti($record))
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('onceki_miktar')
                    ->label('Önceki miktar')
                    ->wrapHeader()
                    ->extraHeaderAttributes($compactHeader)
                    ->extraCellAttributes($compactCell)
                    ->size('sm')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),
                Tables\Columns\TextColumn::make('sonraki_miktar')
                    ->label('Sonraki miktar')
                    ->wrapHeader()
                    ->extraHeaderAttributes($compactHeader)
                    ->extraCellAttributes($compactCell)
                    ->size('sm')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),
                Tables\Columns\TextColumn::make('birim_maliyet')
                    ->label('Birim maliyet')
                    ->wrapHeader()
                    ->extraHeaderAttributes($compactHeader)
                    ->extraCellAttributes($compactCell)
                    ->size('sm')
                    ->money('TRY')
                    ->sortable(),
                Tables\Columns\TextColumn::make('toplam_maliyet')
                    ->label('Toplam maliyet')
                    ->wrapHeader()
                    ->extraHeaderAttributes($compactHeader)
                    ->extraCellAttributes($compactCell)
                    ->size('sm')
                    ->money('TRY')
                    ->sortable(),
                Tables\Columns\TextColumn::make('referans_tipi')
                    ->label('Referans tipi')
                    ->wrapHeader()
                    ->extraHeaderAttributes($compactHeader)
                    ->extraCellAttributes($compactCell)
                    ->size('sm')
                    ->sortable(),
                Tables\Columns\TextColumn::make('referans_id')
                    ->label('Referans ID')
                    ->wrapHeader()
                    ->extraHeaderAttributes($compactHeader)
                    ->extraCellAttributes($compactCell)
                    ->size('sm')
                    ->sortable(),
                Tables\Columns\TextColumn::make('aciklama')
                    ->label('Açıklama')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('islem_tarihi', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('stok_id')
                    ->label('Stok kartı')
                    ->relationship('stokKarti', 'ad')
                    ->searchable(),
                Tables\Filters\SelectFilter::make('depo_id')
                    ->label('Depo')
                    ->options(fn (): array => Depo::query()->aktif()->orderBy('ad')->pluck('ad', 'id')->all())
                    ->default(fn (): ?int => request()->integer('depo_id') ?: null),
                Tables\Filters\SelectFilter::make('islem_turu')
                    ->label('Hareket tipi')
                    ->options(collect(StokHareketIslemTuru::cases())->mapWithKeys(
                        fn (StokHareketIslemTuru $tip) => [$tip->value => $tip->value]
                    )),
                Tables\Filters\Filter::make('islem_tarihi')
                    ->form([
                        Forms\Components\DatePicker::make('baslangic')->label('Başlangıç'),
                        Forms\Components\DatePicker::make('bitis')->label('Bitiş'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['baslangic'] ?? null, fn (Builder $q, $d) => $q->where('islem_tarihi', '>=', (string) $d.' 00:00:00'))
                        ->when($data['bitis'] ?? null, fn (Builder $q, $d) => $q->where('islem_tarihi', '<=', (string) $d.' 23:59:59'))),
            ])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    public static function stokHareketSorgusu(): Builder
    {
        return StokHareketi::query()
            ->select([
                'id',
                'firma_id',
                'cari_id',
                'islem_tarihi',
                'stok_id',
                'depo_id',
                'islem_turu',
                'miktar',
                'onceki_miktar',
                'sonraki_miktar',
                'birim_maliyet',
                'toplam_maliyet',
                'referans_tipi',
                'referans_id',
                'aciklama',
                'durum',
            ])
            ->with([
                'stokKarti:id,firma_id,ad',
                'cari:id,firma_id,kod,ad',
                'depo:id,ad',
                'seriHareketleri:id,firma_id,stok_hareketi_id,stok_seri_no_id',
                'seriHareketleri.seriNo:id,firma_id,seri_no,barkod',
            ])
            ->where('durum', StokHareketDurumu::Aktif);
    }

    public static function takipOzeti(StokHareketi $hareket): string
    {
        $satirlar = collect();

        $seriler = $hareket->seriHareketleri
            ->map(fn ($seriHareketi): string => (string) ($seriHareketi->seriNo?->barkod ?: $seriHareketi->seriNo?->seri_no ?: ''))
            ->filter()
            ->unique()
            ->map(fn (string $seri): string => 'Seri: '.$seri);

        return $satirlar
            ->concat($seriler)
            ->unique()
            ->values()
            ->implode(', ');
    }
}
