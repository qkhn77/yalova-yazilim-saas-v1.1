<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\BarkodluSatis\Guvenlik\BarkodluSatisFilamentErisimYardimcisi;
use App\Filament\Clusters\Muhasebe as MuhasebeCluster;
use App\Models\Muhasebe\BarkodluSatis;
use App\Models\Muhasebe\BarkodluSatisKalemi;
use App\Models\Muhasebe\BarkodluSatisIade;
use App\Muhasebe\Servisler\BarkodluSatisServisi;
use App\Services\FirmaAyarDeposu;
use App\Services\TenantContextService;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BarkodluSatisIadeGecmisiSayfasi extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $cluster = MuhasebeCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Iade gecmisi';

    protected static ?string $slug = 'satis/barkodlu-satis-iade-gecmisi';

    protected static string $view = 'filament.clusters.muhasebe.pages.barkodlu-satis-iade-gecmisi-sayfasi';

    /** @var array<string,mixed> */
    public array $hizliIade = [];

    public ?int $hizliIadeSatisId = null;

    public ?int $sonOtomatikIadeId = null;

    public ?string $sonOtomatikIadeNo = null;

    public int $otomatikIadeGeriAlmaSuresiSaniye = 5;

    private bool $aktifFirmaIdCozuldu = false;

    private ?int $aktifFirmaIdCache = null;

    public function getHeading(): string|Htmlable
    {
        return 'Barkodlu satis iade gecmisi';
    }

    public function getSubheading(): ?string
    {
        return 'Tum iade hareketlerini filtreleyin, fisi goruntuleyin ve yazdirin.';
    }

    public function mount(): void
    {
        $firmaId = (int) ($this->aktifFirmaId() ?? 0);
        $tekKalemOtomatikKaydet = true;
        if ($firmaId > 0) {
            $ayarlar = Cache::remember(
                'barkodlu-satis:iade-gecmisi:ayarlar:v1:firma:'.$firmaId,
                now()->addSeconds(60),
                function () use ($firmaId): array {
                    $depo = app(FirmaAyarDeposu::class);

                    return [
                        'geri_alma_suresi' => (int) $depo->oku($firmaId, 'barkodlu_iade_geri_alma_suresi_saniye', 5),
                        'tek_kalem_otomatik' => (bool) $depo->oku($firmaId, 'barkodlu_satis_iade_ultra_hizli_varsayilan', true),
                    ];
                }
            );

            $this->otomatikIadeGeriAlmaSuresiSaniye = max(1, min(30, (int) ($ayarlar['geri_alma_suresi'] ?? 5)));
            $tekKalemOtomatikKaydet = (bool) ($ayarlar['tek_kalem_otomatik'] ?? true);
        }

        $this->form->fill([
            'satis_no' => null,
            'satis_kalem_id' => null,
            'iade_miktari' => 1,
            'neden' => null,
            'tek_kalem_otomatik_kaydet' => $tekKalemOtomatikKaydet,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('satis_no')
                    ->label('Satis no')
                    ->placeholder('Ornek: BS-2026-000123')
                    ->extraInputAttributes([
                        'id' => 'hizli-iade-satis-no',
                        'wire:keydown.enter.prevent' => 'hizliIadeSatisiniYukle',
                    ]),
                Forms\Components\Select::make('satis_kalem_id')
                    ->label('Iade edilecek kalem')
                    ->options(fn (): array => $this->hizliIadeKalemSecenekleri())
                    ->searchable()
                    ->extraInputAttributes([
                        'id' => 'hizli-iade-kalem',
                    ])
                    ->placeholder('Once satis secin'),
                Forms\Components\TextInput::make('iade_miktari')
                    ->label('Iade miktari')
                    ->numeric()
                    ->minValue(0.0001)
                    ->step('0.0001')
                    ->default(1)
                    ->extraInputAttributes([
                        'id' => 'hizli-iade-miktar',
                        'wire:keydown.enter.prevent' => 'hizliIadeKaydet',
                    ])
                    ->required(),
                Forms\Components\Textarea::make('neden')
                    ->label('Iade nedeni')
                    ->rows(2)
                    ->extraInputAttributes([
                        'id' => 'hizli-iade-neden',
                        'wire:keydown.enter.prevent' => 'hizliIadeKaydet',
                    ])
                    ->maxLength(1000),
                Forms\Components\Toggle::make('tek_kalem_otomatik_kaydet')
                    ->label('Tek kalemde otomatik kaydet (Ultra hizli)')
                    ->default(true),
            ])
            ->columns(2)
            ->statePath('hizliIade');
    }

    public static function canAccess(): bool
    {
        return BarkodluSatisFilamentErisimYardimcisi::herhangiBirBarkodluSatisYetkisiVarMi([
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_GORUNTULE,
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_IADE,
        ]);
    }

    public function hizliIadeSatisiniYukle(): void
    {
        $satisNo = trim((string) ($this->hizliIade['satis_no'] ?? ''));
        if ($satisNo === '') {
            Notification::make()->title('Satis no giriniz')->warning()->send();

            return;
        }

        $satis = BarkodluSatis::query()
            ->where('satis_no', $satisNo)
            ->with(['kalemler', 'iadeler.kalemler'])
            ->first();

        if (! $satis) {
            $this->hizliIadeSatisId = null;
            $this->hizliIade['satis_kalem_id'] = null;
            Notification::make()->title('Satis kaydi bulunamadi')->danger()->send();

            return;
        }

        if ((string) ($satis->durum ?? '') === 'iptal') {
            $this->hizliIadeSatisId = null;
            $this->hizliIade['satis_kalem_id'] = null;
            Notification::make()->title('Iptal edilmis satis iade edilemez')->danger()->send();

            return;
        }

        $this->hizliIadeSatisId = (int) $satis->id;
        $secenekler = $this->hizliIadeKalemSecenekleri();
        $ilk = array_key_first($secenekler);
        $this->hizliIade['satis_kalem_id'] = $ilk ? (int) $ilk : null;
        $this->hizliIade['iade_miktari'] = 1;

        $otomatikKaydet = (bool) ($this->hizliIade['tek_kalem_otomatik_kaydet'] ?? false);
        if ($otomatikKaydet && count($secenekler) === 1 && $this->hizliIade['satis_kalem_id']) {
            $this->hizliIadeKaydet();

            return;
        }

        Notification::make()
            ->title('Satis yüklendi')
            ->body('Iade kalemini secip kaydedebilirsiniz.')
            ->success()
            ->send();
        $this->dispatch('hizli-iade-miktar-odakla');
    }

    public function hizliIadeKaydet(): void
    {
        if (! $this->hizliIadeYetkisiVarMi()) {
            Notification::make()->title('Bu islem icin yetkiniz yok')->danger()->send();

            return;
        }

        $satisId = (int) ($this->hizliIadeSatisId ?? 0);
        $satisKalemId = (int) ($this->hizliIade['satis_kalem_id'] ?? 0);
        $iadeMiktari = (float) ($this->hizliIade['iade_miktari'] ?? 0);
        $neden = trim((string) ($this->hizliIade['neden'] ?? ''));

        if ($satisId < 1 || $satisKalemId < 1 || $iadeMiktari <= 0) {
            Notification::make()->title('Gerekli alanlari doldurun')->warning()->send();

            return;
        }

        $satis = BarkodluSatis::query()->whereKey($satisId)->first();
        if (! $satis) {
            Notification::make()->title('Satis kaydi bulunamadi')->danger()->send();

            return;
        }

        try {
            $iade = app(BarkodluSatisServisi::class)->satisKalemiIadeEt(
                firmaId: (int) $satis->firma_id,
                satisId: (int) $satis->id,
                satisKalemId: $satisKalemId,
                iadeMiktari: $iadeMiktari,
                kullaniciId: (int) (auth()->id() ?? 0),
                neden: $neden !== '' ? $neden : null,
            );

            $otomatik = (bool) ($this->hizliIade['tek_kalem_otomatik_kaydet'] ?? false);
            if ($otomatik) {
                $this->sonOtomatikIadeId = (int) $iade->id;
                $this->sonOtomatikIadeNo = (string) $iade->iade_no;
                $this->dispatch('hizli-iade-undo-penceresi-ac', saniye: $this->otomatikIadeGeriAlmaSuresiSaniye);

                Notification::make()
                    ->title('Iade kaydi olusturuldu')
                    ->body($this->otomatikIadeGeriAlmaSuresiSaniye.' saniye icinde geri alabilirsiniz.')
                    ->actions([
                        NotificationAction::make('geri_al')
                            ->label('Geri Al')
                            ->button()
                            ->color('warning')
                            ->dispatch('hizli-iade-geri-al-tiklandi'),
                    ])
                    ->success()
                    ->send();
            } else {
                Notification::make()->title('Iade kaydi olusturuldu')->success()->send();
            }

            $this->hizliIade['iade_miktari'] = 1;
            $this->hizliIade['neden'] = null;
        } catch (\Throwable $e) {
            Notification::make()->title('Iade islemi basarisiz')->body($e->getMessage())->danger()->send();
        }
    }

    public function sonOtomatikIadeyiGeriAl(): void
    {
        $iadeId = (int) ($this->sonOtomatikIadeId ?? 0);
        if ($iadeId < 1) {
            return;
        }

        $iade = BarkodluSatisIade::query()->whereKey($iadeId)->first();
        if (! $iade) {
            $this->otomatikIadeGeriAlFirsatiniKapat();

            return;
        }

        try {
            app(BarkodluSatisServisi::class)->iadeKaydiniGeriAl(
                firmaId: (int) $iade->firma_id,
                iadeId: (int) $iade->id,
                kullaniciId: (int) (auth()->id() ?? 0),
                neden: 'otomatik iade geri al',
            );

            Notification::make()->title('Iade geri alindi')->success()->send();
            $this->otomatikIadeGeriAlFirsatiniKapat();
        } catch (\Throwable $e) {
            Notification::make()->title('Iade geri alma basarisiz')->body($e->getMessage())->danger()->send();
        }
    }

    public function otomatikIadeGeriAlFirsatiniKapat(): void
    {
        $this->sonOtomatikIadeId = null;
        $this->sonOtomatikIadeNo = null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                BarkodluSatisIade::query()
                    ->select([
                        'id',
                        'firma_id',
                        'satis_id',
                        'iade_no',
                        'dogrulama_kodu',
                        'iade_tarihi',
                        'toplam_iade_tutari',
                        'olusturan_id',
                    ])
                    ->with([
                        'satis:id,firma_id,cari_id,satis_no,para_birimi',
                        'satis.cari:id,firma_id,ad',
                        'olusturan:id,name',
                    ])
                    ->withCount('kalemler')
            )
            ->headerActions([
                Tables\Actions\Action::make('export_csv')
                    ->label('CSV Disa Aktar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(fn (): StreamedResponse => $this->iadeGecmisiCsvIndir(false)),
                Tables\Actions\Action::make('export_excel_csv')
                    ->label('Excel Uyumlu CSV')
                    ->icon('heroicon-o-document-chart-bar')
                    ->color('success')
                    ->action(fn (): StreamedResponse => $this->iadeGecmisiCsvIndir(true)),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('iade_no')
                    ->label('Iade no')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('iade_tarihi')
                    ->label('Iade tarihi')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('satis.satis_no')
                    ->label('Satis no')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('satis.cari.ad')
                    ->label('Cari')
                    ->placeholder('-')
                    ->searchable(),
                Tables\Columns\TextColumn::make('kalem_sayisi')
                    ->label('Kalem')
                    ->state(fn (BarkodluSatisIade $record): int => (int) ($record->kalemler_count ?? 0)),
                Tables\Columns\TextColumn::make('toplam_iade_tutari')
                    ->label('Iade tutari')
                    ->money(fn (BarkodluSatisIade $record): string => strtoupper((string) ($record->satis?->para_birimi ?: 'TRY')))
                    ->sortable(),
                Tables\Columns\TextColumn::make('olusturan.name')
                    ->label('Olusturan')
                    ->placeholder('-'),
            ])
            ->defaultSort('iade_tarihi', 'desc')
            ->filters([
                Tables\Filters\Filter::make('bu_ay')
                    ->label('Bu ay')
                    ->query(fn ($query) => $query->whereBetween('iade_tarihi', [now()->startOfMonth(), now()->endOfMonth()])),
            ])
            ->actions([
                Tables\Actions\Action::make('fis')
                    ->label('Iade Fisi')
                    ->icon('heroicon-o-printer')
                    ->url(fn (BarkodluSatisIade $record): string => BarkodluSatisIadeFisiSayfasi::getUrl([
                        'iade' => $record->id,
                        'kod' => (string) ($record->dogrulama_kodu ?? ''),
                        'sig' => $this->iadeFisiImzasi((int) $record->id, (string) ($record->dogrulama_kodu ?? '')),
                    ]))
                    ->openUrlInNewTab(),
            ])
            ->deferLoading()
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    public function iadeGecmisiCsvIndir(bool $excelUyumlu = false): StreamedResponse
    {
        $sorgu = $this->getFilteredSortedTableQuery()->clone()->with(['satis.cari', 'kalemler', 'olusturan']);
        $delimiter = $excelUyumlu ? ';' : ',';
        $dosyaAdi = 'barkodlu-satis-iade-gecmisi-'.now()->format('Ymd_His').($excelUyumlu ? '-excel' : '').'.csv';

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
            fputcsv($out, ['Iade No', 'Iade Tarihi', 'Satis No', 'Cari', 'Kalem', 'Iade Tutari', 'Olusturan'], $delimiter);

            /** @var BarkodluSatisIade $kayit */
            foreach ($sorgu->cursor() as $kayit) {
                fputcsv($out, [
                    (string) $kayit->iade_no,
                    optional($kayit->iade_tarihi)->format('d.m.Y H:i') ?: '',
                    (string) ($kayit->satis?->satis_no ?? ''),
                    (string) ($kayit->satis?->cari?->ad ?? ''),
                    (string) $kayit->kalemler->count(),
                    number_format((float) $kayit->toplam_iade_tutari, 2, ',', '.'),
                    (string) ($kayit->olusturan?->name ?? ''),
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
        $satirlar[] = ['Rapor', 'Barkodlu Satis Iade Gecmisi'];
        $satirlar[] = ['Olusturulma', now()->format('d.m.Y H:i:s')];

        $arama = trim((string) ($this->getTableSearch() ?? ''));
        $satirlar[] = ['Global Arama', $arama !== '' ? $arama : '-'];

        $siralaKolon = (string) ($this->getTableSortColumn() ?? '');
        $siralaYon = (string) ($this->getTableSortDirection() ?? '');
        $sirala = $siralaKolon !== '' ? $siralaKolon.' '.($siralaYon !== '' ? $siralaYon : 'asc') : 'varsayilan';
        $satirlar[] = ['Siralama', $sirala];

        $buAyHam = $this->getTableFilterState('bu_ay');
        $buAy = is_array($buAyHam) ? Arr::get($buAyHam, 'isActive') : null;

        $satirlar[] = ['Filtre Bu Ay', $this->aktifPasifEtiketi($buAy)];

        return $satirlar;
    }

    private function aktifPasifEtiketi(mixed $deger): string
    {
        if ($deger === null || $deger === '') {
            return 'Kapali';
        }

        return filter_var($deger, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ? 'Acik' : 'Kapali';
    }

    private function iadeFisiImzasi(int $iadeId, string $dogrulamaKodu): string
    {
        if ($iadeId < 1 || trim($dogrulamaKodu) === '') {
            return '';
        }

        $anahtar = (string) config('app.key');
        if ($anahtar === '') {
            return '';
        }

        if (str_starts_with($anahtar, 'base64:')) {
            $cozulmus = base64_decode(substr($anahtar, 7), true);
            if ($cozulmus !== false) {
                $anahtar = $cozulmus;
            }
        }

        $mesaj = 'iade_fis|'.$iadeId.'|'.$dogrulamaKodu;

        return hash_hmac('sha256', $mesaj, $anahtar);
    }

    /**
     * @return array<int, string>
     */
    private function hizliIadeKalemSecenekleri(): array
    {
        $satisId = (int) ($this->hizliIadeSatisId ?? 0);
        if ($satisId < 1) {
            return [];
        }

        $satis = BarkodluSatis::query()->whereKey($satisId)->first();
        if (! $satis) {
            return [];
        }

        $iadeMiktarlari = [];
        foreach ($satis->iadeler as $iade) {
            foreach ($iade->kalemler as $iadeKalemi) {
                $kalemId = (int) $iadeKalemi->satis_kalem_id;
                $iadeMiktarlari[$kalemId] = (float) ($iadeMiktarlari[$kalemId] ?? 0) + (float) $iadeKalemi->miktar;
            }
        }

        $secenekler = [];
        $kalemler = BarkodluSatisKalemi::query()
            ->where('firma_id', (int) $satis->firma_id)
            ->where('satis_id', (int) $satis->id)
            ->get(['id', 'miktar', 'stok_adi']);
        foreach ($kalemler as $kalem) {
            $kalemId = (int) $kalem->id;
            $kalan = max(0, (float) $kalem->miktar - (float) ($iadeMiktarlari[$kalemId] ?? 0));
            if ($kalan <= 0.0001) {
                continue;
            }

            $secenekler[$kalemId] = (string) $kalem->stok_adi.' - Kalan: '.number_format($kalan, 2, ',', '.');
        }

        return $secenekler;
    }

    private function hizliIadeYetkisiVarMi(): bool
    {
        return BarkodluSatisFilamentErisimYardimcisi::barkodluSatisYetkisiVarMi(
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_IADE
        );
    }

    private function aktifFirmaId(): ?int
    {
        if (! $this->aktifFirmaIdCozuldu) {
            $firmaId = app(TenantContextService::class)->aktifFirmaId();
            $this->aktifFirmaIdCache = $firmaId ? (int) $firmaId : null;
            $this->aktifFirmaIdCozuldu = true;
        }

        return $this->aktifFirmaIdCache;
    }
}
