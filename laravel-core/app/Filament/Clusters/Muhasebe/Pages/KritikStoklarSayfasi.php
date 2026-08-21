<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe as MuhasebeCluster;
use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeSayfaErisimleri;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\Depo;
use App\Services\TenantContextService;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class KritikStoklarSayfasi extends Page implements HasTable
{
    use InteractsWithTable;
    use MuhasebeSayfaErisimleri;

    protected static ?string $cluster = MuhasebeCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Kritik Stoklar';

    protected static ?string $slug = 'stok/kritik-stoklar';

    protected static string $view = 'filament.clusters.muhasebe.pages.kritik-stoklar';

    public function getTitle(): string|Htmlable
    {
        return 'Kritik Stoklar';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Kritik Stoklar';
    }

    public function getSubheading(): ?string
    {
        return 'Mevcut stoğu minimum seviyesinin altında veya eşit olan aktif stok kartları.';
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
        return $table
            ->query(static::kritikStokSorgusu())
            ->columns([
                Tables\Columns\TextColumn::make('ad')
                    ->label('Stok adı')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('kod')
                    ->label('Kod')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('stok_miktari')
                    ->label('Mevcut stok')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),
                Tables\Columns\TextColumn::make('minimum_stok')
                    ->label('Minimum stok')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),
                Tables\Columns\TextColumn::make('fark')
                    ->label('Fark')
                    ->getStateUsing(fn (StokKarti $record): string => bcsub(
                        (string) ($record->stok_miktari ?? 0),
                        (string) ($record->minimum_stok ?? 0),
                        4
                    ))
                    ->numeric(decimalPlaces: 4),
                Tables\Columns\TextColumn::make('kategori.ad')
                    ->label('Kategori')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('durum')
                    ->label('Durum')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof HesapDurumu ? $state->value : (string) $state),
            ])
            ->defaultSort('fark', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('kategori_id')
                    ->label('Kategori')
                    ->relationship('kategori', 'ad')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('depo_id')
                    ->label('Depo')
                    ->options(fn (): array => static::depoSecenekleri())
                    ->visible(fn (): bool => static::depoSecenekleri() !== [])
                    ->query(function (Builder $query, array $data): Builder {
                        $depoId = (int) ($data['value'] ?? 0);
                        if ($depoId < 1) {
                            return $query;
                        }

                        return $query->whereExists(function ($altSorgu) use ($depoId): void {
                            $altSorgu->selectRaw('1')
                                ->from('stok_depo_bakiyeleri as sdb')
                                ->whereColumn('sdb.stok_id', 'stok_kartlari.id')
                                ->where('sdb.depo_id', $depoId)
                                ->whereRaw('COALESCE(sdb.miktar, 0) - COALESCE(sdb.rezerve_miktar, 0) <= COALESCE(stok_kartlari.minimum_stok, 0)');
                        });
                    }),
            ])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    public static function kritikStokSorgusu(): Builder
    {
        return StokKarti::query()
            ->select([
                'stok_kartlari.id',
                'stok_kartlari.firma_id',
                'stok_kartlari.kategori_id',
                'stok_kartlari.ad',
                'stok_kartlari.kod',
                'stok_kartlari.stok_miktari',
                'stok_kartlari.minimum_stok',
                'stok_kartlari.stok_takip',
                'stok_kartlari.durum',
            ])
            ->selectRaw('COALESCE(stok_miktari, 0) - COALESCE(minimum_stok, 0) as fark')
            ->with('kategori:id,firma_id,ad')
            ->where('stok_takip', true)
            ->where('durum', HesapDurumu::Aktif)
            ->whereNotNull('minimum_stok')
            ->whereColumn('stok_miktari', '<=', 'minimum_stok');
    }

    /** @return array<int|string, string> */
    private static function depoSecenekleri(): array
    {
        $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
        if ($firmaId < 1) {
            return [];
        }

        return Depo::tenantScopeOlmadan(fn () => Depo::query()
            ->where('firma_id', $firmaId)
            ->where('aktif_mi', true)
            ->orderBy('ad')
            ->pluck('ad', 'id')
            ->map(fn ($ad): string => (string) $ad)
            ->all());
    }
}
