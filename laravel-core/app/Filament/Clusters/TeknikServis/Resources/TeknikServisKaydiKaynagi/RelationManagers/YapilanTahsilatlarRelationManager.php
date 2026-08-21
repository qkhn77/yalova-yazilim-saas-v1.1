<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi\RelationManagers;

use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi as MuhasebeFaturaKaynagi;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi\Pages\TeknikServisKaydiDuzenle;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\MasrafKategorisi;
use App\Models\TeknikServis\TeknikServisMuhasebeBaglantisi;
use App\Models\TeknikServis\TeknikServisTahsilati;
use App\Filament\Clusters\MasrafTakip\Kaynaklar\MasrafTakipFilamentErisimYardimcisi;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Servisler\MasrafKayitServisi;
use App\Support\MasrafTakipYetkiSablonlari;
use App\Muhasebe\Servisler\MasrafKaynakDogrulamaServisi;
use App\TeknikServis\Filament\ServisGiderFaturasiDestegi;
use App\TeknikServis\Filament\TeknikServisTahsilatFormu;
use App\TeknikServis\Servisler\TeknikServisTahsilatServisi;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\HeaderActionsPosition;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class YapilanTahsilatlarRelationManager extends RelationManager
{
    protected static string $relationship = 'tahsilatlar';

    private ?Fatura $bagliFaturaCache = null;

    private bool $bagliFaturaCacheHazir = false;

    private ?float $aktifTahsilatToplamiCache = null;

    private ?float $servisToplamiCache = null;

    private ?float $kalanBorcCache = null;

    private ?string $kaynakParaBirimiCache = null;

    protected static ?string $title = 'Muhasebe Kayıtları';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $pageClass !== TeknikServisKaydiDuzenle::class;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->heading(new HtmlString('<span class="sr-only">Tahsilat işlemleri</span>'))
            ->description(fn (): HtmlString => $this->tahsilatTablosuAciklamaMetni())
            ->headerActionsPosition(HeaderActionsPosition::Bottom)
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->select([
                    'id',
                    'teknik_servis_kaydi_id',
                    'finans_hareketi_id',
                    'iptal_finans_hareketi_id',
                    'kasa_hesap_id',
                    'banka_hesap_id',
                    'pos_hesap_id',
                    'olusturan_id',
                    'tarih',
                    'kanal',
                    'tutar',
                    'kaynak_para_birimi',
                    'hedef_tutar',
                    'hedef_para_birimi',
                    'durum',
                    'aciklama',
                ])
                ->with([
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
            ->headerActions([
                Tables\Actions\Action::make('gider_ekle')
                    ->label('Gider-Masraf Ekle')
                    ->icon('heroicon-o-document-plus')
                    ->button()
                    ->color('gray')
                    ->modalHeading('Gider-Masraf Ekle')
                    ->modalWidth('7xl')
                    ->mountUsing(function (Forms\ComponentContainer $form): void {
                        $form->fill(ServisGiderFaturasiDestegi::varsayilanFormData($this->getOwnerRecord()));
                    })
                    ->form(fn (): array => ServisGiderFaturasiDestegi::formSchema($this->getOwnerRecord()))
                    ->action(function (array $data): void {
                        try {
                            $fatura = ServisGiderFaturasiDestegi::kaydet($this->getOwnerRecord(), $data);
                            Notification::make()
                                ->title('Gider faturası kaydedildi')
                                ->body('Fatura: '.((string) ($fatura->fatura_no ?: '#'.$fatura->getKey())))
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Gider faturası kaydedilemedi')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('tahsilat_ekle')
                    ->label('Tahsilat Ekle')
                    ->icon('heroicon-o-plus')
                    ->button()
                    ->color('success')
                    ->form($this->tahsilatFormu())
                    ->action(function (array $data): void {
                        try {
                            $sonuc = app(TeknikServisTahsilatServisi::class)->olustur($this->getOwnerRecord(), $data);
                            Notification::make()
                                ->title(in_array((string) ($data['kanal'] ?? ''), ['veresiye', 'taksitli'], true) ? 'Ödeme planı oluşturuldu' : 'Tahsilat kaydedildi')
                                ->body(in_array((string) ($data['kanal'] ?? ''), ['veresiye', 'taksitli'], true) ? 'Plan #'.(int) $sonuc->getKey().' Finans > Vade Takibi ekranına eklendi.' : null)
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Tahsilat kaydedilemedi')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
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
                            Notification::make()->title('Tahsilat iptal edildi')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Tahsilat iptal edilemedi')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('tarih', 'desc')
            ->deferLoading()
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    /** @return array<int, Forms\Components\Component> */
    private function servisMasrafFormu(): array
    {
        $firmaId = (int) $this->getOwnerRecord()->firma_id;
        MasrafKategorisi::varsayilanlariHazirla($firmaId);

        return [
            Forms\Components\DatePicker::make('tarih')
                ->label('Masraf tarihi')
                ->required()
                ->native(false)
                ->default(now()->toDateString()),
            Forms\Components\Select::make('masraf_kategorisi_id')
                ->label('Masraf türü')
                ->options(fn (): array => MasrafKategorisi::query()
                    ->where('firma_id', $firmaId)
                    ->where('aktif_mi', true)
                    ->where('secilir_mi', true)
                    ->with('ustKategori:id,ad')
                    ->orderBy('sira')
                    ->orderBy('ad')
                    ->get()
                    ->mapWithKeys(fn (MasrafKategorisi $kategori): array => [
                        (string) $kategori->getKey() => ($kategori->ustKategori?->ad ? $kategori->ustKategori->ad.' / ' : '').$kategori->ad,
                    ])
                    ->all())
                ->searchable()
                ->native(false)
                ->required()
                ->helperText('Masraf raporlarında kullanılacak alt türü seçin.'),
            Forms\Components\TextInput::make('tutar')
                ->label('Tutar')
                ->required()
                ->numeric()
                ->minValue(0.01)
                ->step('0.01')
                ->inputMode('decimal'),
            Forms\Components\Select::make('para_birimi')
                ->label('Para birimi')
                ->options(['TRY' => '₺ Türk Lirası', 'USD' => '$ Amerikan Doları', 'EUR' => '€ Euro', 'GBP' => '£ İngiliz Sterlini'])
                ->required()
                ->native(false),
            Forms\Components\TextInput::make('aciklama')
                ->label('Kısa açıklama')
                ->placeholder('Örn. Servis yol gideri veya küçük malzeme')
                ->required()
                ->maxLength(191)
                ->columnSpan(2),
            Forms\Components\Textarea::make('notlar')
                ->label('Not (isteğe bağlı)')
                ->rows(3)
                ->maxLength(2000)
                ->columnSpan(2),
            Forms\Components\FileUpload::make('belge_yolu')
                ->label('Belge / fiş (isteğe bağlı)')
                ->helperText('PDF, JPG veya PNG; en fazla 10 MB.')
                ->disk('public')
                ->directory('masraflar/'.($firmaId ?: 0))
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                ->maxSize(10240)
                ->storeFileNamesIn('belge_adi')
                ->columnSpan(2),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private function tahsilatFormu(): array
    {
        return TeknikServisTahsilatFormu::schema($this->getOwnerRecord());
    }

    private function kaynakParaBirimi(): string
    {
        return $this->kaynakParaBirimiCache ??= strtoupper((string) ($this->getOwnerRecord()->cari?->para_birimi ?: 'TRY'));
    }

    private function aktifTahsilatToplami(): float
    {
        return $this->aktifTahsilatToplamiCache ??= (float) $this->getRelationship()->getQuery()
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

        return $this->servisToplamiCache = (float) ($this->getOwnerRecord()->toplam_tutar ?? 0);
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

        $servis = $this->getOwnerRecord();
        $baglanti = TeknikServisMuhasebeBaglantisi::query()
            ->select(['id', 'satis_faturasi_id'])
            ->where('firma_id', (int) ($servis->firma_id ?? 0))
            ->where('teknik_servis_kaydi_id', (int) $servis->getKey())
            ->where('islem_tipi', 'satis')
            ->whereNotNull('satis_faturasi_id')
            ->orderByDesc('id')
            ->first();

        if ($baglanti?->satis_faturasi_id) {
            $this->bagliFaturaCache = Fatura::query()
                ->whereKey((int) $baglanti->satis_faturasi_id)
                ->first(['id', 'fatura_no', 'durum', 'odenecek_tutar', 'genel_toplam', 'acik_tutar', 'para_birimi']);
            $this->bagliFaturaCacheHazir = true;

            return $this->bagliFaturaCache;
        }

        $this->bagliFaturaCacheHazir = true;

        return $this->bagliFaturaCache = null;
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
