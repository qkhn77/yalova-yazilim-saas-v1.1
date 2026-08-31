<?php

namespace App\Filament\Clusters\ProjeYonetimi\Pages;

use App\Filament\Clusters\MasrafTakip\Kaynaklar\MasrafTakipSayfaErisimleri;
use App\Filament\Clusters\ProjeYonetimi\Pages\IsletmeProjeleriSayfasi;
use App\Filament\Clusters\ProjeYonetimi as ProjeYonetimiCluster;
use App\Models\Proje\IsletmeProjesi;
use App\Services\TenantContextService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;
use Filament\Actions\Action;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjeRaporlariSayfasi extends Page implements HasForms
{
    use InteractsWithForms;
    use MasrafTakipSayfaErisimleri;
    use WithPagination;

    protected static ?string $cluster = ProjeYonetimiCluster::class;
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $title = 'Proje Raporları';
    protected static ?string $slug = 'raporlar';
    protected static string $view = 'filament.clusters.proje-yonetimi.pages.proje-raporlari';

    /** @var array{baslangic:string,bitis:string,proje_id:int|string,durum:string} */
    public array $filtreler = [];

    public int $hareketlerPerPage = 25;

    public string $hareketArama = '';

    public function mount(): void
    {
        $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
        $projeId = (int) request()->query('proje_id', 0);
        $proje = $projeId > 0
            ? IsletmeProjesi::query()
                ->where('firma_id', $firmaId)
                ->kullaniciIcinGorunur(null, $firmaId)
                ->whereKey($projeId)
                ->first(['id', 'durum'])
            : null;
        $projeId = $proje?->id ? (int) $proje->id : 0;
        $projeDurumu = match ($proje?->durum) {
            IsletmeProjesi::DURUM_AKTIF => 'aktif',
            IsletmeProjesi::DURUM_TAMAMLANDI => IsletmeProjesi::DURUM_TAMAMLANDI,
            default => 'tumu',
        };
        $this->filtreler = [
            'baslangic' => now()->startOfMonth()->toDateString(),
            'bitis' => now()->toDateString(),
            'proje_id' => $projeId > 0 ? $projeId : '',
            'durum' => $projeId > 0 ? $projeDurumu : 'aktif',
        ];
        $this->filtreForm->fill($this->filtreler);
    }

    public function getHeading(): string
    {
        return 'Proje raporları';
    }

    public function getSubheading(): ?string
    {
        return 'Projeleri aynı tarih aralığında bütçe, masraf, gelir ve ödeme yönünden karşılaştırın.';
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('projelereDon')
                ->label('Projelere dön')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(IsletmeProjeleriSayfasi::getUrl()),
            Action::make('raporCsvIndir')
                ->label('CSV indir')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn (): StreamedResponse => $this->raporCsvIndir()),
        ];
    }

    protected function getForms(): array
    {
        return ['filtreForm'];
    }

    public function filtreForm(Form $form): Form
    {
        return $form
            ->statePath('filtreler')
            ->schema([
                Forms\Components\DatePicker::make('baslangic')->label('Başlangıç')->required()->native(false),
                Forms\Components\DatePicker::make('bitis')->label('Bitiş')->required()->afterOrEqual('baslangic')->native(false),
                Forms\Components\Select::make('proje_id')
                    ->label('Proje')
                    ->placeholder('Tüm projeler')
                    ->searchable()
                    ->options(fn (): array => $this->projeSecenekleri())
                    ->native(false),
                Forms\Components\Select::make('durum')
                    ->label('Proje durumu')
                    ->options([
                        'aktif' => 'Aktif projeler',
                        'tumu' => 'Tüm projeler',
                        IsletmeProjesi::DURUM_TAMAMLANDI => 'Tamamlananlar',
                    ])
                    ->native(false),
            ])
            ->columns([
                'default' => 1,
                'sm' => 2,
                'xl' => 4,
            ]);
    }

    public function filtreleriUygula(): void
    {
        $this->filtreler = array_replace($this->filtreler, $this->filtreForm->getState());
        $this->resetPage('hareketler');
    }

    public function hizliTarihFiltrele(string $donem): void
    {
        $bugun = now();

        [$baslangic, $bitis] = match ($donem) {
            'bugun' => [$bugun->copy(), $bugun->copy()],
            'bu_hafta' => [$bugun->copy()->startOfWeek(Carbon::MONDAY), $bugun->copy()],
            'son_30_gun' => [$bugun->copy()->subDays(29), $bugun->copy()],
            'bu_yil' => [$bugun->copy()->startOfYear(), $bugun->copy()],
            default => [$bugun->copy()->startOfMonth(), $bugun->copy()],
        };

        $this->filtreler['baslangic'] = $baslangic->toDateString();
        $this->filtreler['bitis'] = $bitis->toDateString();
        $this->filtreForm->fill($this->filtreler);
        $this->resetPage('hareketler');
    }

    public function updatedHareketlerPerPage(): void
    {
        $this->hareketlerPerPage = (int) $this->hareketlerPerPage;
        $this->hareketlerPerPage = in_array($this->hareketlerPerPage, [25, 50, 100], true) ? $this->hareketlerPerPage : 25;
        $this->resetPage('hareketler');
    }

    public function updatingHareketArama(): void
    {
        $this->resetPage('hareketler');
    }

    public function filtreleriSifirla(): void
    {
        $this->filtreler = [
            'baslangic' => now()->startOfMonth()->toDateString(),
            'bitis' => now()->toDateString(),
            'proje_id' => '',
            'durum' => 'aktif',
        ];
        $this->hareketArama = '';
        $this->filtreForm->fill($this->filtreler);
        $this->resetPage('hareketler');
    }

    /** @return array<int, array{kod:string,proje:string,durum:string,para_birimi:string,butce:string|null,masraf:string,gelir:string,odeme:string,net:string,kar:string,kar_marji:string|null,kalan:string|null}> */
    public function raporSatirlari(): array
    {
        $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
        if ($firmaId < 1) {
            return [];
        }

        $baslangic = (string) ($this->filtreler['baslangic'] ?? now()->startOfMonth()->toDateString());
        $bitis = (string) ($this->filtreler['bitis'] ?? now()->toDateString());
        $masraf = DB::table('masraflar')
            ->where('firma_id', $firmaId)->where('durum', 'aktif')
            ->whereBetween('tarih', [$baslangic.' 00:00:00', $bitis.' 23:59:59'])
            ->select('isletme_proje_id', 'para_birimi')
            ->selectRaw('SUM(tutar) as masraf')
            ->groupBy('isletme_proje_id', 'para_birimi');
        $finans = DB::table('finans_hareketleri')
            ->where('firma_id', $firmaId)->where('durum', 'aktif')
            ->whereBetween('tarih', [$baslangic.' 00:00:00', $bitis.' 23:59:59'])
            ->select('isletme_proje_id', 'para_birimi')
            ->selectRaw("SUM(CASE WHEN tur = 'tahsilat' THEN tutar ELSE 0 END) as gelir")
            ->selectRaw("SUM(CASE WHEN tur = 'odeme' THEN tutar ELSE 0 END) as odeme")
            ->groupBy('isletme_proje_id', 'para_birimi');

        $rows = IsletmeProjesi::query()
            ->where('isletme_projeleri.firma_id', $firmaId)
            ->kullaniciIcinGorunur(null, $firmaId)
            ->when(($this->filtreler['proje_id'] ?? '') !== '', fn ($q) => $q->whereKey((int) $this->filtreler['proje_id']))
            ->when(($this->filtreler['durum'] ?? 'aktif') === 'aktif', fn ($q) => $q->where('durum', IsletmeProjesi::DURUM_AKTIF))
            ->when(($this->filtreler['durum'] ?? '') === IsletmeProjesi::DURUM_TAMAMLANDI, fn ($q) => $q->where('durum', IsletmeProjesi::DURUM_TAMAMLANDI))
            ->leftJoinSub($masraf, 'donem_masraf', function (JoinClause $join): void {
                $join->on('donem_masraf.isletme_proje_id', '=', 'isletme_projeleri.id')->on('donem_masraf.para_birimi', '=', 'isletme_projeleri.para_birimi');
            })
            ->leftJoinSub($finans, 'donem_finans', function (JoinClause $join): void {
                $join->on('donem_finans.isletme_proje_id', '=', 'isletme_projeleri.id')->on('donem_finans.para_birimi', '=', 'isletme_projeleri.para_birimi');
            })
            ->select('isletme_projeleri.kod', 'isletme_projeleri.ad as proje', 'isletme_projeleri.durum', 'isletme_projeleri.para_birimi', 'isletme_projeleri.butce_tutari')
            ->selectRaw('COALESCE(donem_masraf.masraf, 0) as masraf, COALESCE(donem_finans.gelir, 0) as gelir, COALESCE(donem_finans.odeme, 0) as odeme')
            ->orderBy('isletme_projeleri.ad')->get();

        return $rows->map(function ($row): array {
            $masraf = bcadd((string) $row->masraf, '0', 2);
            $gelir = bcadd((string) $row->gelir, '0', 2);
            $odeme = bcadd((string) $row->odeme, '0', 2);
            $kar = bcsub($gelir, $masraf, 2);
            return [
                'kod' => (string) $row->kod, 'proje' => (string) $row->proje, 'durum' => (string) $row->durum,
                'para_birimi' => strtoupper((string) $row->para_birimi),
                'butce' => $row->butce_tutari === null ? null : bcadd((string) $row->butce_tutari, '0', 2),
                'masraf' => $masraf, 'gelir' => $gelir, 'odeme' => $odeme,
                'net' => bcsub($gelir, $odeme, 2),
                'kar' => $kar,
                'kar_marji' => bccomp($gelir, '0', 2) > 0 ? (string) (((float) $kar / (float) $gelir) * 100) : null,
                'kalan' => $row->butce_tutari === null ? null : bcsub((string) $row->butce_tutari, $masraf, 2),
            ];
        })->all();
    }

    /** @return array<int, array{para_birimi:string,butce:string,masraf:string,gelir:string,odeme:string,net:string,kar:string,kar_marji:string|null,kalan:string}> */
    public function raporOzetleri(?array $satirlar = null): array
    {
        $ozetler = [];

        foreach ($satirlar ?? $this->raporSatirlari() as $satir) {
            $pb = strtoupper($satir['para_birimi']);
            $ozetler[$pb] ??= [
                'para_birimi' => $pb,
                'butce' => '0.00',
                'masraf' => '0.00',
                'gelir' => '0.00',
                'odeme' => '0.00',
                'net' => '0.00',
                'kar' => '0.00',
                'kar_marji' => null,
                'kalan' => '0.00',
            ];

            foreach (['butce', 'masraf', 'gelir', 'odeme', 'net', 'kalan'] as $alan) {
                if ($satir[$alan] !== null) {
                    $ozetler[$pb][$alan] = bcadd($ozetler[$pb][$alan], (string) $satir[$alan], 2);
                }
            }

            $ozetler[$pb]['kar'] = bcsub($ozetler[$pb]['gelir'], $ozetler[$pb]['masraf'], 2);
            $ozetler[$pb]['kar_marji'] = bccomp($ozetler[$pb]['gelir'], '0', 2) > 0
                ? (string) (((float) $ozetler[$pb]['kar'] / (float) $ozetler[$pb]['gelir']) * 100)
                : null;
        }

        return array_values($ozetler);
    }

    public function projeBaglantiUyumsuzlukSayisi(): int
    {
        $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
        if ($firmaId < 1) {
            return 0;
        }

        return (int) DB::table('masraf_fatura_dagitilari as d')
            ->join('masraflar as m', 'm.id', '=', 'd.masraf_id')
            ->join('faturalar as f', 'f.id', '=', 'd.fatura_id')
            ->where('d.firma_id', $firmaId)
            ->where('m.firma_id', $firmaId)
            ->where('f.firma_id', $firmaId)
            ->whereRaw('(m.isletme_proje_id <> f.isletme_proje_id OR (m.isletme_proje_id IS NULL AND f.isletme_proje_id IS NOT NULL) OR (m.isletme_proje_id IS NOT NULL AND f.isletme_proje_id IS NULL))')
            ->count();
    }

    /** @return array<int, array{donem:string,para_birimi:string,masraf:string,gelir:string,odeme:string,net:string}> */
    public function aylikOzeti(): array
    {
        $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
        if ($firmaId < 1) {
            return [];
        }

        $baslangic = Carbon::parse((string) ($this->filtreler['baslangic'] ?? now()->startOfMonth()->toDateString()))->startOfMonth();
        $bitis = Carbon::parse((string) ($this->filtreler['bitis'] ?? now()->toDateString()))->startOfMonth();
        $gorunurProjeIds = IsletmeProjesi::query()->where('firma_id', $firmaId)->kullaniciIcinGorunur(null, $firmaId)->select('id');
        $aylar = [];
        for ($ay = $baslangic->copy(); $ay->lte($bitis); $ay->addMonth()) {
            $aylar[$ay->format('Y-m')] = $ay->format('m.Y');
        }

        $masraflar = DB::table('masraflar')->where('firma_id', $firmaId)->where('durum', 'aktif')
            ->whereNotNull('isletme_proje_id')
            ->whereIn('isletme_proje_id', $gorunurProjeIds)
            ->whereBetween('tarih', [$baslangic->toDateString(), $bitis->copy()->endOfMonth()->toDateString()])
            ->when(($this->filtreler['proje_id'] ?? '') !== '', fn ($q) => $q->where('isletme_proje_id', (int) $this->filtreler['proje_id']))
            ->get(['tarih', 'tutar', 'para_birimi']);
        $finanslar = DB::table('finans_hareketleri')->where('firma_id', $firmaId)->where('durum', 'aktif')
            ->whereNotNull('isletme_proje_id')
            ->whereIn('isletme_proje_id', $gorunurProjeIds)
            ->whereBetween('tarih', [$baslangic->startOfMonth()->toDateTimeString(), $bitis->copy()->endOfMonth()->toDateTimeString()])
            ->when(($this->filtreler['proje_id'] ?? '') !== '', fn ($q) => $q->where('isletme_proje_id', (int) $this->filtreler['proje_id']))
            ->get(['tarih', 'tutar', 'para_birimi', 'tur']);

        $toplamlar = [];
        foreach ($masraflar as $kayit) {
            $donem = Carbon::parse((string) $kayit->tarih)->format('Y-m');
            $pb = strtoupper((string) ($kayit->para_birimi ?: 'TRY'));
            $toplamlar[$donem.'|'.$pb] ??= ['donem' => $aylar[$donem] ?? $donem, 'para_birimi' => $pb, 'masraf' => '0.00', 'gelir' => '0.00', 'odeme' => '0.00', 'net' => '0.00'];
            $toplamlar[$donem.'|'.$pb]['masraf'] = bcadd($toplamlar[$donem.'|'.$pb]['masraf'], (string) $kayit->tutar, 2);
        }
        foreach ($finanslar as $kayit) {
            $donem = Carbon::parse((string) $kayit->tarih)->format('Y-m');
            $pb = strtoupper((string) ($kayit->para_birimi ?: 'TRY'));
            $toplamlar[$donem.'|'.$pb] ??= ['donem' => $aylar[$donem] ?? $donem, 'para_birimi' => $pb, 'masraf' => '0.00', 'gelir' => '0.00', 'odeme' => '0.00', 'net' => '0.00'];
            $alan = (string) $kayit->tur === 'tahsilat' ? 'gelir' : ((string) $kayit->tur === 'odeme' ? 'odeme' : null);
            if ($alan !== null) {
                $toplamlar[$donem.'|'.$pb][$alan] = bcadd($toplamlar[$donem.'|'.$pb][$alan], (string) $kayit->tutar, 2);
            }
        }
        foreach ($toplamlar as &$satir) {
            $satir['net'] = bcsub($satir['gelir'], $satir['odeme'], 2);
        }

        uasort($toplamlar, fn (array $a, array $b): int => [$a['donem'], $a['para_birimi']] <=> [$b['donem'], $b['para_birimi']]);

        return array_values($toplamlar);
    }

    /** @return array<int, array{cari:string,proje:string,para_birimi:string,borc:string,alacak:string,net:string}> */
    public function cariOzeti(): array
    {
        $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
        if ($firmaId < 1) {
            return [];
        }

        $baslangic = (string) ($this->filtreler['baslangic'] ?? now()->startOfMonth()->toDateString());
        $bitis = (string) ($this->filtreler['bitis'] ?? now()->toDateString());
        $gorunurProjeIds = IsletmeProjesi::query()->where('firma_id', $firmaId)->kullaniciIcinGorunur(null, $firmaId)->select('id');

        return DB::table('cari_hareketleri as ch')
            ->join('cariler as c', 'c.id', '=', 'ch.cari_id')
            ->join('isletme_projeleri as p', 'p.id', '=', 'ch.isletme_proje_id')
            ->where('ch.firma_id', $firmaId)
            ->where('c.firma_id', $firmaId)
            ->where('p.firma_id', $firmaId)
            ->whereIn('ch.isletme_proje_id', $gorunurProjeIds)
            ->where('ch.durum', 'aktif')
            ->whereBetween('ch.islem_tarihi', [$baslangic.' 00:00:00', $bitis.' 23:59:59'])
            ->when(($this->filtreler['proje_id'] ?? '') !== '', fn ($q) => $q->where('ch.isletme_proje_id', (int) $this->filtreler['proje_id']))
            ->when(($this->filtreler['durum'] ?? 'aktif') === 'aktif', fn ($q) => $q->where('p.durum', IsletmeProjesi::DURUM_AKTIF))
            ->select('c.ad as cari', 'p.ad as proje', 'ch.para_birimi')
            ->selectRaw('SUM(ch.borc) as borc, SUM(ch.alacak) as alacak')
            ->groupBy('c.ad', 'p.ad', 'ch.para_birimi')
            ->orderBy('c.ad')
            ->get()
            ->map(fn ($row): array => [
                'cari' => (string) $row->cari,
                'proje' => (string) $row->proje,
                'para_birimi' => strtoupper((string) ($row->para_birimi ?: 'TRY')),
                'borc' => bcadd((string) ($row->borc ?? 0), '0', 2),
                'alacak' => bcadd((string) ($row->alacak ?? 0), '0', 2),
                'net' => bcsub((string) ($row->borc ?? 0), (string) ($row->alacak ?? 0), 2),
            ])->all();
    }

    /** @return \Illuminate\Pagination\LengthAwarePaginator<int, object> */
    public function projeHareketleri(): \Illuminate\Pagination\LengthAwarePaginator
    {
        return $this->projeHareketleriSorgusu()->paginate($this->hareketlerPerPage, ['*'], 'hareketler');
    }

    public function projeHareketDetayUrl(object $hareket): string
    {
        $tur = strtolower(trim(explode('/', (string) $hareket->hareket_turu, 2)[0]));

        return ProjeHareketDetaySayfasi::getUrl([
            'tur' => $tur,
            'record' => (int) $hareket->kaynak_id,
        ]);
    }

    private function projeHareketleriSorgusu(): QueryBuilder
    {
        $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
        $baslangic = (string) ($this->filtreler['baslangic'] ?? now()->startOfMonth()->toDateString());
        $bitis = (string) ($this->filtreler['bitis'] ?? now()->toDateString());
        $masrafEtiketi = $this->sqlConcat("'Masraf #'", 'm.id');
        $faturaEtiketi = $this->sqlConcat("'Fatura / '", "COALESCE(f.tur, '')");
        $faturaBelgesi = $this->sqlConcat("'Fatura #'", 'f.id');
        $finansEtiketi = $this->sqlConcat("'Finans / '", "COALESCE(fh.tur, '')");
        $finansBelgesi = $this->sqlConcat("'Finans #'", 'fh.id');
        $cariEtiketi = $this->sqlConcat("'Cari / '", "COALESCE(ch.belge_turu, '')");
        $cariBelgesi = $this->sqlConcat("'Cari #'", 'ch.id');
        $stokEtiketi = $this->sqlConcat("'Stok / '", "COALESCE(sh.islem_turu, '')");
        $stokBelgesi = $this->sqlConcat("'Stok #'", 'sh.id');

        $masraflar = DB::table('masraflar as m')
            ->where('m.firma_id', $firmaId)
            ->where('m.durum', 'aktif')
            ->whereNotNull('m.isletme_proje_id')
            ->whereNotExists(function (QueryBuilder $q): void {
                $q->selectRaw('1')
                    ->from('masraf_fatura_dagitilari as mfd')
                    ->whereColumn('mfd.masraf_id', 'm.id');
            })
            ->whereBetween('m.tarih', [$baslangic.' 00:00:00', $bitis.' 23:59:59'])
            ->selectRaw("'Masraf' as hareket_turu, m.id as kaynak_id, m.tarih, {$masrafEtiketi} as belge, m.aciklama, 'Çıkış' as yon, NULL as miktar, m.tutar, m.para_birimi, m.isletme_proje_id as proje_id");

        $faturalar = DB::table('faturalar as f')
            ->where('f.firma_id', $firmaId)
            ->where('f.durum', '!=', 'iptal')
            ->whereNotNull('f.isletme_proje_id')
            ->whereBetween('f.tarih', [$baslangic.' 00:00:00', $bitis.' 23:59:59'])
            ->selectRaw("{$faturaEtiketi} as hareket_turu, f.id as kaynak_id, f.tarih, COALESCE(NULLIF(f.belge_no, ''), NULLIF(f.fatura_no, ''), {$faturaBelgesi}) as belge, f.aciklama, CASE WHEN f.tur IN ('giden', 'giden_fatura') THEN 'Giriş' WHEN f.tur IN ('gelen', 'gelen_fatura', 'gider', 'gider_faturasi') THEN 'Çıkış' ELSE 'Nötr' END as yon, NULL as miktar, f.genel_toplam as tutar, f.para_birimi, f.isletme_proje_id as proje_id");

        $finanslar = DB::table('finans_hareketleri as fh')
            ->where('fh.firma_id', $firmaId)
            ->where('fh.durum', 'aktif')
            ->whereNotNull('fh.isletme_proje_id')
            ->whereBetween('fh.tarih', [$baslangic.' 00:00:00', $bitis.' 23:59:59'])
            ->selectRaw("{$finansEtiketi} as hareket_turu, fh.id as kaynak_id, fh.tarih, {$finansBelgesi} as belge, COALESCE(fh.aciklama, fh.ek_aciklama) as aciklama, CASE WHEN fh.tur = 'tahsilat' THEN 'Giriş' WHEN fh.tur = 'odeme' THEN 'Ödeme' ELSE 'Nötr' END as yon, NULL as miktar, fh.tutar, fh.para_birimi, fh.isletme_proje_id as proje_id");

        $cariler = DB::table('cari_hareketleri as ch')
            ->where('ch.firma_id', $firmaId)
            ->where('ch.durum', 'aktif')
            ->whereNotNull('ch.isletme_proje_id')
            ->where(function (QueryBuilder $q): void {
                $q->where('ch.belge_turu', '!=', 'fatura')
                    ->orWhereNotExists(function (QueryBuilder $faturaQuery): void {
                        $faturaQuery->selectRaw('1')
                            ->from('faturalar as cf')
                            ->whereColumn('cf.id', 'ch.belge_id')
                            ->where('cf.firma_id', (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0));
                    });
            })
            ->whereBetween('ch.islem_tarihi', [$baslangic.' 00:00:00', $bitis.' 23:59:59'])
            ->selectRaw("{$cariEtiketi} as hareket_turu, ch.id as kaynak_id, ch.islem_tarihi as tarih, {$cariBelgesi} as belge, ch.aciklama, CASE WHEN ch.borc > 0 THEN 'Borç' WHEN ch.alacak > 0 THEN 'Alacak' ELSE 'Nötr' END as yon, NULL as miktar, CASE WHEN ch.borc > 0 THEN ch.borc ELSE ch.alacak END as tutar, ch.para_birimi, ch.isletme_proje_id as proje_id");

        // Stok hareketlerinde doğrudan proje kolonu bulunmadığı için proje bağlantılı faturaya bağlanır.
        $stoklar = DB::table('stok_hareketleri as sh')
            ->join('faturalar as sf', function (JoinClause $join): void {
                $join->on('sf.id', '=', 'sh.belge_id')
                    ->where('sh.belge_turu', 'fatura')
                    ->where('sf.durum', '!=', 'iptal');
            })
            ->where('sh.firma_id', $firmaId)
            ->where('sf.firma_id', $firmaId)
            ->where('sh.durum', 'aktif')
            ->whereNotNull('sf.isletme_proje_id')
            ->whereBetween(DB::raw('COALESCE(sh.islem_tarihi, sh.tarih)'), [$baslangic.' 00:00:00', $bitis.' 23:59:59'])
            ->selectRaw("{$stokEtiketi} as hareket_turu, sh.id as kaynak_id, COALESCE(sh.islem_tarihi, sh.tarih) as tarih, {$stokBelgesi} as belge, sh.aciklama, CASE WHEN sh.islem_turu IN ('acilis', 'alis', 'satis_iadesi', 'transfer_giris') THEN 'Giriş' ELSE 'Çıkış' END as yon, sh.miktar, COALESCE(sh.toplam_maliyet, sh.toplam) as tutar, COALESCE(sf.para_birimi, 'TRY') as para_birimi, sf.isletme_proje_id as proje_id");

        // Faturaya bağlı cari/stok kayıtları faturanın muhasebe alt kayıtlarıdır;
        // aynı işlemi ikinci kez göstermemek için ana fatura satırında gruplanırlar.
        $union = $masraflar
            ->unionAll($faturalar)
            ->unionAll($finanslar)
            ->unionAll($cariler);

        $gorunurProjeler = IsletmeProjesi::query()
            ->where('isletme_projeleri.firma_id', $firmaId)
            ->kullaniciIcinGorunur(null, $firmaId)
            ->select('isletme_projeleri.id');

        return DB::query()
            ->fromSub($union, 'h')
            ->join('isletme_projeleri as p', 'p.id', '=', 'h.proje_id')
            ->whereIn('h.proje_id', $gorunurProjeler)
            ->when(trim($this->hareketArama) !== '', function (QueryBuilder $query): void {
                $arama = '%'.trim($this->hareketArama).'%';
                $query->where(function (QueryBuilder $aramaSorgusu) use ($arama): void {
                    $aramaSorgusu
                        ->where('h.hareket_turu', 'like', $arama)
                        ->orWhere('h.belge', 'like', $arama)
                        ->orWhere('h.aciklama', 'like', $arama)
                        ->orWhere('p.kod', 'like', $arama)
                        ->orWhere('p.ad', 'like', $arama);
                });
            })
            ->when(($this->filtreler['proje_id'] ?? '') !== '', fn (QueryBuilder $q): QueryBuilder => $q->where('h.proje_id', (int) $this->filtreler['proje_id']))
            ->when(($this->filtreler['durum'] ?? 'aktif') === 'aktif', fn (QueryBuilder $q): QueryBuilder => $q->where('p.durum', IsletmeProjesi::DURUM_AKTIF))
            ->when(($this->filtreler['durum'] ?? '') === IsletmeProjesi::DURUM_TAMAMLANDI, fn (QueryBuilder $q): QueryBuilder => $q->where('p.durum', IsletmeProjesi::DURUM_TAMAMLANDI))
            ->select('h.*', 'p.kod as proje_kodu', 'p.ad as proje')
            ->orderByDesc('h.tarih')
            ->orderByDesc('h.kaynak_id');
    }

    public function projeHareketleriCsvIndir(bool $excelUyumlu = false): StreamedResponse
    {
        $hareketler = $this->projeHareketleriSorgusu()->get();
        $delimiter = $excelUyumlu ? ';' : ',';
        $dosyaAdi = 'proje-hareketleri-'.now()->format('Ymd_His').($excelUyumlu ? '-excel' : '').'.csv';

        return response()->streamDownload(function () use ($hareketler, $delimiter): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Tarih', 'Hareket türü', 'Proje kodu', 'Proje', 'Belge / kayıt', 'Açıklama', 'Yön', 'Miktar', 'Tutar', 'Para birimi'], $delimiter);
            foreach ($hareketler as $hareket) {
                fputcsv($out, [
                    Carbon::parse((string) $hareket->tarih)->format('d.m.Y H:i'),
                    $hareket->hareket_turu,
                    $hareket->proje_kodu,
                    $hareket->proje,
                    $hareket->belge,
                    $hareket->aciklama,
                    $hareket->yon,
                    $hareket->miktar,
                    $hareket->tutar,
                    strtoupper((string) ($hareket->para_birimi ?: 'TRY')),
                ], $delimiter);
            }

            fclose($out);
        }, $dosyaAdi, [
            'Content-Type' => $excelUyumlu ? 'application/vnd.ms-excel; charset=UTF-8' : 'text/csv; charset=UTF-8',
        ]);
    }

    private function sqlConcat(string ...$parts): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return implode(' || ', $parts);
        }

        return 'CONCAT('.implode(', ', $parts).')';
    }

    /** @return array<int, string> */
    private function projeSecenekleri(): array
    {
        $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
        return IsletmeProjesi::query()->where('firma_id', $firmaId)->kullaniciIcinGorunur(null, $firmaId)->orderBy('ad')->limit(100)->get(['id', 'kod', 'ad'])
            ->mapWithKeys(fn (IsletmeProjesi $proje): array => [$proje->id => $proje->kod.' — '.$proje->ad])->all();
    }

    private function raporCsvIndir(): StreamedResponse
    {
        $satirlar = $this->raporSatirlari();
        $dosyaAdi = 'proje-finans-raporu-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($satirlar): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Kod', 'Proje', 'Durum', 'Bütçe', 'Masraf', 'Gelir / Tahsilat', 'Ödeme', 'Net Finans', 'Kalan Bütçe', 'Para Birimi'], ';');
            foreach ($satirlar as $satir) {
                fputcsv($out, [
                    $satir['kod'], $satir['proje'], $satir['durum'], $satir['butce'] ?? '', $satir['masraf'],
                    $satir['gelir'], $satir['odeme'], $satir['net'], $satir['kalan'] ?? '', $satir['para_birimi'],
                ], ';');
            }

            fclose($out);
        }, $dosyaAdi, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
