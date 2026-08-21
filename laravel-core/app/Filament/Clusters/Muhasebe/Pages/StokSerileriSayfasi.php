<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe;
use App\Filament\Clusters\Muhasebe\Resources\StokKartiKaynagi;
use App\Models\Muhasebe\Depo;
use App\Models\Muhasebe\StokSeriNo;
use App\Services\TenantContextService;
use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeSayfaErisimleri;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Actions;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StokSerileriSayfasi extends Page implements HasForms
{
    use InteractsWithForms;
    use MuhasebeSayfaErisimleri;

    protected static ?string $cluster = Muhasebe::class;
    protected static ?string $navigationIcon = 'heroicon-o-qr-code';
    protected static string $view = 'filament.clusters.muhasebe.pages.stok-serileri-sayfasi';
    protected static ?string $title = 'Seri No Barkodları';
    protected static ?string $slug = 'stok/seri-no-barkodlari';

    public ?array $data = [];

    protected static function gerekliYetkiKodu(): string
    {
        return MuhasebeYetkiSablonlari::STOK_SERI_GORUNTULE;
    }

    protected static function muhasebeSayfasiYetkiKodlari(): array
    {
        return [MuhasebeYetkiSablonlari::STOK_SERI_GORUNTULE, MuhasebeYetkiSablonlari::STOK_GORUNTULE];
    }

    public function mount(): void
    {
        $durum = (string) request()->query('durum', 'stokta');
        $this->form->fill(['durum' => in_array($durum, ['stokta', 'satildi', 'cikti', 'tumu'], true) ? $durum : 'stokta']);
    }

    public function form(Form $form): Form
    {
        $firmaId = $this->firmaId();

        return $form->schema([
            Forms\Components\Select::make('depo_id')
                ->label('Depo')
                ->options(fn (): array => [0 => 'Tüm depolar'] + Depo::query()->where('firma_id', $firmaId)->aktif()->orderBy('ad')->pluck('ad', 'id')->all())
                ->default(0)
                ->live(),
            Forms\Components\Select::make('durum')
                ->label('Durum')
                ->options(['stokta' => 'Stokta olanlar', 'satildi' => 'Satılanlar', 'cikti' => 'Çıkış yapılanlar', 'tumu' => 'Tüm kayıtlar'])
                ->default('stokta')
                ->live(),
            Forms\Components\TextInput::make('arama')
                ->label('Ürün veya Seri No Barkodu ara')
                ->placeholder('Seri no, barkod, ürün adı veya kod')
                ->live(debounce: 350)
                ->columnSpan(2),
        ])->columns(2)->statePath('data');
    }

    public function getSerilerProperty()
    {
        return $this->filtreliSeriSorgusu()
            ->with([
                'stokKarti:id,firma_id,kod,ad,birim',
                'depo:id,ad',
                'hareketler.stokHareketi:id,islem_turu,tarih,birim_fiyat,toplam',
            ])
            ->orderBy('seri_no')
            ->limit(500)
            ->get();
    }

    private function filtreliSeriSorgusu(): Builder
    {
        $state = $this->form->getState();
        $arama = trim((string) ($state['arama'] ?? ''));
        $depoId = (int) ($state['depo_id'] ?? 0);
        $durum = (string) ($state['durum'] ?? 'stokta');

        return StokSeriNo::query()
            ->where('firma_id', $this->firmaId())
            ->when($depoId > 0, fn (Builder $query): Builder => $query->where('depo_id', $depoId))
            ->when($durum !== 'tumu', fn (Builder $query): Builder => $query->where('durum', $durum))
            ->when($arama !== '', function (Builder $query) use ($arama): Builder {
                return $query->where(function (Builder $inner) use ($arama): void {
                    $inner->where('seri_no', 'like', '%'.$arama.'%')
                        ->orWhere('barkod', 'like', '%'.$arama.'%')
                        ->orWhereHas('stokKarti', fn (Builder $stok): Builder => $stok->where('ad', 'like', '%'.$arama.'%')->orWhere('kod', 'like', '%'.$arama.'%'));
                });
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('seriCsvIndir')
                ->label('CSV indir')->icon('heroicon-o-arrow-down-tray')->color('gray')
                ->action(fn (): StreamedResponse => $this->seriCsvIndir(false)),
            Actions\Action::make('seriExcelCsvIndir')
                ->label('Excel uyumlu CSV')->icon('heroicon-o-document-chart-bar')->color('success')
                ->action(fn (): StreamedResponse => $this->seriCsvIndir(true)),
        ];
    }

    public function seriCsvIndir(bool $excelUyumlu = false): StreamedResponse
    {
        $seriler = $this->filtreliSeriSorgusu()->with([
            'stokKarti:id,firma_id,kod,ad,birim',
            'depo:id,ad',
            'hareketler.stokHareketi:id,islem_turu,tarih,birim_fiyat,toplam',
        ])->orderBy('seri_no')->limit(500)->get();
        $delimiter = $excelUyumlu ? ';' : ',';
        $dosyaAdi = 'seri-no-barkodlari-'.now()->format('Ymd_His').($excelUyumlu ? '-excel' : '').'.csv';

        return response()->streamDownload(function () use ($seriler, $delimiter, $excelUyumlu): void {
            $out = fopen('php://output', 'wb');
            if (! is_resource($out)) return;
            if ($excelUyumlu) fwrite($out, "\xEF\xBB\xBF");
            $state = $this->form->getState();
            fputcsv($out, ['Rapor', 'Seri No Barkodları'], $delimiter);
            fputcsv($out, ['Durum', (string) ($state['durum'] ?? 'stokta')], $delimiter);
            fputcsv($out, ['Depo', (string) ($state['depo_id'] ?? 0) === '0' ? 'Tüm depolar' : (string) ($state['depo_id'] ?? '')], $delimiter);
            fputcsv($out, ['Arama', (string) ($state['arama'] ?? '')], $delimiter);
            fputcsv($out, [], $delimiter);
            fputcsv($out, ['Ürün Kodu', 'Ürün', 'Seri No', 'Seri No Barkodu', 'Depo', 'Durum', 'Son Satış Tarihi', 'Satış Fiyatı', 'Gerçekleşen Kâr', 'Garanti Başlangıcı', 'Garanti Bitişi'], $delimiter);
            foreach ($seriler as $seri) {
                fputcsv($out, [
                    (string) ($seri->stokKarti?->kod ?? ''), (string) ($seri->stokKarti?->ad ?? ''), (string) $seri->seri_no, (string) ($seri->barkod ?? ''),
                    (string) ($seri->depo?->ad ?? 'Genel stok'), match ($seri->durum) { 'stokta' => 'Stokta', 'satildi' => 'Satıldı', default => 'Çıkış yapıldı' },
                    $this->seriSatisHareketi($seri)?->tarih?->format('d.m.Y H:i') ?? '',
                    $this->seriSatisFiyati($seri) !== null ? number_format($this->seriSatisFiyati($seri), 2, ',', '.') : '',
                    $this->seriGerceklesenKari($seri) !== null ? number_format($this->seriGerceklesenKari($seri), 2, ',', '.') : '',
                    $seri->garanti_baslangic_tarihi?->format('d.m.Y') ?? '', $seri->garanti_bitis_tarihi?->format('d.m.Y') ?? '',
                ], $delimiter);
            }
            fclose($out);
        }, $dosyaAdi, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function seriKartiUrl(int $stokId): string
    {
        return StokKartiKaynagi::getUrl('view', ['record' => $stokId, 'detay' => 1]);
    }

    public function seriSatisHareketi(StokSeriNo $seri): mixed
    {
        return $seri->hareketler
            ->map(fn ($hareket) => $hareket->stokHareketi)
            ->filter(fn ($hareket): bool => $hareket && (string) ($hareket->islem_turu?->value ?? $hareket->islem_turu ?? '') === 'satis')
            ->sortByDesc(fn ($hareket) => $hareket->tarih?->timestamp ?? 0)
            ->first();
    }

    public function seriSatisFiyati(StokSeriNo $seri): ?float
    {
        $hareket = $this->seriSatisHareketi($seri);

        return $hareket ? (float) ($hareket->birim_fiyat ?? 0) : null;
    }

    public function seriGerceklesenKari(StokSeriNo $seri): ?float
    {
        $fiyat = $this->seriSatisFiyati($seri);

        return $fiyat === null ? null : round($fiyat - (float) ($seri->birim_maliyet ?? 0), 2);
    }

    private function firmaId(): int
    {
        return (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
    }
}
