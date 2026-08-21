<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\BarkodluSatis\Guvenlik\BarkodluSatisFilamentErisimYardimcisi;
use App\Filament\Clusters\Muhasebe as MuhasebeCluster;
use App\Filament\Clusters\Muhasebe\Resources\StokKartiKaynagi;
use App\Models\Muhasebe\StokBarkodu;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BarkodluSatisBarkodListesiSayfasi extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $cluster = MuhasebeCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Barkod esleme listesi';

    protected static ?string $slug = 'satis/barkod-esleme-listesi';

    protected static string $view = 'filament.clusters.muhasebe.pages.barkodlu-satis-barkod-listesi-sayfasi';

    public function getHeading(): string|Htmlable
    {
        return 'Barkod esleme listesi';
    }

    public function getSubheading(): ?string
    {
        return 'Bir urune bagli tum barkodlari izleyin, filtreleyin ve stok kartina hizli gecin.';
    }

    public static function canAccess(): bool
    {
        return BarkodluSatisFilamentErisimYardimcisi::herhangiBirBarkodluSatisYetkisiVarMi([
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_GORUNTULE,
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_GUNCELLE,
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_ETIKET_YAZDIR,
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                StokBarkodu::query()
                    ->select([
                        'id',
                        'firma_id',
                        'stok_id',
                        'barkod',
                        'aktif',
                        'varsayilan_mi',
                        'updated_at',
                    ])
                    ->with('stok:id,firma_id,kod,ad')
            )
            ->headerActions([
                Tables\Actions\Action::make('export_csv')
                    ->label('CSV Disa Aktar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(fn (): StreamedResponse => $this->barkodEslemeCsvIndir(false)),
                Tables\Actions\Action::make('export_excel_csv')
                    ->label('Excel Uyumlu CSV')
                    ->icon('heroicon-o-document-chart-bar')
                    ->color('success')
                    ->action(fn (): StreamedResponse => $this->barkodEslemeCsvIndir(true)),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('barkod')
                    ->label('Barkod')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('stok.kod')
                    ->label('Stok kodu')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('stok.ad')
                    ->label('Urun')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\IconColumn::make('aktif')
                    ->label('Aktif')
                    ->boolean(),
                Tables\Columns\IconColumn::make('varsayilan_mi')
                    ->label('Varsayilan')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Guncellenme')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('aktif')
                    ->label('Aktiflik')
                    ->placeholder('Hepsi')
                    ->trueLabel('Aktif')
                    ->falseLabel('Pasif'),
                Tables\Filters\TernaryFilter::make('varsayilan_mi')
                    ->label('Varsayilan')
                    ->placeholder('Hepsi')
                    ->trueLabel('Varsayilan')
                    ->falseLabel('Degil'),
            ])
            ->actions([
                Tables\Actions\Action::make('stok_karti')
                    ->label('Stok Karti')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (StokBarkodu $record): string => StokKartiKaynagi::getUrl('edit', ['record' => (int) $record->stok_id]))
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    public function barkodEslemeCsvIndir(bool $excelUyumlu = false): StreamedResponse
    {
        $sorgu = $this->getFilteredSortedTableQuery()->clone()->with('stok');
        $delimiter = $excelUyumlu ? ';' : ',';
        $dosyaAdi = 'barkod-esleme-listesi-'.now()->format('Ymd_His').($excelUyumlu ? '-excel' : '').'.csv';

        return response()->streamDownload(function () use ($sorgu, $delimiter, $excelUyumlu): void {
            $out = fopen('php://output', 'wb');
            if (! is_resource($out)) {
                return;
            }

            if ($excelUyumlu) {
                fwrite($out, "\xEF\xBB\xBF");
            }

            foreach ($this->csvFiltreOzetSatirlari() as $satir) {
                fputcsv($out, $satir, $delimiter);
            }
            fputcsv($out, [], $delimiter);
            fputcsv($out, ['Barkod', 'Stok Kodu', 'Urun', 'Aktif', 'Varsayilan', 'Guncellenme'], $delimiter);

            /** @var StokBarkodu $kayit */
            foreach ($sorgu->cursor() as $kayit) {
                fputcsv($out, [
                    (string) $kayit->barkod,
                    (string) ($kayit->stok?->kod ?? ''),
                    (string) ($kayit->stok?->ad ?? ''),
                    $kayit->aktif ? 'Evet' : 'Hayir',
                    $kayit->varsayilan_mi ? 'Evet' : 'Hayir',
                    optional($kayit->updated_at)->format('d.m.Y H:i') ?: '',
                ], $delimiter);
            }

            fclose($out);
        }, $dosyaAdi, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function csvFiltreOzetSatirlari(): array
    {
        $satirlar = [];
        $satirlar[] = ['Rapor', 'Barkod Eslestirme Listesi'];
        $satirlar[] = ['Olusturulma', now()->format('d.m.Y H:i:s')];

        $arama = trim((string) ($this->getTableSearch() ?? ''));
        $satirlar[] = ['Global Arama', $arama !== '' ? $arama : '-'];

        $siralaKolon = (string) ($this->getTableSortColumn() ?? '');
        $siralaYon = (string) ($this->getTableSortDirection() ?? '');
        $sirala = $siralaKolon !== '' ? $siralaKolon.' '.($siralaYon !== '' ? $siralaYon : 'asc') : 'varsayilan';
        $satirlar[] = ['Siralama', $sirala];

        $aktif = Arr::get($this->getTableFilterState('aktif') ?? [], 'value');
        $varsayilan = Arr::get($this->getTableFilterState('varsayilan_mi') ?? [], 'value');

        $satirlar[] = ['Filtre Aktiflik', $this->ternaryEtiket($aktif)];
        $satirlar[] = ['Filtre Varsayilan', $this->ternaryEtiket($varsayilan)];

        return $satirlar;
    }

    private function ternaryEtiket(mixed $deger): string
    {
        if ($deger === null || $deger === '') {
            return 'Hepsi';
        }

        return filter_var($deger, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ? 'Evet' : 'Hayir';
    }
}
