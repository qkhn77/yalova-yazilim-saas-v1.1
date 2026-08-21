<?php

namespace App\Filament\Clusters\MasrafTakip\Pages;

use App\Filament\Clusters\MasrafTakip as MasrafTakipCluster;
use App\Filament\Clusters\MasrafTakip\Kaynaklar\MasrafTakipSayfaErisimleri;
use App\Models\Muhasebe\Masraf;
use App\Models\Muhasebe\MasrafKategorisi;
use App\Models\Masraf\MasrafButcesi;
use App\Models\Proje\IsletmeProjesi;
use App\Filament\Clusters\MasrafTakip\Pages\MasrafDetaySayfasi;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Services\TenantContextService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Livewire\WithPagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MasrafRaporlariSayfasi extends Page implements HasForms
{
    use InteractsWithForms;
    use MasrafTakipSayfaErisimleri;
    use WithPagination;

    protected static ?string $cluster = MasrafTakipCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Masraf Raporları';

    protected static ?string $slug = 'raporlar';

    protected static string $view = 'filament.clusters.masraf-takip.pages.masraf-raporlari';

    /** @var array{baslangic:string, bitis:string, kategori:string, isletme_proje_id:int|string, durum:string, personel_maliyet_turu:string} */
    public array $filtreler = [];

    public bool $raporHazir = false;

    public string $hareketArama = '';

    /** @var array<string, array<int, int>> */
    public array $kategoriSecimState = [];

    public function mount(): void
    {
        if ($firmaId = $this->aktifFirmaId()) {
            MasrafKategorisi::varsayilanlariHazirla($firmaId);
        }

        $this->filtreleriVarsayilanla();
        $this->filtreFormunuDoldur();
    }

    public function getHeading(): string
    {
        return 'Masraf raporları';
    }

    public function getSubheading(): ?string
    {
        return 'Firma bazında masrafları dönem, kategori ve para birimine göre inceleyin.';
    }

    protected function getForms(): array
    {
        return ['filtreForm'];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('masraflaraDon')
                ->label('Masraflara dön')
                ->icon('heroicon-m-arrow-left')
                ->color('gray')
                ->url(MasrafTakibiSayfasi::getUrl()),
        ];
    }

    public function filtreForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('baslangic')
                    ->label('Başlangıç')
                    ->required()
                    ->native(false),
                Forms\Components\DatePicker::make('bitis')
                    ->label('Bitiş')
                    ->required()
                    ->afterOrEqual('baslangic')
                    ->native(false),
                Forms\Components\Select::make('isletme_proje_id')
                    ->label('İşletme projesi')
                    ->searchable()
                    ->options(fn (): array => ['' => 'Tüm projeler', 'projesiz' => 'Projesiz masraflar'] + $this->projeSecenekleri())
                    ->getSearchResultsUsing(fn (string $search): array => ['projesiz' => 'Projesiz masraflar'] + $this->projeSecenekleri($search))
                    ->getOptionLabelUsing(fn ($value): ?string => $this->projeEtiketi($value))
                    ->native(false),
                ...$this->kategoriSecimBilesenleri(),
                Forms\Components\Select::make('durum')
                    ->label('Kayıt durumu')
                    ->options([
                        Masraf::DURUM_AKTIF => 'Aktif kayıtlar',
                        Masraf::DURUM_IPTAL => 'İptal edilenler',
                        'tumu' => 'Tüm kayıtlar',
                    ])
                    ->native(false),
            ])
            ->columns([
                'default' => 1,
                'sm' => 2,
                'xl' => 5,
            ])
            ->statePath('filtreler');
    }

    public function filtreleriUygula(): void
    {
        $this->filtreler = array_replace($this->filtreler, $this->filtreForm->getState());
        $this->resetPage('hareketlerPage');
        $this->raporHazir = true;
    }

    public function hizliTarihFiltrele(string $donem): void
    {
        $bugun = now();

        [$baslangic, $bitis] = match ($donem) {
            'bugun' => [$bugun->copy(), $bugun->copy()],
            'bu_hafta' => [$bugun->copy()->startOfWeek(Carbon::MONDAY), $bugun->copy()],
            'bu_yil' => [$bugun->copy()->startOfYear(), $bugun->copy()],
            'son_30_gun' => [$bugun->copy()->subDays(29), $bugun->copy()],
            default => [$bugun->copy()->startOfMonth(), $bugun->copy()],
        };

        $this->filtreler['baslangic'] = $baslangic->toDateString();
        $this->filtreler['bitis'] = $bitis->toDateString();
        $this->filtreFormunuDoldur();
        $this->resetPage('hareketlerPage');
        $this->raporHazir = true;
    }

    public function filtreleriSifirla(): void
    {
        $this->filtreleriVarsayilanla();
        $this->kategoriSecimState = [];
        $this->filtreFormunuDoldur();
        $this->hareketArama = '';
        $this->resetPage('hareketlerPage');
        $this->raporHazir = true;
    }

    public function raporuYukle(): void
    {
        $this->raporHazir = true;
    }

    /** @return array<int, array{para_birimi:string, toplam:string, adet:int}> */
    public function ozet(): array
    {
        return $this->filtreliSorgu()
            ->selectRaw('para_birimi, SUM(tutar) as toplam, COUNT(*) as adet')
            ->groupBy('para_birimi')
            ->orderBy('para_birimi')
            ->get()
            ->map(fn ($row): array => [
                'para_birimi' => strtoupper((string) ($row->para_birimi ?: 'TRY')),
                'toplam' => bcadd((string) ($row->toplam ?? '0'), '0', 2),
                'adet' => (int) $row->adet,
            ])->all();
    }

    /** @return array<int, array{kategori:string, ana_kategori:string, para_birimi:string, toplam:string, adet:int}> */
    public function kategoriOzeti(): array
    {
        return $this->filtreliSorgu()
            ->join('masraf_kategorileri', 'masraf_kategorileri.id', '=', 'masraflar.masraf_kategorisi_id')
            ->leftJoin('masraf_kategorileri as ust_kategoriler', 'ust_kategoriler.id', '=', 'masraf_kategorileri.ust_kategori_id')
            ->selectRaw('masraf_kategorileri.ad as kategori, ust_kategoriler.ad as ana_kategori, masraflar.para_birimi, SUM(masraflar.tutar) as toplam, COUNT(*) as adet')
            ->groupBy('masraf_kategorileri.ad', 'ust_kategoriler.ad', 'masraflar.para_birimi')
            ->orderByDesc('toplam')
            ->get()
            ->map(fn ($row): array => [
                'kategori' => (string) $row->kategori,
                'ana_kategori' => (string) ($row->ana_kategori ?? ''),
                'para_birimi' => strtoupper((string) ($row->para_birimi ?: 'TRY')),
                'toplam' => bcadd((string) ($row->toplam ?? '0'), '0', 2),
                'adet' => (int) $row->adet,
            ])->all();
    }

    /**
     * @return array<int, array{proje_id:int, kod:string, proje:string, durum:string, para_birimi:string, butce:string|null, gerceklesen:string, kalan:string|null, adet:int}>
     */
    public function projeButceGerceklesenOzeti(): array
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId === null) {
            return [];
        }

        $gerceklesen = DB::table('masraflar as m')
            ->where('m.firma_id', $firmaId)
            ->where('m.durum', Masraf::DURUM_AKTIF)
            ->whereBetween('m.tarih', [$this->filtreler['baslangic'].' 00:00:00', $this->filtreler['bitis'].' 23:59:59'])
            ->whereNotNull('m.isletme_proje_id')
            ->select('m.isletme_proje_id', 'm.para_birimi')
            ->selectRaw('SUM(m.tutar) as gerceklesen, COUNT(m.id) as adet')
            ->groupBy('m.isletme_proje_id', 'm.para_birimi');

        if (($kategoriId = (int) ($this->filtreler['kategori'] ?? 0)) > 0) {
            $gerceklesen->whereIn('m.masraf_kategorisi_id', $this->kategoriKapsamIdleri($kategoriId));
        }

        $projeFiltresi = (string) ($this->filtreler['isletme_proje_id'] ?? '');
        if ($projeFiltresi === 'projesiz') {
            return [];
        }

        $projeId = (int) $projeFiltresi;

        return DB::table('isletme_projeleri as p')
            ->leftJoinSub($gerceklesen, 'donem_masraf', function ($join): void {
                $join->on('donem_masraf.isletme_proje_id', '=', 'p.id')
                    ->on('donem_masraf.para_birimi', '=', 'p.para_birimi');
            })
            ->where('p.firma_id', $firmaId)
            ->where('p.durum', '<>', IsletmeProjesi::DURUM_IPTAL)
            ->when($projeId > 0, fn ($query) => $query->where('p.id', $projeId))
            ->select([
                'p.id as proje_id',
                'p.kod',
                'p.ad as proje',
                'p.durum',
                'p.para_birimi',
                'p.butce_tutari as butce',
            ])
            ->selectRaw('COALESCE(donem_masraf.gerceklesen, 0) as gerceklesen, COALESCE(donem_masraf.adet, 0) as adet')
            ->orderBy('p.ad')
            ->get()
            ->map(fn ($row): array => [
                'proje_id' => (int) $row->proje_id,
                'kod' => (string) $row->kod,
                'proje' => (string) $row->proje,
                'durum' => (string) $row->durum,
                'para_birimi' => strtoupper((string) ($row->para_birimi ?: 'TRY')),
                'butce' => $row->butce === null ? null : bcadd((string) $row->butce, '0', 2),
                'gerceklesen' => bcadd((string) ($row->gerceklesen ?? '0'), '0', 2),
                'kalan' => $row->butce === null ? null : bcsub((string) $row->butce, (string) ($row->gerceklesen ?? '0'), 2),
                'adet' => (int) ($row->adet ?? 0),
            ])
            ->all();
    }

    /**
     * @return array<int, array{butce_id:int, kategori:string, ana_kategori:string, baslangic:string, bitis:string, para_birimi:string, butce:string, gerceklesen:string, kalan:string, adet:int, durum:string}>
     */
    public function kategoriButceGerceklesenOzeti(): array
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId === null) {
            return [];
        }

        $baslangic = (string) $this->filtreler['baslangic'];
        $bitis = (string) $this->filtreler['bitis'];
        $kategoriId = (int) ($this->filtreler['kategori'] ?? 0);

        $gerceklesen = DB::table('masraflar as m')
            ->where('m.firma_id', $firmaId)
            ->where('m.durum', Masraf::DURUM_AKTIF)
            ->whereBetween('m.tarih', [$baslangic.' 00:00:00', $bitis.' 23:59:59'])
            ->select('m.masraf_kategorisi_id', 'm.para_birimi')
            ->selectRaw('SUM(m.tutar) as gerceklesen, COUNT(m.id) as adet')
            ->groupBy('m.masraf_kategorisi_id', 'm.para_birimi');

        if ($kategoriId > 0) {
            $gerceklesen->whereIn('m.masraf_kategorisi_id', $this->kategoriKapsamIdleri($kategoriId));
        }

        return DB::table('masraf_butceleri as b')
            ->join('masraf_kategorileri as k', 'k.id', '=', 'b.masraf_kategorisi_id')
            ->leftJoin('masraf_kategorileri as uk', 'uk.id', '=', 'k.ust_kategori_id')
            ->leftJoinSub($gerceklesen, 'donem_masraf', function ($join): void {
                $join->on('donem_masraf.masraf_kategorisi_id', '=', 'b.masraf_kategorisi_id')
                    ->on('donem_masraf.para_birimi', '=', 'b.para_birimi');
            })
            ->where('b.firma_id', $firmaId)
            ->where('b.donem_baslangic', '<=', $bitis)
            ->where('b.donem_bitis', '>=', $baslangic)
            ->when($kategoriId > 0, fn ($query) => $query->whereIn('b.masraf_kategorisi_id', $this->kategoriKapsamIdleri($kategoriId)))
            ->select([
                'b.id as butce_id',
                'k.ad as kategori',
                'uk.ad as ana_kategori',
                'b.donem_baslangic as baslangic',
                'b.donem_bitis as bitis',
                'b.para_birimi',
                'b.butce_tutari as butce',
                'b.durum',
            ])
            ->selectRaw('COALESCE(donem_masraf.gerceklesen, 0) as gerceklesen, COALESCE(donem_masraf.adet, 0) as adet')
            ->orderBy('b.donem_baslangic')
            ->orderBy('k.ad')
            ->get()
            ->map(fn ($row): array => [
                'butce_id' => (int) $row->butce_id,
                'kategori' => (string) $row->kategori,
                'ana_kategori' => (string) ($row->ana_kategori ?? ''),
                'baslangic' => (string) $row->baslangic,
                'bitis' => (string) $row->bitis,
                'para_birimi' => strtoupper((string) ($row->para_birimi ?: 'TRY')),
                'butce' => bcadd((string) $row->butce, '0', 2),
                'gerceklesen' => bcadd((string) ($row->gerceklesen ?? '0'), '0', 2),
                'kalan' => bcsub((string) $row->butce, (string) ($row->gerceklesen ?? '0'), 2),
                'adet' => (int) ($row->adet ?? 0),
                'durum' => (string) $row->durum,
            ])
            ->all();
    }

    /** @return array<int, array{kalem:string, para_birimi:string, toplam:string, adet:int}> */
    public function personelGiderOzeti(): array
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId === null) {
            return [];
        }

        if (($kategoriId = (int) ($this->filtreler['kategori'] ?? 0)) > 0) {
            $kategori = MasrafKategorisi::query()
                ->where('firma_id', $firmaId)
                ->with('ustKategori:id,kod')
                ->whereKey($kategoriId)
                ->first(['id', 'kod', 'ust_kategori_id']);
            if (! $kategori || ($kategori->kod !== 'personel_giderleri' && $kategori->ustKategori?->kod !== 'personel_giderleri')) {
                return [];
            }
        }

        $baslangic = (string) $this->filtreler['baslangic'];
        $bitis = (string) $this->filtreler['bitis'];
        // Alan rapor filtresinden kaldırıldı; eski kayıt/entegrasyon çağrıları için
        // hesaplama seçenekleri geriye dönük olarak desteklenmeye devam eder.
        $personelMaliyetTuru = (string) ($this->filtreler['personel_maliyet_turu'] ?? 'brut');
        if (! in_array($personelMaliyetTuru, ['brut', 'net', 'isveren_toplam'], true)) {
            $personelMaliyetTuru = 'brut';
        }

        $maasTutariAlani = match ($personelMaliyetTuru) {
            'net' => 'COALESCE(mh.net_tutar, 0)',
            'isveren_toplam' => 'COALESCE(mh.brut_tutar, 0)'
                .' + COALESCE(mh.fazla_mesai_tutari, 0)'
                .' + COALESCE(mh.prim_tutari, 0)'
                .' + COALESCE(mh.ek_odeme_tutari, 0)'
                .' + COALESCE(mh.sgk_isveren_tutari, 0)'
                .' + COALESCE(mh.issizlik_isveren_tutari, 0)'
                .' + COALESCE(mh.diger_maliyet_tutari, 0)',
            default => 'COALESCE(mh.brut_tutar, 0)',
        };
        $maasKalemEtiketi = match ($personelMaliyetTuru) {
            'net' => 'Maaş (net)',
            'isveren_toplam' => 'İşveren toplam maliyeti',
            default => 'Maaş (brüt)',
        };

        $maaslar = DB::table('personel_maas_hareketleri as mh')
            ->join('personel_maas_donemleri as md', 'md.id', '=', 'mh.maas_donemi_id')
            ->where('mh.firma_id', $firmaId)
            ->whereNull('mh.deleted_at')
            ->whereNull('md.deleted_at')
            ->where('md.baslangic_tarihi', '<=', $bitis)
            ->where('md.bitis_tarihi', '>=', $baslangic)
            ->selectRaw(
                "? as kalem, md.para_birimi, SUM({$maasTutariAlani}) as toplam, COUNT(mh.id) as adet, "
                .'SUM(COALESCE(mh.sgk_isveren_tutari, 0)) as sgk_isveren_toplam, '
                .'SUM(COALESCE(mh.issizlik_isveren_tutari, 0)) as issizlik_isveren_toplam, '
                .'SUM(COALESCE(mh.gelir_vergisi_tutari, 0)) as gelir_vergisi_toplam, '
                .'SUM(COALESCE(mh.damga_vergisi_tutari, 0)) as damga_vergisi_toplam, '
                .'SUM(COALESCE(mh.diger_maliyet_tutari, 0)) as diger_maliyet_toplam, '
                .'SUM(CASE WHEN COALESCE(mh.sgk_isveren_tutari, 0) > 0 THEN 1 ELSE 0 END) as sgk_isveren_adet, '
                .'SUM(CASE WHEN COALESCE(mh.issizlik_isveren_tutari, 0) > 0 THEN 1 ELSE 0 END) as issizlik_isveren_adet, '
                .'SUM(CASE WHEN COALESCE(mh.gelir_vergisi_tutari, 0) > 0 THEN 1 ELSE 0 END) as gelir_vergisi_adet, '
                .'SUM(CASE WHEN COALESCE(mh.damga_vergisi_tutari, 0) > 0 THEN 1 ELSE 0 END) as damga_vergisi_adet, '
                .'SUM(CASE WHEN COALESCE(mh.diger_maliyet_tutari, 0) > 0 THEN 1 ELSE 0 END) as diger_maliyet_adet',
                [$maasKalemEtiketi],
            )
            ->groupBy('md.para_birimi')
            ->get();

        $avanslar = DB::table('personel_avanslari as pa')
            ->where('pa.firma_id', $firmaId)
            ->whereNull('pa.deleted_at')
            ->whereBetween('pa.tarih', [$baslangic, $bitis])
            ->whereIn('pa.onay_durumu', ['onaylandi', 'bekliyor'])
            ->selectRaw("'Avans' as kalem, pa.para_birimi, SUM(pa.tutar) as toplam, COUNT(pa.id) as adet")
            ->groupBy('pa.para_birimi')
            ->get();

        $personelSatirlari = $maaslar->flatMap(function ($row): array {
            $paraBirimi = strtoupper((string) ($row->para_birimi ?: 'TRY'));
            $satirlar = [[
                'kalem' => (string) $row->kalem,
                'para_birimi' => $paraBirimi,
                'toplam' => bcadd((string) ($row->toplam ?? '0'), '0', 2),
                'adet' => (int) $row->adet,
            ]];

            foreach ([
                ['SGK işveren payı', 'sgk_isveren_toplam', 'sgk_isveren_adet'],
                ['İşsizlik işveren payı', 'issizlik_isveren_toplam', 'issizlik_isveren_adet'],
                ['Gelir vergisi', 'gelir_vergisi_toplam', 'gelir_vergisi_adet'],
                ['Damga vergisi', 'damga_vergisi_toplam', 'damga_vergisi_adet'],
                ['Diğer işveren maliyeti', 'diger_maliyet_toplam', 'diger_maliyet_adet'],
            ] as [$etiket, $toplamAlani, $adetAlani]) {
                if ((float) ($row->{$toplamAlani} ?? 0) <= 0) {
                    continue;
                }

                $satirlar[] = [
                    'kalem' => $etiket,
                    'para_birimi' => $paraBirimi,
                    'toplam' => bcadd((string) ($row->{$toplamAlani} ?? '0'), '0', 2),
                    'adet' => (int) ($row->{$adetAlani} ?? 0),
                ];
            }

            return $satirlar;
        });

        $avansSatirlari = $avanslar->map(fn ($row): array => [
            'kalem' => (string) $row->kalem,
            'para_birimi' => strtoupper((string) ($row->para_birimi ?: 'TRY')),
            'toplam' => bcadd((string) ($row->toplam ?? '0'), '0', 2),
            'adet' => (int) $row->adet,
        ]);

        return collect($personelSatirlari)
            ->concat($avansSatirlari)
            ->values()
            ->all();
    }

    /** @return array<int, array{kalem:string, para_birimi:string, toplam:string, adet:int}> */
    public function teknikServisGiderOzeti(): array
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId === null) {
            return [];
        }

        if (($kategoriId = (int) ($this->filtreler['kategori'] ?? 0)) > 0) {
            $kategori = MasrafKategorisi::query()
                ->where('firma_id', $firmaId)
                ->with('ustKategori:id,kod')
                ->whereKey($kategoriId)
                ->first(['id', 'kod', 'ust_kategori_id']);
            if (! $kategori || ($kategori->kod !== 'teknik_servis_operasyon' && $kategori->ustKategori?->kod !== 'teknik_servis_operasyon')) {
                return [];
            }
        }

        $baslangic = (string) $this->filtreler['baslangic'];
        $bitis = (string) $this->filtreler['bitis'];

        return DB::table('faturalar as f')
            ->where('f.firma_id', $firmaId)
            ->where('f.kaynak_tipi', 'teknik_servis')
            ->whereIn('f.tur', ['gelen', 'gelen_fatura', 'gider', 'gider_faturasi'])
            ->where('f.durum', '<>', FaturaDurumu::Iptal->value)
            ->whereNull('f.deleted_at')
            ->whereBetween('f.tarih', [$baslangic.' 00:00:00', $bitis.' 23:59:59'])
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('masraflar as m')
                    ->whereColumn('m.firma_id', 'f.firma_id')
                    ->where('m.kaynak_turu', 'teknik_servis')
                    ->whereColumn('m.kaynak_id', 'f.islem_no')
                    ->where('m.durum', Masraf::DURUM_AKTIF);
            })
            ->selectRaw("'Teknik servis gider faturası' as kalem, f.para_birimi, SUM(COALESCE(f.odenecek_tutar, f.genel_toplam)) as toplam, COUNT(f.id) as adet")
            ->groupBy('f.para_birimi')
            ->get()
            ->map(fn ($row): array => [
                'kalem' => (string) $row->kalem,
                'para_birimi' => strtoupper((string) ($row->para_birimi ?: 'TRY')),
                'toplam' => bcadd((string) ($row->toplam ?? '0'), '0', 2),
                'adet' => (int) $row->adet,
            ])
            ->values()
            ->all();
    }

    public function masrafCsvIndir(bool $excelUyumlu = false): StreamedResponse
    {
        $this->filtreler = $this->filtreForm->getState();
        $query = $this->masrafSorgusu()->orderBy('tarih')->orderBy('id');
        $filtreler = $this->filtreler;
        $projeButceOzeti = $this->projeButceGerceklesenOzeti();
        $kategoriButceOzeti = $this->kategoriButceGerceklesenOzeti();
        $personelGiderleri = $this->personelGiderOzeti();
        $teknikServisGiderleri = $this->teknikServisGiderOzeti();
        $delimiter = $excelUyumlu ? ';' : ',';
        $dosyaAdi = 'masraf-raporu-'.now()->format('Ymd_His').($excelUyumlu ? '-excel' : '').'.csv';

        return response()->streamDownload(function () use ($query, $filtreler, $projeButceOzeti, $kategoriButceOzeti, $personelGiderleri, $teknikServisGiderleri, $delimiter, $excelUyumlu): void {
            $out = fopen('php://output', 'wb');
            if (! is_resource($out)) {
                return;
            }

            if ($excelUyumlu) {
                fwrite($out, "\xEF\xBB\xBF");
            }

            fputcsv($out, ['Rapor', 'Masraf Raporları'], $delimiter);
            fputcsv($out, ['Olusturulma', now()->format('d.m.Y H:i:s')], $delimiter);
            fputcsv($out, ['Baslangic', (string) ($filtreler['baslangic'] ?? '-')], $delimiter);
            fputcsv($out, ['Bitis', (string) ($filtreler['bitis'] ?? '-')], $delimiter);
            fputcsv($out, ['Masraf Turu', (string) ($filtreler['kategori'] ?? '') ?: 'Tumu'], $delimiter);
            fputcsv($out, [], $delimiter);
            fputcsv($out, ['Tarih', 'Ana Kategori', 'Masraf Turu', 'Proje', 'Aciklama', 'Tutar', 'Para Birimi', 'Durum', 'Kaydeden', 'Not'], $delimiter);

            foreach ($query->lazy(500) as $masraf) {
                fputcsv($out, [
                    optional($masraf->tarih)->format('Y-m-d'),
                    (string) ($masraf->kategori?->ustKategori?->ad ?? ''),
                    (string) ($masraf->kategori?->ad ?? ''),
                    (string) ($masraf->isletmeProjesi ? $masraf->isletmeProjesi->kod.' / '.$masraf->isletmeProjesi->ad : ''),
                    (string) $masraf->aciklama,
                    (string) $masraf->tutar,
                    strtoupper((string) ($masraf->para_birimi ?: 'TRY')),
                    $masraf->durum === Masraf::DURUM_IPTAL ? 'Iptal' : 'Aktif',
                    (string) ($masraf->olusturanKullanici?->name ?? ''),
                    (string) ($masraf->notlar ?? ''),
                ], $delimiter);
            }

            if ($projeButceOzeti !== []) {
                fputcsv($out, [], $delimiter);
                fputcsv($out, ['Proje Bütçe / Gerçekleşen'], $delimiter);
                fputcsv($out, ['Kod', 'Proje', 'Bütçe', 'Dönem Gerçekleşen', 'Kalan Bütçe', 'Para Birimi', 'Kayıt'], $delimiter);
                foreach ($projeButceOzeti as $projeSatiri) {
                    fputcsv($out, [
                        $projeSatiri['kod'],
                        $projeSatiri['proje'],
                        $projeSatiri['butce'] ?? '',
                        $projeSatiri['gerceklesen'],
                        $projeSatiri['kalan'] ?? '',
                        $projeSatiri['para_birimi'],
                        $projeSatiri['adet'],
                    ], $delimiter);
                }
            }

            if ($kategoriButceOzeti !== []) {
                fputcsv($out, [], $delimiter);
                fputcsv($out, ['Kategori Bütçe / Gerçekleşen'], $delimiter);
                fputcsv($out, ['Ana Kategori', 'Masraf Türü', 'Başlangıç', 'Bitiş', 'Bütçe', 'Gerçekleşen', 'Kalan', 'Para Birimi', 'Kayıt', 'Durum'], $delimiter);
                foreach ($kategoriButceOzeti as $butceSatiri) {
                    fputcsv($out, [
                        $butceSatiri['ana_kategori'],
                        $butceSatiri['kategori'],
                        $butceSatiri['baslangic'],
                        $butceSatiri['bitis'],
                        $butceSatiri['butce'],
                        $butceSatiri['gerceklesen'],
                        $butceSatiri['kalan'],
                        $butceSatiri['para_birimi'],
                        $butceSatiri['adet'],
                        $butceSatiri['durum'] === MasrafButcesi::DURUM_AKTIF ? 'Aktif' : 'Kapalı',
                    ], $delimiter);
                }
            }

            if ($personelGiderleri !== []) {
                fputcsv($out, [], $delimiter);
                fputcsv($out, ['Otomatik Personel Giderleri'], $delimiter);
                fputcsv($out, ['Kalem', 'Tutar', 'Para Birimi', 'Kayıt'], $delimiter);
                foreach ($personelGiderleri as $personelGideri) {
                    fputcsv($out, [
                        $personelGideri['kalem'],
                        $personelGideri['toplam'],
                        $personelGideri['para_birimi'],
                        $personelGideri['adet'],
                    ], $delimiter);
                }
            }

            if ($teknikServisGiderleri !== []) {
                fputcsv($out, [], $delimiter);
                fputcsv($out, ['Otomatik Teknik Servis Giderleri'], $delimiter);
                fputcsv($out, ['Kalem', 'Tutar', 'Para Birimi', 'Kayıt'], $delimiter);
                foreach ($teknikServisGiderleri as $teknikServisGideri) {
                    fputcsv($out, [
                        $teknikServisGideri['kalem'],
                        $teknikServisGideri['toplam'],
                        $teknikServisGideri['para_birimi'],
                        $teknikServisGideri['adet'],
                    ], $delimiter);
                }
            }

            fclose($out);
        }, $dosyaAdi, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function masrafExcelCsvIndir(): StreamedResponse
    {
        return $this->masrafCsvIndir(true);
    }

    /**
     * Seçilen filtrelerle eşleşen tüm masraf hareketlerini rapor tablosu için döndürür.
     * CSV dışa aktarımıyla aynı sorgu kullanıldığı için ekrandaki liste ve dışa aktarılan
     * kayıtlar arasında kapsam farkı oluşmaz.
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, Masraf>
     */
    public function masrafHareketleri()
    {
        return $this->masrafSorgusu()
            ->orderByDesc('tarih')
            ->orderByDesc('id')
            ->paginate(25, ['*'], 'hareketlerPage');
    }

    private function masrafSorgusu(): Builder
    {
        $query = Masraf::query()
            ->with(['kategori:id,ad,ust_kategori_id', 'kategori.ustKategori:id,ad', 'isletmeProjesi:id,kod,ad', 'olusturanKullanici:id,name'])
            ->select(['id', 'firma_id', 'masraf_kategorisi_id', 'isletme_proje_id', 'tarih', 'tutar', 'para_birimi', 'aciklama', 'notlar', 'durum', 'olusturan_kullanici_id'])
            ->where('firma_id', $this->aktifFirmaId() ?? 0)
            ->whereBetween('tarih', [$this->filtreler['baslangic'].' 00:00:00', $this->filtreler['bitis'].' 23:59:59']);

        if (($kategori = $this->filtreler['kategori'] ?? '') !== '') {
            $query->whereIn('masraf_kategorisi_id', $this->kategoriKapsamIdleri((int) $kategori));
        }

        if (($proje = (string) ($this->filtreler['isletme_proje_id'] ?? '')) !== '') {
            $proje === 'projesiz'
                ? $query->whereNull('isletme_proje_id')
                : $query->where('isletme_proje_id', (int) $proje);
        }

        $arama = trim($this->hareketArama);
        if ($arama !== '') {
            $query->where(function (Builder $aramaSorgusu) use ($arama): void {
                $aramaSorgusu
                    ->where('aciklama', 'like', '%'.$arama.'%')
                    ->orWhere('notlar', 'like', '%'.$arama.'%')
                    ->orWhereHas('kategori', fn (Builder $kategoriSorgusu) => $kategoriSorgusu->where('ad', 'like', '%'.$arama.'%'))
                    ->orWhereHas('isletmeProjesi', fn (Builder $projeSorgusu) => $projeSorgusu
                        ->where('ad', 'like', '%'.$arama.'%')
                        ->orWhere('kod', 'like', '%'.$arama.'%'));
            });
        }

        $durum = $this->filtreler['durum'] ?? Masraf::DURUM_AKTIF;
        return $durum === 'tumu' ? $query : $query->where('durum', $durum);
    }

    private function filtreliSorgu(): Builder
    {
        $query = Masraf::query()
            ->where('masraflar.firma_id', $this->aktifFirmaId() ?? 0)
            ->whereBetween('masraflar.tarih', [$this->filtreler['baslangic'].' 00:00:00', $this->filtreler['bitis'].' 23:59:59']);

        $durum = $this->filtreler['durum'] ?? Masraf::DURUM_AKTIF;
        if ($durum !== 'tumu') {
            $query->where('masraflar.durum', $durum);
        }

        if (($kategori = $this->filtreler['kategori'] ?? '') !== '') {
            $query->whereIn('masraflar.masraf_kategorisi_id', $this->kategoriKapsamIdleri((int) $kategori));
        }

        if (($proje = (string) ($this->filtreler['isletme_proje_id'] ?? '')) !== '') {
            $proje === 'projesiz'
                ? $query->whereNull('masraflar.isletme_proje_id')
                : $query->where('masraflar.isletme_proje_id', (int) $proje);
        }

        return $query;
    }

    /** @return array<int> */
    private function kategoriKapsamIdleri(int $kategoriId): array
    {
        if ($kategoriId < 1) {
            return [];
        }

        $kapsam = [$kategoriId];
        $bekleyen = [$kategoriId];

        while ($bekleyen !== []) {
            $ustId = array_shift($bekleyen);
            $altIdleri = array_map('intval', array_keys($this->kategoriAltSecenekleri($ustId)));
            $kapsam = array_values(array_unique([...$kapsam, ...$altIdleri]));
            $bekleyen = [...$bekleyen, ...$altIdleri];
        }

        return $kapsam;
    }

    /** @return array<int|string, string> */
    private function kategoriSecenekleri(): array
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId === null) {
            return [];
        }

        return Cache::remember(
            MasrafKategorisi::secenekCacheAnahtari($firmaId),
            now()->addMinutes(10),
            fn (): array => MasrafKategorisi::query()
                ->where('firma_id', $firmaId)
                ->aktif()
                ->where('secilir_mi', true)
                ->orderBy('sira')
                ->with('ustKategori:id,ad')
                ->get(['id', 'ad', 'ust_kategori_id'])
                ->mapWithKeys(fn (MasrafKategorisi $kategori): array => [
                    $kategori->id => $kategori->ustKategori
                        ? $kategori->ustKategori->ad.' / '.$kategori->ad
                        : $kategori->ad,
                ])
                ->all(),
        );
    }

    /** @return array<int|string, string> */
    private function projeSecenekleri(string $arama = ''): array
    {
        $firmaId = $this->aktifFirmaId();
        $arama = trim($arama);
        if ($firmaId === null) {
            return [];
        }

        return IsletmeProjesi::query()
            ->where('firma_id', $firmaId)
            ->where('durum', IsletmeProjesi::DURUM_AKTIF)
            ->when($arama !== '', fn (Builder $query): Builder => $query->where(function (Builder $inner) use ($arama): void {
                $inner->where('kod', 'like', '%'.$arama.'%')
                    ->orWhere('ad', 'like', '%'.$arama.'%');
            }))
            ->orderBy('ad')
            ->limit(50)
            ->get(['id', 'kod', 'ad'])
            ->mapWithKeys(fn (IsletmeProjesi $proje): array => [$proje->id => $proje->ad])
            ->all();
    }

    private function projeEtiketi(mixed $value): ?string
    {
        $firmaId = $this->aktifFirmaId();
        $id = (int) $value;
        if ($firmaId === null || $id < 1) {
            return null;
        }

        $proje = IsletmeProjesi::query()
            ->where('firma_id', $firmaId)
            ->whereKey($id)
            ->first(['id', 'kod', 'ad']);

        return $proje?->ad;
    }

    private function filtreFormunuDoldur(): void
    {
        $this->filtreForm->fill($this->filtreler);
    }

    private function filtreleriVarsayilanla(): void
    {
        $this->filtreler = [
            'baslangic' => now()->startOfMonth()->toDateString(),
            'bitis' => now()->toDateString(),
            'kategori' => '',
            'isletme_proje_id' => '',
            'durum' => Masraf::DURUM_AKTIF,
            'personel_maliyet_turu' => 'brut',
        ];
    }

    private function aktifFirmaId(): ?int
    {
        $firmaId = app(TenantContextService::class)->aktifFirmaId();

        return $firmaId ? (int) $firmaId : null;
    }

    /** @return array<int, Forms\Components\Component> */
    private function kategoriSecimBilesenleri(): array
    {
        $bilesenler = [];
        $seviyeSayisi = $this->kategoriSeviyeSayisi();

        for ($seviye = 0; $seviye < $seviyeSayisi; $seviye++) {
            $alan = 'kategori_seviyesi_'.$seviye;
            $oncekiAlan = 'kategori_seviyesi_'.($seviye - 1);

            $bilesenler[] = Forms\Components\Select::make($alan)
                ->label($seviye === 0 ? 'Masraf kategorisi' : 'Alt masraf türü')
                ->options(function (Forms\Get $get) use ($seviye, $oncekiAlan): array {
                    $secenekler = $this->kategoriAltSecenekleri($seviye === 0 ? null : (int) ($get($oncekiAlan) ?: 0));
                    return $seviye === 0 ? ['' => 'Tüm türler'] + $secenekler : $secenekler;
                })
                ->visible(fn (Forms\Get $get): bool => $seviye === 0
                    || ((int) ($this->kategoriSecimState[$seviye - 1] ?? $get($oncekiAlan) ?: 0) > 0
                        && $this->kategoriAltSecenekleri((int) ($this->kategoriSecimState[$seviye - 1] ?? $get($oncekiAlan))) !== []))
                ->live()
                ->afterStateUpdated(function (Forms\Set $set, mixed $state) use ($seviye): void {
                    for ($sonraki = $seviye + 1; $sonraki < $this->kategoriSeviyeSayisi(); $sonraki++) {
                        $set('kategori_seviyesi_'.$sonraki, null);
                    }

                    $id = (int) ($state ?: 0);
                    $this->kategoriSecimState[$seviye] = $id;
                    for ($sonraki = $seviye + 1; $sonraki < $this->kategoriSeviyeSayisi(); $sonraki++) {
                        unset($this->kategoriSecimState[$sonraki]);
                    }
                    // Ana kategori seçildiğinde de filtre aktif kalır; sorgu alt
                    // kategorileri dahil ederek kapsam filtresi uygular.
                    $set('kategori', $id > 0 ? $id : '');
                })
                ->native(false);
        }

        return [...$bilesenler, Forms\Components\Hidden::make('kategori')->dehydrated()];
    }

    private function kategoriSeviyeSayisi(): int
    {
        // Rapor formu da popup ile aynı kademeli seçim davranışını kullanır.
        return 8;
    }

    /** @return array<int|string, string> */
    private function kategoriAltSecenekleri(?int $ustKategoriId): array
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId === null) {
            return [];
        }

        return MasrafKategorisi::query()
            ->where('firma_id', $firmaId)
            ->aktif()
            ->where('ust_kategori_id', $ustKategoriId)
            ->orderBy('sira')
            ->orderBy('ad')
            ->pluck('ad', 'id')
            ->all();
    }
}
