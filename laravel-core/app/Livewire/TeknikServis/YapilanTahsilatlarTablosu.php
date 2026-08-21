<?php

namespace App\Livewire\TeknikServis;

use App\Filament\Clusters\Muhasebe\Pages\VadeTakipSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\TahsilatOlusturSayfasi;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi as MuhasebeFaturaKaynagi;
use App\Filament\Clusters\MasrafTakip\Kaynaklar\MasrafTakipFilamentErisimYardimcisi;
use App\Filament\Clusters\TeknikServis\Kaynaklar\TeknikServisFilamentErisimYardimcisi;
use App\Models\Muhasebe\AlacakPlani;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\MasrafKategorisi;
use App\Models\Proje\IsletmeProjesi;
use App\Models\TeknikServis\TeknikServisKaydi;
use App\Models\TeknikServis\TeknikServisMuhasebeBaglantisi;
use App\Models\TeknikServis\TeknikServisTahsilati;
use App\TeknikServis\Filament\ServisGiderFaturasiDestegi;
use App\TeknikServis\Filament\TeknikServisTahsilatFormu;
use App\TeknikServis\Servisler\TeknikServisAlacakOzetServisi;
use App\TeknikServis\Servisler\TeknikServisTahsilatServisi;
use App\Muhasebe\Servisler\MasrafFaturaKayitServisi;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Support\MasrafTakipYetkiSablonlari;
use App\Support\TeknikServisYetkiSablonlari;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Component;

class YapilanTahsilatlarTablosu extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public ?TeknikServisKaydi $record = null;

    public bool $showHeaderActions = true;

    public ?string $masrafIdempotencyKey = null;

    private ?Fatura $bagliFaturaCache = null;

    private bool $bagliFaturaCacheHazir = false;

    private ?float $aktifTahsilatToplamiCache = null;

    private ?float $servisToplamiCache = null;

    private ?float $kalanBorcCache = null;

    /** @var array<string,mixed>|null */
    private ?array $alacakOzetCache = null;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                TeknikServisTahsilati::query()
                    ->where('teknik_servis_kaydi_id', (int) ($this->record?->getKey() ?? 0))
            )
            ->heading(new HtmlString('<span class="sr-only">Tahsilat işlemleri</span>'))
            ->description(fn (): HtmlString => $this->tahsilatTablosuAciklamaMetni())
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'finansHareketi:id,tarih,tutar,para_birimi,durum',
                'iptalFinansHareketi:id,tarih,tutar,para_birimi,durum',
                'kasaHesabi:id,ad',
                'bankaHesabi:id,ad',
                'posHesabi:id,ad',
                'olusturan:id,name',
            ]))
            ->columns([
                Tables\Columns\TextColumn::make('tarih')
                    ->label('Tarih')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('kanal')
                    ->label('Kanal')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'kasa' => 'Kasa',
                        'banka' => 'Banka',
                        'pos' => 'POS',
                        default => strtoupper((string) $state),
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'kasa' => 'success',
                        'banka' => 'info',
                        'pos' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('_hesap')
                    ->label('Hesap')
                    ->state(fn (TeknikServisTahsilati $record): string => $this->hesapEtiketi($record))
                    ->wrap(),
                Tables\Columns\TextColumn::make('tutar')
                    ->label('Tutar')
                    ->state(fn (TeknikServisTahsilati $record): string => number_format((float) ($record->tutar ?? 0), 2, ',', '.').' '.strtoupper((string) ($record->kaynak_para_birimi ?: 'TRY')))
                    ->sortable(),
                Tables\Columns\TextColumn::make('_fatura')
                    ->label('Fatura')
                    ->html()
                    ->state(fn (TeknikServisTahsilati $record): string => $this->faturaEtiketi($record))
                    ->wrap(),
                Tables\Columns\TextColumn::make('_hedef_tutar')
                    ->label('Hedef')
                    ->state(function (TeknikServisTahsilati $record): string {
                        $hedefTutar = (float) ($record->hedef_tutar ?? 0);
                        $hedefPb = strtoupper((string) ($record->hedef_para_birimi ?: ''));

                        if ($hedefPb === '' || $hedefPb === strtoupper((string) ($record->kaynak_para_birimi ?: 'TRY'))) {
                            return '-';
                        }

                        return number_format($hedefTutar, 2, ',', '.').' '.$hedefPb;
                    }),
                Tables\Columns\TextColumn::make('durum')
                    ->label('Durum')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'aktif' => 'Aktif',
                        'iptal' => 'İptal',
                        default => ucfirst((string) $state),
                    })
                    ->color(fn (?string $state): string => $state === 'iptal' ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('_finans')
                    ->label('Finans')
                    ->html()
                    ->state(fn (TeknikServisTahsilati $record): string => $this->finansEtiketi($record))
                    ->wrap(),
                Tables\Columns\TextColumn::make('aciklama')
                    ->label('Açıklama')
                    ->limit(40)
                    ->tooltip(fn (TeknikServisTahsilati $record): ?string => $record->aciklama),
                Tables\Columns\TextColumn::make('olusturan.name')
                    ->label('Ekleyen')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions($this->showHeaderActions ? [
                Tables\Actions\Action::make('gider_ekle')
                    ->label('Masraf Ekle')
                    ->icon('heroicon-o-document-plus')
                    ->color('gray')
                    ->visible(fn (): bool => $this->masrafOlusturmaYetkisiVarMi())
                    ->modalHeading('Masraf Ekle')
                    ->modalWidth('7xl')
                    ->extraModalWindowAttributes(['class' => 'teknik-servis-masraf-ekle-modal'], merge: true)
                    ->mountUsing(function (Forms\ComponentContainer $form): void {
                        if (! $this->record) {
                            return;
                        }

                        $this->masrafIdempotencyKey = (string) Str::uuid();
                        $form->fill($this->masrafVarsayilanFormData());
                    })
                    ->form(fn (): array => $this->masrafFormu())
                    ->action(function (array $data): void {
                        try {
                            if (! $this->masrafOlusturmaYetkisiVarMi()) {
                                throw new IsKuraliIstisnasi('Masraf kaydı oluşturma yetkiniz bulunmuyor.');
                            }

                            if (! $this->record) {
                                throw new \RuntimeException('Servis kaydı bulunamadı.');
                            }

                            $faturaModu = (string) ($data['fatura_modu'] ?? 'yok');
                            $masrafKategoriId = $this->masrafKategoriId($data);
                            $masrafTutar = $this->masrafTutariniHesapla($faturaModu, $data);
                            $masrafParaBirimi = $this->masrafParaBirimi($faturaModu, $data);
                            $masraf = app(MasrafFaturaKayitServisi::class)->kaydet(
                                (int) $this->record->firma_id,
                                [
                                    'masraf_kategorisi_id' => $masrafKategoriId,
                                    'isletme_proje_id' => $data['isletme_proje_id'] ?? null,
                                    'tarih' => $data['masraf_tarih'] ?? now()->toDateString(),
                                    'tutar' => $masrafTutar,
                                    'para_birimi' => $masrafParaBirimi,
                                    'aciklama' => $data['masraf_aciklama'] ?? null,
                                    'notlar' => $data['masraf_notlar'] ?? null,
                                    'belge_yolu' => $data['belge_yolu'] ?? null,
                                    'belge_adi' => $data['belge_adi'] ?? null,
                                    'kaynak_turu' => 'teknik_servis',
                                    'kaynak_id' => (int) $this->record->getKey(),
                                ],
                                $faturaModu,
                                [
                                    ...$data,
                                    'fatura_cari_id' => $data['cari_id'] ?? null,
                                    'fatura_tarihi' => $data['tarih'] ?? ($data['masraf_tarih'] ?? now()->toDateString()),
                                    'fatura_aciklama' => $data['fatura_aciklama'] ?? ($data['masraf_aciklama'] ?? null),
                                ],
                                auth()->id() ? (int) auth()->id() : null,
                                $this->masrafIdempotencyKey ?: (string) Str::uuid(),
                            );
                            $this->muhasebeOzetCacheTemizle();
                            $this->resetTable();
                            Notification::make()
                                ->title('Masraf kaydedildi')
                                ->body($masraf->faturalar()->exists() ? 'Masraf ve gider faturası birlikte kaydedildi.' : 'Masraf Masraflar tablosuna kaydedildi.')
                                ->success()
                                ->send();
                            $this->dispatch('servis-tahsilat-guncellendi');
                        } catch (IsKuraliIstisnasi $e) {
                            Notification::make()->title('Masraf kaydedilemedi')->body($e->getMessage())->danger()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Masraf kaydedilemedi')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('tahsilat_ekle')
                    ->label('Tahsilat Ekle')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->form($this->tahsilatFormu())
                    ->action(function (array $data): void {
                        try {
                            if (! $this->record) {
                                throw new \RuntimeException('Servis kaydı bulunamadı.');
                            }

                            $sonuc = app(TeknikServisTahsilatServisi::class)->olustur($this->record, $data);
                            $this->muhasebeOzetCacheTemizle();
                            $this->resetTable();
                            Notification::make()
                                ->title(in_array((string) ($data['kanal'] ?? ''), ['veresiye', 'taksitli'], true) ? 'Ödeme planı oluşturuldu' : 'Tahsilat kaydedildi')
                                ->body(in_array((string) ($data['kanal'] ?? ''), ['veresiye', 'taksitli'], true) ? 'Plan #'.(int) $sonuc->getKey().' Finans > Vade Takibi ekranına eklendi.' : null)
                                ->success()
                                ->send();
                            $this->dispatch('servis-tahsilat-guncellendi');
                        } catch (\Throwable $e) {
                            Notification::make()->title('Tahsilat kaydedilemedi')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ] : [])
            ->actions([
                Tables\Actions\Action::make('duzelt')
                    ->label('Düzelt')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->modalHeading('Tahsilat kaydını düzenle')
                    ->visible(fn (TeknikServisTahsilati $record): bool => $record->durum === 'aktif')
                    ->fillForm(fn (TeknikServisTahsilati $record): array => [
                        'kanal' => $record->kanal,
                        'kasa_hesap_id' => $record->kasa_hesap_id,
                        'banka_hesap_id' => $record->banka_hesap_id,
                        'pos_hesap_id' => $record->pos_hesap_id,
                        'kaynak_para_birimi' => strtoupper((string) ($record->kaynak_para_birimi ?: $this->kaynakParaBirimi())),
                        'hedef_para_birimi' => strtoupper((string) ($record->hedef_para_birimi ?: $record->kaynak_para_birimi ?: $this->kaynakParaBirimi())),
                        'doviz_kuru_turu' => $record->doviz_kuru_turu ?: 'manuel',
                        'doviz_kuru' => $record->doviz_kuru,
                        'tutar' => $record->tutar,
                        'hedef_tutar' => $record->hedef_tutar,
                        'tarih' => optional($record->tarih)->format('Y-m-d H:i:s'),
                        'aciklama' => $record->aciklama,
                    ])
                    ->form($this->tahsilatFormu())
                    ->action(function (TeknikServisTahsilati $record, array $data): void {
                        try {
                            app(TeknikServisTahsilatServisi::class)->guncelle($record, $data);
                            $this->muhasebeOzetCacheTemizle();
                            $this->resetTable();
                            Notification::make()->title('Tahsilat güncellendi')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Tahsilat güncellenemedi')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('iptal')
                    ->label('İptal')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (TeknikServisTahsilati $record): bool => $record->durum === 'aktif')
                    ->action(function (TeknikServisTahsilati $record): void {
                        try {
                            app(TeknikServisTahsilatServisi::class)->iptalEt($record, 'Servis tahsilat tablosundan iptal edildi');
                            $this->muhasebeOzetCacheTemizle();
                            $this->resetTable();
                            Notification::make()->title('Tahsilat iptal edildi')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Tahsilat iptal edilemedi')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('tarih', 'desc')
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    public function render(): View
    {
        return view('livewire.teknik-servis.yapilan-tahsilatlar-tablosu');
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private function tahsilatFormu(): array
    {
        return $this->record ? TeknikServisTahsilatFormu::schema($this->record) : [];
    }

    private function kaynakParaBirimi(): string
    {
        return strtoupper((string) ($this->record?->cari?->para_birimi ?: 'TRY'));
    }

    private function aktifTahsilatToplami(): float
    {
        if ($this->aktifTahsilatToplamiCache !== null) {
            return $this->aktifTahsilatToplamiCache;
        }

        $servisKaydiId = (int) ($this->record?->getKey() ?? 0);

        if ($servisKaydiId < 1) {
            return $this->aktifTahsilatToplamiCache = 0.0;
        }

        return $this->aktifTahsilatToplamiCache = (float) TeknikServisTahsilati::query()
            ->where('teknik_servis_kaydi_id', $servisKaydiId)
            ->where('durum', 'aktif')
            ->sum('tutar');
    }

    private function servisToplami(): float
    {
        if ($this->servisToplamiCache !== null) {
            return $this->servisToplamiCache;
        }

        $fatura = $this->bagliFatura();

        if ($fatura) {
            return $this->servisToplamiCache = (float) ($fatura->odenecek_tutar ?? $fatura->genel_toplam ?? 0);
        }

        return $this->servisToplamiCache = (float) ($this->record?->toplam_tutar ?? 0);
    }

    private function kalanBorc(): float
    {
        if ($this->kalanBorcCache !== null) {
            return $this->kalanBorcCache;
        }

        $fatura = $this->bagliFatura();

        if ($fatura && (string) ($fatura->durum?->value ?? $fatura->durum) === 'onayli') {
            return $this->kalanBorcCache = max(0, (float) ($fatura->acik_tutar ?? 0));
        }

        return $this->kalanBorcCache = max(0, $this->servisToplami() - $this->aktifTahsilatToplami());
    }

    private function bagliFatura(): ?Fatura
    {
        if ($this->bagliFaturaCacheHazir) {
            return $this->bagliFaturaCache;
        }

        if (! $this->record) {
            return null;
        }

        $satisFaturasiId = TeknikServisMuhasebeBaglantisi::query()
            ->where('firma_id', (int) ($this->record->firma_id ?? 0))
            ->where('teknik_servis_kaydi_id', (int) $this->record->getKey())
            ->where('islem_tipi', 'satis')
            ->whereNotNull('satis_faturasi_id')
            ->orderByDesc('id')
            ->value('satis_faturasi_id');

        if ($satisFaturasiId) {
            $this->bagliFaturaCache = Fatura::query()
                ->select(['id', 'fatura_no', 'durum', 'odenecek_tutar', 'genel_toplam', 'acik_tutar', 'para_birimi'])
                ->find((int) $satisFaturasiId);
            $this->bagliFaturaCacheHazir = true;

            return $this->bagliFaturaCache;
        }

        $this->bagliFaturaCacheHazir = true;

        return $this->bagliFaturaCache = null;
    }

    private function muhasebeOzetCacheTemizle(): void
    {
        $this->bagliFaturaCache = null;
        $this->bagliFaturaCacheHazir = false;
        $this->aktifTahsilatToplamiCache = null;
        $this->servisToplamiCache = null;
        $this->kalanBorcCache = null;
        $this->alacakOzetCache = null;
    }

    private function bagliFaturaMetni(): string
    {
        $fatura = $this->bagliFatura();

        if (! $fatura) {
            return 'Bağlı fatura henüz oluşmamış. Tahsilat kaydı avans olarak tutulur.';
        }

        $faturaNo = (string) ($fatura->fatura_no ?: ('#'.$fatura->id));
        $acik = number_format((float) ($fatura->acik_tutar ?? 0), 2, ',', '.');
        $pb = strtoupper((string) ($fatura->para_birimi ?: 'TRY'));

        return $faturaNo.' | Açık tutar: '.$acik.' '.$pb;
    }

    private function tahsilatTablosuAciklamaMetni(): HtmlString
    {
        $parcalar = [
            '<span><strong>Servis toplamı:</strong> '.e($this->formatTutar($this->servisToplami())).'</span>',
            '<span><strong>Tahsil edilen:</strong> '.e($this->formatTutar($this->aktifTahsilatToplami())).'</span>',
            '<span><strong>Kalan:</strong> '.e($this->formatTutar($this->kalanBorc())).'</span>',
            '<a href="'.e(url('/admin/muhasebe/finans/finans-hareketleri')).'" target="_blank" style="text-decoration: underline;">Finans hareketlerini aç</a>',
        ];

        $vadeliPlanMetni = $this->vadeliPlanOzetMetni();
        if ($vadeliPlanMetni !== null) {
            $parcalar[] = $vadeliPlanMetni;
        }

        $fatura = $this->bagliFatura();

        if ($fatura) {
            $faturaNo = (string) ($fatura->fatura_no ?: ('#'.$fatura->id));
            $url = MuhasebeFaturaKaynagi::getUrl('edit', ['record' => $fatura]);

            $parcalar[] = '<span><strong>Bağlı fatura:</strong> <a href="'.e($url).'" target="_blank" style="text-decoration: underline;">'.e($faturaNo).'</a></span>';
        } else {
            $parcalar[] = '<span><strong>Bağlı fatura:</strong> Henüz oluşmamış.</span>';
        }

        return new HtmlString('<div class="teknik-servis-muhasebe-ozet">'.implode(' <span class="teknik-servis-muhasebe-ozet-sep">|</span> ', $parcalar).'</div>');
    }

    private function formatTutar(float $tutar): string
    {
        return number_format($tutar, 2, ',', '.').' '.strtoupper((string) ($this->bagliFatura()?->para_birimi ?: $this->kaynakParaBirimi()));
    }

    private function formatTutarParaBirimiyle(float $tutar, string $paraBirimi): string
    {
        return number_format($tutar, 2, ',', '.').' '.strtoupper($paraBirimi ?: 'TRY');
    }

    /**
     * @return array<int,array<string,string>>
     */
    public function vadeliPlanSatirlari(): array
    {
        $plan = $this->aktifAlacakPlani();
        if (! $plan instanceof AlacakPlani) {
            return [];
        }

        $taksitler = $this->alacakOzet()['taksitler'] ?? collect();

        return collect($taksitler)
            ->map(function ($taksit) use ($plan): array {
                $kalan = (float) ($taksit->kalan_tutar ?? 0);
                $odenen = (float) ($taksit->odenen_tutar ?? 0);
                $durum = (string) ($taksit->durum ?? '');
                $paraBirimi = (string) ($plan->para_birimi ?: $this->kaynakParaBirimi());

                return [
                    'sira' => '#'.(int) ($taksit->sira_no ?? 1),
                    'vade_tarihi' => $taksit->vade_tarihi?->format('d.m.Y') ?? '-',
                    'tutar' => $this->formatTutarParaBirimiyle((float) ($taksit->tutar ?? 0), $paraBirimi),
                    'odenen' => $this->formatTutarParaBirimiyle($odenen, $paraBirimi),
                    'kalan' => $this->formatTutarParaBirimiyle($kalan, $paraBirimi),
                    'odeme_tarihi' => $taksit->son_tahsilat_tarihi?->format('d.m.Y H:i') ?? '-',
                    'durum' => $this->vadeliTaksitDurumEtiketi($durum, $kalan, $odenen),
                    'durum_rengi' => $this->vadeliTaksitDurumRengi($durum, $kalan, $odenen),
                    'durum_sinifi' => $this->vadeliTaksitDurumSinifi($durum, $kalan, $odenen),
                    'tahsilat_url' => $this->vadeliTaksitTahsilatUrl($taksit, $kalan),
                ];
            })
            ->values()
            ->all();
    }

    public function vadeliPlanBaslik(): ?string
    {
        $plan = $this->aktifAlacakPlani();

        return $plan instanceof AlacakPlani
            ? $this->planTuruEtiketi((string) $plan->plan_turu).' planı #'.(int) $plan->getKey()
            : null;
    }

    public function vadeliPlanAltBaslik(): ?string
    {
        $plan = $this->aktifAlacakPlani();
        if (! $plan instanceof AlacakPlani) {
            return null;
        }

        $paraBirimi = (string) ($plan->para_birimi ?: $this->kaynakParaBirimi());
        $planlanan = $this->formatTutarParaBirimiyle((float) ($plan->planlanan_tutar ?? 0), $paraBirimi);
        $odenen = $this->formatTutarParaBirimiyle((float) ($plan->odenen_tutar ?? 0), $paraBirimi);
        $kalan = $this->formatTutarParaBirimiyle((float) ($this->alacakOzet()['plan_kalan_tutar'] ?? $plan->kalan_tutar ?? 0), $paraBirimi);

        return 'Planlanan: '.$planlanan.' | Ödenen: '.$odenen.' | Kalan: '.$kalan.' | Durum: '.$this->planDurumEtiketi((string) $plan->durum);
    }

    public function vadeTakipUrl(): string
    {
        return VadeTakipSayfasi::getUrl(['operasyon' => 1]);
    }

    private function vadeliTaksitTahsilatUrl(object $taksit, float $kalan): ?string
    {
        $durum = (string) ($taksit->durum ?? '');
        if ($kalan <= 0.009 || in_array($durum, ['odendi', 'iptal'], true)) {
            return null;
        }

        return TahsilatOlusturSayfasi::getUrl([
            'alacak_plan_taksiti_id' => (int) ($taksit->id ?? 0),
            'tutar' => number_format($kalan, 2, '.', ''),
            'aciklama' => 'Teknik servis vade tahsilatı - Taksit #'.(int) ($taksit->sira_no ?? 1),
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function alacakOzet(): array
    {
        if ($this->alacakOzetCache !== null) {
            return $this->alacakOzetCache;
        }

        if (! $this->record) {
            return $this->alacakOzetCache = [];
        }

        return $this->alacakOzetCache = app(TeknikServisAlacakOzetServisi::class)->ozet($this->record, [
            'toplam_tutar' => $this->servisToplami(),
            'para_birimi' => $this->bagliFatura()?->para_birimi ?: $this->kaynakParaBirimi(),
        ]);
    }

    private function aktifAlacakPlani(): ?AlacakPlani
    {
        $plan = $this->alacakOzet()['plan'] ?? null;

        return $plan instanceof AlacakPlani ? $plan : null;
    }

    private function vadeliPlanOzetMetni(): ?string
    {
        $plan = $this->aktifAlacakPlani();
        if (! $plan instanceof AlacakPlani) {
            return null;
        }

        $paraBirimi = (string) ($plan->para_birimi ?: $this->kaynakParaBirimi());
        $planlanan = $this->formatTutarParaBirimiyle((float) ($plan->planlanan_tutar ?? 0), $paraBirimi);
        $kalan = $this->formatTutarParaBirimiyle((float) ($this->alacakOzet()['plan_kalan_tutar'] ?? $plan->kalan_tutar ?? 0), $paraBirimi);
        $plansiz = $this->formatTutarParaBirimiyle((float) ($this->alacakOzet()['plansiz_kalan_tutar'] ?? 0), $paraBirimi);
        $vade = $plan->son_vade_tarihi?->format('d.m.Y') ?? '-';
        $url = $this->vadeTakipUrl();

        return '<span><strong>'.$this->planTuruEtiketi((string) $plan->plan_turu).' planı:</strong> '
            .'<a href="'.e($url).'" target="_blank" style="text-decoration: underline;">#'.e((string) $plan->getKey()).'</a>'
            .' | Planlanan: '.e($planlanan)
            .' | Kalan: '.e($kalan)
            .' | Plansız kalan: '.e($plansiz)
            .' | Vade: '.e($vade)
            .' | Durum: '.e($this->planDurumEtiketi((string) $plan->durum))
            .'</span>';
    }

    private function planTuruEtiketi(string $planTuru): string
    {
        return match ($planTuru) {
            'taksit' => 'Taksitli',
            'veresiye' => 'Veresiye',
            default => ucfirst($planTuru ?: 'Vadeli'),
        };
    }

    private function planDurumEtiketi(string $durum): string
    {
        return match ($durum) {
            'aktif', 'bekliyor' => 'Ödenmedi',
            'kismi_odendi' => 'Kısmi ödendi',
            'gecikti' => 'Gecikti',
            'odendi' => 'Ödendi',
            'iptal' => 'İptal',
            default => ucfirst(str_replace('_', ' ', $durum ?: '-')),
        };
    }

    private function vadeliTaksitDurumEtiketi(string $durum, float $kalan, float $odenen): string
    {
        if ($kalan <= 0.009) {
            return 'Ödendi';
        }

        if ($odenen > 0.009) {
            return 'Kısmi ödendi';
        }

        return $this->planDurumEtiketi($durum);
    }

    private function vadeliTaksitDurumRengi(string $durum, float $kalan, float $odenen): string
    {
        if ($kalan <= 0.009) {
            return 'success';
        }

        if ($durum === 'gecikti') {
            return 'danger';
        }

        if ($odenen > 0.009 || $durum === 'kismi_odendi') {
            return 'warning';
        }

        return 'gray';
    }

    private function vadeliTaksitDurumSinifi(string $durum, float $kalan, float $odenen): string
    {
        return match ($this->vadeliTaksitDurumRengi($durum, $kalan, $odenen)) {
            'success' => 'bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-500/10 dark:text-green-300 dark:ring-green-400/20',
            'warning' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/20',
            'danger' => 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-400/20',
            default => 'bg-gray-50 text-gray-700 ring-gray-600/20 dark:bg-gray-500/10 dark:text-gray-300 dark:ring-gray-400/20',
        };
    }

    /** @return array<int, Forms\Components\Component> */
    private function masrafFormu(): array
    {
        if (! $this->record) {
            return [];
        }

        $firmaId = (int) $this->record->firma_id;
        MasrafKategorisi::varsayilanlariHazirla($firmaId);

        $faturaBilesenleri = array_map(
            fn (Forms\Components\Component $component): Forms\Components\Component => $component
                ->visible(fn (Get $get): bool => $get('fatura_modu') === 'yeni'),
            ServisGiderFaturasiDestegi::formSchema($this->record),
        );

        return [
            Forms\Components\Select::make('fatura_modu')
                ->label('Masraf durumu')
                ->options([
                    'yok' => 'Faturasız masraf',
                    'mevcut' => 'Mevcut gider faturasına bağla',
                    'yeni' => 'Yeni gider faturası oluştur',
                ])
                ->default('yok')
                ->live()
                ->required()
                ->native(false),
            Forms\Components\Section::make('Masraf Türü')
                ->schema([
                    Forms\Components\Select::make('masraf_kategori_ust_id')
                        ->label('Ana masraf türü')
                        ->options(fn (): array => $this->masrafKategoriUstSecenekleri($firmaId))
                        ->live()
                        ->afterStateUpdated(fn (Forms\Set $set): mixed => $set('masraf_kategorisi_id', null))
                        ->required()
                        ->native(false),
                    Forms\Components\Select::make('masraf_kategorisi_id')
                        ->label('Alt masraf türü')
                        ->options(fn (Get $get): array => $this->masrafKategoriAltSecenekleri($firmaId, $get('masraf_kategori_ust_id')))
                        ->visible(fn (Get $get): bool => $this->masrafKategoriAltSecenekleri($firmaId, $get('masraf_kategori_ust_id')) !== [])
                        ->required(fn (Get $get): bool => $this->masrafKategoriAltSecenekleri($firmaId, $get('masraf_kategori_ust_id')) !== [])
                        ->native(false),
                    Forms\Components\Select::make('isletme_proje_id')
                        ->label('İşletme projesi')
                        ->searchable()
                        ->options(fn (): array => $this->projeSecenekleri($firmaId))
                        ->getSearchResultsUsing(fn (string $search): array => $this->projeSecenekleri($firmaId, $search))
                        ->getOptionLabelUsing(fn ($value): ?string => $this->projeEtiketi($firmaId, $value))
                        ->helperText('Masrafı Proje Yönetimi modülünde tanımlı bir projeye bağlar.')
                        ->native(false)
                        ->columnSpanFull(),
                    Forms\Components\DatePicker::make('masraf_tarih')
                        ->label('Masraf tarihi')
                        ->required()
                        ->default(now()->toDateString())
                        ->native(false),
                    Forms\Components\TextInput::make('masraf_tutar')
                        ->label('Masraf tutarı')
                        ->numeric()
                        ->minValue(0.01)
                        ->required(fn (Get $get): bool => $get('fatura_modu') === 'yok')
                        ->visible(fn (Get $get): bool => $get('fatura_modu') === 'yok')
                        ->inputMode('decimal'),
                    Forms\Components\Placeholder::make('otomatik_masraf_tutari')
                        ->label('Masraf tutarı')
                        ->content(fn (Get $get): string => match ($get('fatura_modu')) {
                            'mevcut' => 'Seçilecek faturanın toplam tutarı alınır.',
                            'yeni' => 'Fatura kalemlerinin toplamından hesaplanır.',
                            default => '-',
                        })
                        ->visible(fn (Get $get): bool => $get('fatura_modu') !== 'yok'),
                    Forms\Components\Select::make('masraf_para_birimi')
                        ->label('Masraf para birimi')
                        ->options(['TRY' => '₺ Türk Lirası', 'USD' => '$ Amerikan Doları', 'EUR' => '€ Euro', 'GBP' => '£ İngiliz Sterlini'])
                        ->default('TRY')
                        ->required(fn (Get $get): bool => $get('fatura_modu') === 'yok')
                        ->visible(fn (Get $get): bool => $get('fatura_modu') === 'yok')
                        ->native(false),
                    Forms\Components\Placeholder::make('otomatik_masraf_para_birimi')
                        ->label('Masraf para birimi')
                        ->content(fn (Get $get): string => $get('fatura_modu') === 'mevcut'
                            ? 'Seçilen faturadan alınır.'
                            : 'Yeni fatura para biriminden alınır.')
                        ->visible(fn (Get $get): bool => $get('fatura_modu') !== 'yok'),
                    Forms\Components\Textarea::make('masraf_aciklama')
                        ->label('Masraf açıklaması')
                        ->required()
                        ->rows(2)
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('masraf_notlar')
                        ->label('Masraf notu')
                        ->rows(2)
                        ->columnSpanFull(),
                    Forms\Components\FileUpload::make('belge_yolu')
                        ->label('Belge / fiş / fatura')
                        ->helperText('PDF, JPG veya PNG; en fazla 10 MB. Mobilde kamera ile fotoğraf çekebilirsiniz.')
                        ->disk('public')
                        ->directory('masraflar/'.($firmaId ?: 0))
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                        ->extraInputAttributes(['capture' => 'environment'])
                        ->maxSize(10240)
                        ->validationMessages([
                            'mimetypes' => 'Belge yalnızca PDF, JPG veya PNG olabilir.',
                            'max' => 'Belge boyutu en fazla 10 MB olabilir.',
                        ])
                        ->storeFileNamesIn('belge_adi')
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Forms\Components\Select::make('fatura_id')
                ->label('Mevcut gider faturası')
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => $this->giderFaturaSecenekleri($firmaId, $search))
                ->getOptionLabelUsing(fn ($value): ?string => $this->giderFaturaEtiketi($firmaId, $value))
                ->disableOptionWhen(fn ($value): bool => $this->giderFaturasiPasifMi($firmaId, $value))
                ->visible(fn (Get $get): bool => $get('fatura_modu') === 'mevcut')
                ->required(fn (Get $get): bool => $get('fatura_modu') === 'mevcut')
                ->native(false),
            Forms\Components\Section::make('Yeni gider faturası')
                ->schema($faturaBilesenleri)
                ->visible(fn (Get $get): bool => $get('fatura_modu') === 'yeni')
                ->columnSpanFull(),
        ];
    }

    /** @return array<string, mixed> */
    private function masrafVarsayilanFormData(): array
    {
        $faturaVarsayilanlari = $this->record
            ? ServisGiderFaturasiDestegi::varsayilanFormData($this->record)
            : [];

        return [
            ...$faturaVarsayilanlari,
            'masraf_tarih' => now()->toDateString(),
            'masraf_kategori_ust_id' => null,
            'masraf_kategorisi_id' => null,
            'isletme_proje_id' => null,
            'masraf_tutar' => null,
            'masraf_para_birimi' => 'TRY',
            'masraf_aciklama' => $this->record ? ServisGiderFaturasiDestegi::varsayilanAciklama($this->record) : null,
            'masraf_notlar' => null,
            'belge_yolu' => null,
            'belge_adi' => null,
            'fatura_modu' => 'yok',
            'fatura_id' => null,
        ];
    }

    /** @return array<int|string, string> */
    private function projeSecenekleri(int $firmaId, string $arama = ''): array
    {
        $arama = trim($arama);

        return IsletmeProjesi::query()
            ->where('firma_id', $firmaId)
            ->secilebilir()
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

    private function projeEtiketi(int $firmaId, mixed $value): ?string
    {
        $id = (int) $value;
        if ($id < 1) {
            return null;
        }

        return IsletmeProjesi::query()
            ->where('firma_id', $firmaId)
            ->whereKey($id)
            ->value('ad');
    }

    private function masrafOlusturmaYetkisiVarMi(): bool
    {
        return MasrafTakipFilamentErisimYardimcisi::masrafTakipYetkisiVarMi(MasrafTakipYetkiSablonlari::OLUSTUR)
            || TeknikServisFilamentErisimYardimcisi::teknikServisYetkisiVarMi(TeknikServisYetkiSablonlari::GUNCELLE);
    }

    /** @return array<int|string, string> */
    private function masrafKategoriUstSecenekleri(int $firmaId): array
    {
        return MasrafKategorisi::query()
            ->where('firma_id', $firmaId)
            ->where('aktif_mi', true)
            ->whereNull('ust_kategori_id')
            ->orderBy('sira')
            ->orderBy('ad')
            ->pluck('ad', 'id')
            ->all();
    }

    /** @return array<int|string, string> */
    private function masrafKategoriAltSecenekleri(int $firmaId, mixed $ustKategoriId): array
    {
        if ((int) $ustKategoriId < 1) {
            return [];
        }

        return MasrafKategorisi::query()
            ->where('firma_id', $firmaId)
            ->where('aktif_mi', true)
            ->where('ust_kategori_id', (int) $ustKategoriId)
            ->where('secilir_mi', true)
            ->orderBy('sira')
            ->orderBy('ad')
            ->pluck('ad', 'id')
            ->all();
    }

    private function masrafKategoriId(array $data): int
    {
        $ustKategoriId = (int) ($data['masraf_kategori_ust_id'] ?? 0);
        $altSecenekleri = $this->masrafKategoriAltSecenekleri((int) ($this->record?->firma_id ?? 0), $ustKategoriId);
        $kategoriId = (int) ($data['masraf_kategorisi_id'] ?? 0);

        if ($altSecenekleri !== []) {
            return $kategoriId;
        }

        return $ustKategoriId;
    }

    private function masrafTutariniHesapla(string $faturaModu, array $data): string|float
    {
        if ($faturaModu === 'yok') {
            return $data['masraf_tutar'] ?? 0;
        }

        if ($faturaModu === 'mevcut') {
            $fatura = Fatura::query()
                ->where('firma_id', (int) ($this->record?->firma_id ?? 0))
                ->whereKey((int) ($data['fatura_id'] ?? 0))
                ->firstOrFail(['id', 'odenecek_tutar', 'genel_toplam']);

            return bccomp((string) ($fatura->odenecek_tutar ?? 0), '0', 2) > 0
                ? (string) $fatura->odenecek_tutar
                : (string) ($fatura->genel_toplam ?? 0);
        }

        $hesap = MuhasebeFaturaKaynagi::hesaplaFormKalemleriVeOzet([
            ...$data,
            'para_birimi' => $data['para_birimi'] ?? 'TRY',
            'odendi_tutari' => 0,
        ]);

        return (string) ($hesap['odenecek_tutar'] ?? $hesap['genel_toplam'] ?? 0);
    }

    private function masrafParaBirimi(string $faturaModu, array $data): string
    {
        if ($faturaModu !== 'yok') {
            if ($faturaModu === 'mevcut') {
                return strtoupper((string) Fatura::query()
                    ->where('firma_id', (int) ($this->record?->firma_id ?? 0))
                    ->whereKey((int) ($data['fatura_id'] ?? 0))
                    ->value('para_birimi'));
            }

            return strtoupper((string) ($data['para_birimi'] ?? 'TRY'));
        }

        return strtoupper((string) ($data['masraf_para_birimi'] ?? 'TRY'));
    }

    /** @return array<int|string, string> */
    private function giderFaturaSecenekleri(int $firmaId, string $search = ''): array
    {
        return Fatura::query()
            ->where('firma_id', $firmaId)
            ->where('tur', 'gider')
            ->where('durum', '<>', 'iptal')
            ->when(trim($search) !== '', fn (Builder $query): Builder => $query->where(function (Builder $inner) use ($search): void {
                $inner->where('fatura_no', 'like', '%'.trim($search).'%')
                    ->orWhere('aciklama', 'like', '%'.trim($search).'%');
            }))
            ->latest('id')
            ->limit(50)
            ->withSum('masrafDagitimlari as masraf_dagitim_toplami', 'tutar')
            ->get(['id', 'fatura_no', 'genel_toplam', 'odenecek_tutar', 'para_birimi', 'aciklama'])
            ->mapWithKeys(fn (Fatura $fatura): array => [
                $fatura->id => $this->giderFaturaEtiketi($firmaId, $fatura->id, $fatura),
            ])->all();
    }

    private function giderFaturaEtiketi(int $firmaId, mixed $value, ?Fatura $fatura = null): ?string
    {
        $fatura ??= Fatura::query()
            ->where('firma_id', $firmaId)
            ->where('tur', 'gider')
            ->whereKey((int) $value)
            ->where('durum', '<>', 'iptal')
            ->withSum('masrafDagitimlari as masraf_dagitim_toplami', 'tutar')
            ->first(['id', 'fatura_no', 'genel_toplam', 'odenecek_tutar', 'para_birimi', 'aciklama']);

        if (! $fatura) {
            return null;
        }

        $pasif = $this->giderFaturasiPasifMi($firmaId, $fatura) ? ' | Pasif' : '';
        $tavan = $this->giderFaturaTavanTutari($fatura);

        return ($fatura->fatura_no ?: 'Taslak #'.$fatura->id)
            .' | '.number_format((float) $tavan, 2, ',', '.').' '.strtoupper((string) ($fatura->para_birimi ?: 'TRY'))
            .' | '.Illuminate\Support\Str::limit(trim((string) $fatura->aciklama), 45).$pasif;
    }

    private function giderFaturasiPasifMi(int $firmaId, mixed $value): bool
    {
        $fatura = $value instanceof Fatura
            ? $value
            : Fatura::query()
                ->where('firma_id', $firmaId)
                ->where('tur', 'gider')
                ->whereKey((int) $value)
                ->where('durum', '<>', 'iptal')
                ->withSum('masrafDagitimlari as masraf_dagitim_toplami', 'tutar')
                ->first(['id', 'genel_toplam', 'odenecek_tutar']);

        if (! $fatura) {
            return true;
        }

        $tavan = $this->giderFaturaTavanTutari($fatura);

        return bccomp((string) ($fatura->masraf_dagitim_toplami ?? 0), '0', 2) > 0
            || bccomp($tavan, '0', 2) <= 0;
    }

    private function giderFaturaTavanTutari(Fatura $fatura): string
    {
        $odenecek = bcadd((string) ($fatura->odenecek_tutar ?? 0), '0', 2);

        return bccomp($odenecek, '0', 2) > 0
            ? $odenecek
            : bcadd((string) ($fatura->genel_toplam ?? 0), '0', 2);
    }

    private function hesapEtiketi(TeknikServisTahsilati $record): string
    {
        return match ($record->kanal) {
            'kasa' => (string) ($record->kasaHesabi?->ad ?: '-'),
            'banka' => (string) ($record->bankaHesabi?->ad ?: '-'),
            'pos' => (string) ($record->posHesabi?->ad ?: '-'),
            default => '-',
        };
    }

    private function finansEtiketi(TeknikServisTahsilati $record): string
    {
        $listeUrl = url('/admin/muhasebe/finans/finans-hareketleri');
        $aktif = $record->finansHareketi?->id
            ? '<a href="'.e($listeUrl).'" target="_blank" style="text-decoration: underline;">#'.e((string) $record->finansHareketi->id).'</a>'
            : '-';
        $iptal = $record->iptalFinansHareketi?->id
            ? ' | Ters: #'.e((string) $record->iptalFinansHareketi->id)
            : '';

        return $aktif.$iptal;
    }

    private function faturaEtiketi(TeknikServisTahsilati $record): string
    {
        $fatura = $this->bagliFatura();

        if (! $fatura) {
            return '—';
        }

        $faturaNo = (string) ($fatura->fatura_no ?: ('#'.$fatura->id));
        $durum = (string) ($fatura->durum?->value ?? $fatura->durum);
        $tutar = number_format((float) ($fatura->odenecek_tutar ?? $fatura->genel_toplam ?? 0), 2, ',', '.');
        $pb = strtoupper((string) ($fatura->para_birimi ?: 'TRY'));
        $url = MuhasebeFaturaKaynagi::getUrl('edit', ['record' => $fatura]);

        return '<a href="'.e($url).'" target="_blank" style="text-decoration: underline;">'.e($faturaNo).'</a>'
            .'<div style="font-size:0.75rem;color:#64748b;">'.e('Durum: '.$durum.' | Tutar: '.$tutar.' '.$pb).'</div>';
    }
}
