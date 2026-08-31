<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\BarkodluSatis\Guvenlik\BarkodluSatisFilamentErisimYardimcisi;
use App\Filament\Clusters\Muhasebe as MuhasebeCluster;
use App\Models\Muhasebe\BankaHesabi;
use App\Models\Muhasebe\BarkodluSatis;
use App\Models\Muhasebe\BarkodluSatisKalemi;
use App\Models\Muhasebe\BarkodluSatisIade;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\PosHesabi;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Servisler\BarkodluSatisAlacakOzetServisi;
use App\Muhasebe\Servisler\BarkodluSatisServisi;
use App\Services\TenantContextService;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BarkodluSatisGecmisiSayfasi extends Page implements HasTable
{
    use InteractsWithTable;

    private const PERAKENDE_CARI_KOD = 'PERAKENDE-MUSTERI';

    /** @var array<int, array<string, mixed>> */
    private array $alacakOzetiOnbellek = [];

    private bool $aktifFirmaIdCozuldu = false;

    private ?int $aktifFirmaIdCache = null;

    /** @var array<string, string>|null */
    private ?array $kaynakHesapSecenekleriCache = null;

    protected static ?string $cluster = MuhasebeCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Barkodlu satis gecmisi';

    protected static ?string $slug = 'satis/barkodlu-satis-gecmisi';

    protected static string $view = 'filament.clusters.muhasebe.pages.barkodlu-satis-gecmisi-sayfasi';

    public function getHeading(): string|Htmlable
    {
        return 'Barkodlu satis gecmisi';
    }

    public function getSubheading(): ?string
    {
        return 'Tamamlanan ve iptal edilen satislari tek ekranda takip edin.';
    }

    public static function canAccess(): bool
    {
        return BarkodluSatisFilamentErisimYardimcisi::herhangiBirBarkodluSatisYetkisiVarMi([
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_GORUNTULE,
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_GUNCELLE,
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_IPTAL,
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_IADE,
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                BarkodluSatis::query()
                    ->select([
                        'id',
                        'firma_id',
                        'satis_no',
                        'satis_tarihi',
                        'cari_id',
                        'para_birimi',
                        'genel_toplam',
                        'durum',
                        'olusturan_id',
                        'iptal_eden_id',
                    ])
                    ->withCount('kalemler')
                    ->withSum('iadeler as iade_toplami', 'toplam_iade_tutari')
                    ->with([
                        'kalemler:id,satis_id,stok_adi,miktar,seri_nolari',
                        'cari:id,firma_id,kod,ad,para_birimi',
                        'olusturan:id,name',
                        'iptalEden:id,name',
                    ])
            )
            ->headerActions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('hizli_perakende')
                        ->label('Tüm Perakende')
                        ->icon('heroicon-o-funnel')
                        ->url(fn (): string => $this->filtreUrlOlustur(musteriTipi: 'perakende')),
                    Tables\Actions\Action::make('perakende_bu_ay')
                        ->label('Bu Ay')
                        ->url(fn (): string => $this->filtreUrlOlustur(
                            baslangic: now()->startOfMonth()->toDateString(),
                            bitis: now()->endOfMonth()->toDateString(),
                            musteriTipi: 'perakende'
                        )),
                    Tables\Actions\Action::make('perakende_bugun')
                        ->label('Bugün')
                        ->url(fn (): string => $this->filtreUrlOlustur(
                            baslangic: now()->toDateString(),
                            bitis: now()->toDateString(),
                            musteriTipi: 'perakende'
                        )),
                    Tables\Actions\Action::make('perakende_son_30_gun')
                        ->label('Son 30 Gün')
                        ->url(fn (): string => $this->filtreUrlOlustur(
                            baslangic: now()->subDays(29)->toDateString(),
                            bitis: now()->toDateString(),
                            musteriTipi: 'perakende'
                        )),
                    Tables\Actions\Action::make('perakende_gecen_ay')
                        ->label('Geçen Ay')
                        ->url(fn (): string => $this->filtreUrlOlustur(
                            baslangic: now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                            bitis: now()->subMonthNoOverflow()->endOfMonth()->toDateString(),
                            musteriTipi: 'perakende'
                        )),
                ])
                    ->label('Perakende')
                    ->icon('heroicon-o-user-group')
                    ->color('warning')
                    ->button(),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('bugun')
                        ->label('Bugün')
                        ->icon('heroicon-o-calendar-days')
                        ->url(fn (): string => $this->filtreUrlOlustur(
                            baslangic: now()->toDateString(),
                            bitis: now()->toDateString()
                        )),
                    Tables\Actions\Action::make('son_7_gun')
                        ->label('Son 7 Gün')
                        ->url(fn (): string => $this->filtreUrlOlustur(
                            baslangic: now()->subDays(6)->toDateString(),
                            bitis: now()->toDateString()
                        )),
                    Tables\Actions\Action::make('son_30_gun')
                        ->label('Son 30 Gün')
                        ->url(fn (): string => $this->filtreUrlOlustur(
                            baslangic: now()->subDays(29)->toDateString(),
                            bitis: now()->toDateString()
                        )),
                    Tables\Actions\Action::make('bu_ay')
                        ->label('Bu Ay')
                        ->url(fn (): string => $this->filtreUrlOlustur(
                            baslangic: now()->startOfMonth()->toDateString(),
                            bitis: now()->endOfMonth()->toDateString()
                        )),
                    Tables\Actions\Action::make('gecen_ay')
                        ->label('Geçen Ay')
                        ->url(fn (): string => $this->filtreUrlOlustur(
                            baslangic: now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                            bitis: now()->subMonthNoOverflow()->endOfMonth()->toDateString()
                        )),
                    Tables\Actions\Action::make('bu_yil')
                        ->label('Bu Yıl')
                        ->url(fn (): string => $this->filtreUrlOlustur(
                            baslangic: now()->startOfYear()->toDateString(),
                            bitis: now()->endOfYear()->toDateString()
                        )),
                ])
                    ->label('Tarih')
                    ->icon('heroicon-o-calendar-days')
                    ->color('info')
                    ->button(),
                Tables\Actions\Action::make('filtre_temizle')
                    ->label('Filtreleri Temizle')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->url(fn (): string => static::getUrl()),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('export_csv')
                        ->label('CSV Dışa Aktar')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(fn (): StreamedResponse => $this->satisGecmisiCsvIndir(false)),
                    Tables\Actions\Action::make('export_excel_csv')
                        ->label('Excel Uyumlu CSV')
                        ->icon('heroicon-o-document-chart-bar')
                        ->color('success')
                        ->action(fn (): StreamedResponse => $this->satisGecmisiCsvIndir(true)),
                ])
                    ->label('Dışa Aktar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->button(),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('satis_no')
                    ->label('Satis no')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('satis_tarihi')
                    ->label('Tarih')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('cari.ad')
                    ->label('Cari')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('musteri_tipi')
                    ->label('Musteri tipi')
                    ->badge()
                    ->state(fn (BarkodluSatis $record): string => $this->musteriTipiEtiketi($record))
                    ->color(fn (BarkodluSatis $record): string => $this->musteriTipiRenk($record)),
                Tables\Columns\TextColumn::make('kalem_sayisi')
                    ->label('Kalem')
                    ->state(fn (BarkodluSatis $record): int => (int) ($record->kalemler_count ?? 0)),
                Tables\Columns\TextColumn::make('seri_izleme')
                    ->label('Seri No Barkodu')
                    ->state(fn (BarkodluSatis $record): string => $this->satisIzlemeEtiketi($record))
                    ->wrap()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('genel_toplam')
                    ->label('Toplam')
                    ->money(fn (BarkodluSatis $record): string => strtoupper((string) ($record->para_birimi ?: 'TRY')))
                    ->sortable(),
                Tables\Columns\TextColumn::make('tahsilat_durumu')
                    ->label('Tahsilat')
                    ->badge()
                    ->state(fn (BarkodluSatis $record): string => $this->tahsilatDurumuEtiketi($record))
                    ->color(fn (BarkodluSatis $record): string => $this->tahsilatDurumuRenk($record)),
                Tables\Columns\TextColumn::make('alacak_plani')
                    ->label('Plan / Kalan')
                    ->badge()
                    ->state(fn (BarkodluSatis $record): string => $this->planDurumuEtiketi($record))
                    ->description(fn (BarkodluSatis $record): string => $this->planKalanAciklama($record))
                    ->color(fn (BarkodluSatis $record): string => $this->planDurumuRenk($record)),
                Tables\Columns\TextColumn::make('iade_toplami')
                    ->label('Iade')
                    ->state(fn (BarkodluSatis $record): float => (float) ($record->iade_toplami ?? 0))
                    ->money(fn (BarkodluSatis $record): string => strtoupper((string) ($record->para_birimi ?: 'TRY'))),
                Tables\Columns\TextColumn::make('durum')
                    ->label('Durum')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'iptal' ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('kaynak_hesap')
                    ->label('Kaynak Hesap')
                    ->state(fn (BarkodluSatis $record): string => $this->kaynakHesapEtiketi($record))
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('olusturan.name')
                    ->label('Olusturan')
                    ->placeholder('-'),
            ])
            ->defaultSort('satis_tarihi', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('durum')
                    ->label('Durum')
                    ->options([
                        'tamamlandi' => 'Tamamlandi',
                        'iptal' => 'Iptal',
                    ]),
                Tables\Filters\SelectFilter::make('musteri_tipi')
                    ->label('Musteri tipi')
                    ->options([
                        'perakende' => 'Perakende',
                        'cari' => 'Cari',
                    ])
                    ->query(function ($query, array $data) {
                        $deger = trim((string) ($data['value'] ?? ''));
                        if ($deger === '') {
                            return $query;
                        }

                        if ($deger === 'perakende') {
                            return $query->whereHas('cari', function ($cariQuery): void {
                                $cariQuery->where('kod', self::PERAKENDE_CARI_KOD);
                            });
                        }

                        return $query->where(function ($ana): void {
                            $ana->whereNull('cari_id')
                                ->orWhereDoesntHave('cari', function ($cariQuery): void {
                                    $cariQuery->where('kod', self::PERAKENDE_CARI_KOD);
                                });
                        });
                    }),
                Tables\Filters\Filter::make('tarih_araligi')
                    ->label('Tarih araligi')
                    ->form([
                        Forms\Components\DatePicker::make('baslangic')
                            ->label('Baslangic'),
                        Forms\Components\DatePicker::make('bitis')
                            ->label('Bitis'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $baslangic = $this->normalizeTarihDegeri($data['baslangic'] ?? null);
                        $bitis = $this->normalizeTarihDegeri($data['bitis'] ?? null);

                        return $query
                            ->when($baslangic !== null, fn (Builder $q): Builder => $q->where('satis_tarihi', '>=', $baslangic.' 00:00:00'))
                            ->when($bitis !== null, fn (Builder $q): Builder => $q->where('satis_tarihi', '<=', $bitis.' 23:59:59'));
                    }),
                Tables\Filters\Filter::make('seri_no_barkodu')
                    ->label('Seri No Barkodu')
                    ->form([
                        Forms\Components\TextInput::make('deger')
                            ->label('Seri No Barkodu')
                            ->placeholder('Seri numarası veya barkod'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $deger = trim((string) ($data['deger'] ?? ''));
                        if ($deger === '') {
                            return $query;
                        }

                        return $query->whereHas('kalemler', fn (Builder $kalemQuery): Builder => $kalemQuery
                            ->where('seri_nolari', 'like', '%'.$deger.'%'));
                    }),
                Tables\Filters\SelectFilter::make('kaynak_hesap')
                    ->label('Kaynak hesap')
                    ->options([
                        'kasa' => 'Kasa',
                        'banka' => 'Banka',
                        'pos' => 'POS',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $deger = trim((string) ($data['value'] ?? ''));
                        if ($deger === '') {
                            return $query;
                        }

                        return match ($deger) {
                            'kasa' => $query->whereHas('finansHareketleri', fn (Builder $f): Builder => $f->whereHas('kasaHareketleri')),
                            'banka' => $query->whereHas('finansHareketleri', fn (Builder $f): Builder => $f->whereHas('bankaHareketleri')),
                            'pos' => $query->whereHas('finansHareketleri', fn (Builder $f): Builder => $f->whereHas('posHareketleri')),
                            default => $query,
                        };
                    }),
                Tables\Filters\SelectFilter::make('kaynak_hesap_id')
                    ->label('Hesap')
                    ->searchable()
                    ->options(fn (): array => $this->kaynakHesapSecenekleri())
                    ->query(function (Builder $query, array $data): Builder {
                        $deger = trim((string) ($data['value'] ?? ''));
                        if ($deger === '' || ! str_contains($deger, ':')) {
                            return $query;
                        }

                        [$tip, $id] = array_pad(explode(':', $deger, 2), 2, '');
                        $tip = strtolower(trim($tip));
                        $hesapId = (int) $id;
                        if ($hesapId < 1) {
                            return $query;
                        }

                        return match ($tip) {
                            'kasa' => $query->whereHas('finansHareketleri', fn (Builder $f): Builder => $f->whereHas('kasaHareketleri', fn (Builder $kh): Builder => $kh->where('kasa_hesap_id', $hesapId))),
                            'banka' => $query->whereHas('finansHareketleri', fn (Builder $f): Builder => $f->whereHas('bankaHareketleri', fn (Builder $bh): Builder => $bh->where('banka_hesap_id', $hesapId))),
                            'pos' => $query->whereHas('finansHareketleri', fn (Builder $f): Builder => $f->whereHas('posHareketleri', fn (Builder $ph): Builder => $ph->where('pos_hesap_id', $hesapId))),
                            default => $query,
                        };
                    }),
                Tables\Filters\SelectFilter::make('tahsilat_durumu')
                    ->label('Tahsilat durumu')
                    ->options([
                        'tam' => 'Tam',
                        'planli_acik' => 'Planlı Açık',
                        'plansiz_acik' => 'Plansız Açık',
                        'eksik' => 'Eksik',
                        'yok' => 'Yok',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $deger = trim((string) ($data['value'] ?? ''));
                        if ($deger === '') {
                            return $query;
                        }

                        $aktifTahsilatSql = "(SELECT COALESCE(SUM(fh.tutar), 0) FROM finans_hareketleri fh WHERE fh.referans_turu = 'barkodlu_satis' AND fh.referans_id = muhasebe_barkodlu_satislar.id AND fh.durum = 'aktif')";
                        $genelToplamSql = "COALESCE(muhasebe_barkodlu_satislar.genel_toplam, 0)";

                        return match ($deger) {
                            'yok' => $query->whereRaw($aktifTahsilatSql.' <= 0')
                                ->whereDoesntHave('alacakPlanlari', fn (Builder $plan): Builder => $plan->whereIn('durum', ['aktif', 'kismi_odendi', 'gecikti', 'odendi'])),
                            'tam' => $query->where(function (Builder $ana) use ($aktifTahsilatSql, $genelToplamSql): void {
                                $ana->whereRaw($aktifTahsilatSql.' >= '.$genelToplamSql)
                                    ->orWhereHas('alacakPlanlari', fn (Builder $plan): Builder => $plan->whereIn('durum', ['odendi'])->where('kalan_tutar', '<=', 0));
                            }),
                            'planli_acik' => $query->whereHas('alacakPlanlari', fn (Builder $plan): Builder => $plan->whereIn('durum', ['aktif', 'kismi_odendi', 'gecikti'])->where('kalan_tutar', '>', 0)),
                            'plansiz_acik' => $query->whereDoesntHave('alacakPlanlari', fn (Builder $plan): Builder => $plan->whereIn('durum', ['aktif', 'kismi_odendi', 'gecikti', 'odendi']))
                                ->whereRaw($aktifTahsilatSql.' < '.$genelToplamSql),
                            'eksik' => $query->where(function (Builder $ana) use ($aktifTahsilatSql, $genelToplamSql): void {
                                $ana->where(function (Builder $q) use ($aktifTahsilatSql, $genelToplamSql): void {
                                    $q->whereRaw($aktifTahsilatSql.' > 0')->whereRaw($aktifTahsilatSql.' < '.$genelToplamSql);
                                })->orWhereHas('alacakPlanlari', fn (Builder $plan): Builder => $plan->whereIn('durum', ['aktif', 'kismi_odendi', 'gecikti'])->where('kalan_tutar', '>', 0));
                            }),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('tahsilat_al')
                    ->label('Tahsilat Al')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (BarkodluSatis $record): bool => $this->tahsilatAksiyonuGorunurMu($record))
                    ->url(fn (BarkodluSatis $record): string => $this->tahsilatUrl($record)),
                Tables\Actions\Action::make('fis')
                    ->label('Satis Fisi')
                    ->icon('heroicon-o-printer')
                    ->url(fn (BarkodluSatis $record): string => BarkodluSatisFisiSayfasi::getUrl([
                        'satis' => (int) $record->id,
                    ]))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('detay')
                    ->label('Detay')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (BarkodluSatis $record): string => 'Satis Detayi #'.$record->satis_no)
                    ->modalWidth('7xl')
                    ->modalContent(function (BarkodluSatis $record) {
                        return view('filament.clusters.muhasebe.pages.partials.barkodlu-satis-detay', [
                            'satis' => $record->fresh([
                                'cari',
                                'kalemler',
                                'finansHareketleri.kasaHareketleri.kasaHesabi',
                                'finansHareketleri.bankaHareketleri.bankaHesabi',
                                'finansHareketleri.posHareketleri.posHesabi',
                            ]) ?? $record,
                            'ozet' => $this->alacakOzeti($record),
                            'vadeTakipUrl' => VadeTakipSayfasi::getUrl(),
                        ]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Kapat'),
                Tables\Actions\Action::make('vade_takibi')
                    ->label('Vade Takibi')
                    ->icon('heroicon-o-calendar-days')
                    ->color('warning')
                    ->visible(fn (BarkodluSatis $record): bool => (bool) ($this->alacakOzeti($record)['plan'] ?? null))
                    ->url(fn (): string => VadeTakipSayfasi::getUrl()),
                Tables\Actions\Action::make('iptal')
                    ->label('Satisi Iptal Et')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        \Filament\Forms\Components\Textarea::make('iptal_nedeni')
                            ->label('Iptal nedeni')
                            ->rows(3)
                            ->required()
                            ->maxLength(1000),
                    ])
                    ->visible(fn (BarkodluSatis $record): bool => $record->durum !== 'iptal' && $this->iptalYetkisiVarMi())
                    ->action(function (BarkodluSatis $record, array $data): void {
                        try {
                            app(BarkodluSatisServisi::class)->satisIptalEt(
                                firmaId: (int) $record->firma_id,
                                satisId: (int) $record->id,
                                kullaniciId: (int) auth()->id(),
                                iptalNedeni: (string) ($data['iptal_nedeni'] ?? ''),
                            );

                            Notification::make()
                                ->title('Satis iptal edildi')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Iptal islemi basarisiz')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('iade')
                    ->label('Iade Al')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->form(fn (BarkodluSatis $record): array => [
                        Forms\Components\Select::make('satis_kalem_id')
                            ->label('Satis kalemi')
                            ->options($this->iadeEdilebilirKalemSecenekleri($record))
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('iade_miktari')
                            ->label('Iade miktari')
                            ->numeric()
                            ->minValue(0.0001)
                            ->step('0.0001')
                            ->required(),
                        Forms\Components\TextInput::make('seri_no_barkodu')
                            ->label('Seri No Barkodu')
                            ->placeholder('Seri takipli üründe barkodu okutun; tam iadede boş bırakabilirsiniz.')
                            ->helperText('Seri takipli üründe kısmi iade yapıyorsanız iade edilecek seri barkodunu girin.'),
                        Forms\Components\Textarea::make('neden')
                            ->label('Iade nedeni')
                            ->rows(2)
                            ->required()
                            ->maxLength(1000),
                    ])
                    ->visible(fn (BarkodluSatis $record): bool => $record->durum !== 'iptal' && $this->iadeYetkisiVarMi())
                    ->action(function (BarkodluSatis $record, array $data): void {
                        try {
                            app(BarkodluSatisServisi::class)->satisKalemiIadeEt(
                                firmaId: (int) $record->firma_id,
                                satisId: (int) $record->id,
                                satisKalemId: (int) ($data['satis_kalem_id'] ?? 0),
                                iadeMiktari: (float) ($data['iade_miktari'] ?? 0),
                                kullaniciId: (int) auth()->id(),
                                neden: (string) ($data['neden'] ?? ''),
                                seriNoBarkodu: (string) ($data['seri_no_barkodu'] ?? ''),
                            );

                            Notification::make()
                                ->title('Iade kaydi olusturuldu')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Iade islemi basarisiz')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->deferLoading()
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    public function satisGecmisiCsvIndir(bool $excelUyumlu = false): StreamedResponse
    {
        $sorgu = $this->getFilteredSortedTableQuery()->clone()->with(['cari', 'olusturan', 'kalemler', 'iadeler']);
        $delimiter = $excelUyumlu ? ';' : ',';
        $dosyaAdi = 'barkodlu-satis-gecmisi-'.now()->format('Ymd_His').($excelUyumlu ? '-excel' : '').'.csv';

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
            fputcsv($out, ['Satis No', 'Tarih', 'Cari', 'Musteri Tipi', 'Kalem', 'Seri No Barkodu', 'Toplam', 'Tahsilat Durumu', 'Plan Durumu', 'Plan Bakiyesi', 'Finansal Acik', 'Iade', 'Durum', 'Kaynak Hesap', 'Olusturan'], $delimiter);

            /** @var BarkodluSatis $kayit */
            foreach ($sorgu->cursor() as $kayit) {
                $ozet = $this->alacakOzeti($kayit);
                fputcsv($out, [
                    (string) $kayit->satis_no,
                    optional($kayit->satis_tarihi)->format('d.m.Y H:i') ?: '',
                    (string) ($kayit->cari?->ad ?? ''),
                    $this->musteriTipiEtiketi($kayit),
                    (string) $kayit->kalemler->count(),
                    $this->satisSeriEtiketi($kayit),
                    number_format((float) $kayit->genel_toplam, 2, ',', '.'),
                    $this->tahsilatDurumuEtiketi($kayit),
                    $this->planDurumuEtiketi($kayit),
                    number_format((float) ($ozet['plan_kalan_tutar'] ?? 0), 2, ',', '.'),
                    number_format((float) ($ozet['finansal_acik_tutar'] ?? 0), 2, ',', '.'),
                    number_format((float) $kayit->iadeler->sum('toplam_iade_tutari'), 2, ',', '.'),
                    (string) $kayit->durum,
                    $this->kaynakHesapEtiketi($kayit),
                    (string) ($kayit->olusturan?->name ?? ''),
                ], $delimiter);
            }

            fclose($out);
        }, $dosyaAdi, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function iptalYetkisiVarMi(): bool
    {
        return BarkodluSatisFilamentErisimYardimcisi::barkodluSatisYetkisiVarMi(
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_IPTAL
        );
    }

    private function iadeYetkisiVarMi(): bool
    {
        return BarkodluSatisFilamentErisimYardimcisi::barkodluSatisYetkisiVarMi(
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_IADE
        );
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function csvFiltreOzetSatirlari(): array
    {
        $satirlar = [];
        $satirlar[] = ['Rapor', 'Barkodlu Satis Gecmisi'];
        $satirlar[] = ['Olusturulma', now()->format('d.m.Y H:i:s')];

        $arama = trim((string) ($this->getTableSearch() ?? ''));
        $satirlar[] = ['Global Arama', $arama !== '' ? $arama : '-'];

        $siralaKolon = (string) ($this->getTableSortColumn() ?? '');
        $siralaYon = (string) ($this->getTableSortDirection() ?? '');
        $sirala = $siralaKolon !== '' ? $siralaKolon.' '.($siralaYon !== '' ? $siralaYon : 'asc') : 'varsayilan';
        $satirlar[] = ['Siralama', $sirala];

        $durumHam = $this->getTableFilterState('durum');
        $durum = is_array($durumHam) ? Arr::get($durumHam, 'value') : $durumHam;
        $musteriTipiHam = $this->getTableFilterState('musteri_tipi');
        $musteriTipi = is_array($musteriTipiHam) ? Arr::get($musteriTipiHam, 'value') : $musteriTipiHam;
        $kaynakHesapHam = $this->getTableFilterState('kaynak_hesap');
        $kaynakHesap = is_array($kaynakHesapHam) ? Arr::get($kaynakHesapHam, 'value') : $kaynakHesapHam;
        $hesapHam = $this->getTableFilterState('kaynak_hesap_id');
        $hesap = is_array($hesapHam) ? Arr::get($hesapHam, 'value') : $hesapHam;
        $tahsilatDurumuHam = $this->getTableFilterState('tahsilat_durumu');
        $tahsilatDurumu = is_array($tahsilatDurumuHam) ? Arr::get($tahsilatDurumuHam, 'value') : $tahsilatDurumuHam;
        $seriHam = $this->getTableFilterState('seri_no_barkodu');
        $seriDegeri = is_array($seriHam) ? Arr::get($seriHam, 'deger') : $seriHam;
        [$tarihBaslangic, $tarihBitis] = $this->aktifTarihAraligi();

        $satirlar[] = ['Filtre Durum', $this->durumEtiketi($durum)];
        $satirlar[] = ['Filtre Musteri Tipi', $this->musteriTipiFiltreEtiketi($musteriTipi)];
        $satirlar[] = ['Filtre Kaynak Hesap', $this->kaynakHesapFiltreEtiketi($kaynakHesap)];
        $satirlar[] = ['Filtre Hesap', $this->hesapFiltreEtiketi($hesap)];
        $satirlar[] = ['Filtre Tahsilat Durumu', $this->tahsilatDurumuFiltreEtiketi($tahsilatDurumu)];
        $satirlar[] = ['Filtre Seri No Barkodu', trim((string) ($seriDegeri ?? '')) !== '' ? trim((string) $seriDegeri) : 'Hepsi'];
        $satirlar[] = ['Filtre Tarih Araligi', $this->tarihAraligiEtiketi($tarihBaslangic, $tarihBitis)];

        return $satirlar;
    }

    private function durumEtiketi(mixed $durum): string
    {
        $deger = trim((string) ($durum ?? ''));
        if ($deger === '') {
            return 'Hepsi';
        }

        return match ($deger) {
            'tamamlandi' => 'Tamamlandi',
            'iptal' => 'Iptal',
            default => $deger,
        };
    }

    private function musteriTipiFiltreEtiketi(mixed $musteriTipi): string
    {
        $deger = trim((string) ($musteriTipi ?? ''));
        if ($deger === '') {
            return 'Hepsi';
        }

        return match ($deger) {
            'perakende' => 'Perakende',
            'cari' => 'Cari',
            default => $deger,
        };
    }

    private function kaynakHesapFiltreEtiketi(mixed $kaynakHesap): string
    {
        $deger = trim((string) ($kaynakHesap ?? ''));
        if ($deger === '') {
            return 'Hepsi';
        }

        return match ($deger) {
            'kasa' => 'Kasa',
            'banka' => 'Banka',
            'pos' => 'POS',
            default => $deger,
        };
    }

    private function hesapFiltreEtiketi(mixed $hesap): string
    {
        $deger = trim((string) ($hesap ?? ''));
        if ($deger === '') {
            return 'Hepsi';
        }

        return $this->kaynakHesapSecenekleri()[$deger] ?? $deger;
    }

    private function tahsilatDurumuFiltreEtiketi(mixed $durum): string
    {
        $deger = trim((string) ($durum ?? ''));
        if ($deger === '') {
            return 'Hepsi';
        }

        return match ($deger) {
            'tam' => 'Tam',
            'planli_acik' => 'Planlı Açık',
            'plansiz_acik' => 'Plansız Açık',
            'eksik' => 'Eksik',
            'yok' => 'Yok',
            default => $deger,
        };
    }

    private function musteriTipiEtiketi(BarkodluSatis $kayit): string
    {
        $cariKod = strtoupper(trim((string) ($kayit->cari?->kod ?? '')));

        if ($cariKod === self::PERAKENDE_CARI_KOD) {
            return 'Perakende';
        }

        if ((int) ($kayit->cari_id ?? 0) > 0) {
            return 'Cari';
        }

        return 'Belirsiz';
    }

    private function satisSeriEtiketi(BarkodluSatis $kayit): string
    {
        $seriler = [];

        foreach ($kayit->kalemler as $kalem) {
            foreach ((array) ($kalem->seri_nolari ?? []) as $seri) {
                $seri = trim((string) $seri);
                if ($seri !== '') {
                    $seriler[] = $seri;
                }
            }
        }

        return implode(', ', array_values(array_unique($seriler)));
    }

    private function satisIzlemeEtiketi(BarkodluSatis $kayit): string
    {
        $seri = $this->satisSeriEtiketi($kayit);

        return $seri !== '' ? 'Seri: '.$seri : '';
    }

    private function musteriTipiRenk(BarkodluSatis $kayit): string
    {
        return match ($this->musteriTipiEtiketi($kayit)) {
            'Perakende' => 'warning',
            'Cari' => 'success',
            default => 'gray',
        };
    }

    private function kaynakHesapEtiketi(BarkodluSatis $kayit): string
    {
        $finans = $kayit->finansHareketleri
            ->sortByDesc('id')
            ->first(function ($hareket): bool {
                $durum = (string) ($hareket->durum?->value ?? $hareket->durum ?? '');

                return $durum === 'aktif';
            });

        if (! $finans) {
            return '-';
        }

        $kasaHareket = $finans->kasaHareketleri->sortByDesc('id')->first();
        if ($kasaHareket) {
            $hesapAd = trim((string) ($kasaHareket->kasaHesabi?->ad ?? ''));

            return $hesapAd !== '' ? 'Kasa - '.$hesapAd : 'Kasa';
        }

        $bankaHareket = $finans->bankaHareketleri->sortByDesc('id')->first();
        if ($bankaHareket) {
            $hesapAd = trim((string) ($bankaHareket->bankaHesabi?->ad ?? ''));

            return $hesapAd !== '' ? 'Banka - '.$hesapAd : 'Banka';
        }

        $posHareket = $finans->posHareketleri->sortByDesc('id')->first();
        if ($posHareket) {
            $hesapAd = trim((string) ($posHareket->posHesabi?->ad ?? ''));

            return $hesapAd !== '' ? 'POS - '.$hesapAd : 'POS';
        }

        return '-';
    }

    private function tahsilatDurumuEtiketi(BarkodluSatis $kayit): string
    {
        return app(BarkodluSatisAlacakOzetServisi::class)->tahsilatDurumuEtiketi($kayit);
    }

    private function tahsilatDurumuRenk(BarkodluSatis $kayit): string
    {
        return app(BarkodluSatisAlacakOzetServisi::class)->tahsilatDurumuRenk($kayit);
    }

    private function planDurumuEtiketi(BarkodluSatis $kayit): string
    {
        $ozet = $this->alacakOzeti($kayit);
        $plan = $ozet['plan'] ?? null;

        if (! $plan) {
            return (float) ($ozet['finansal_acik_tutar'] ?? 0) > 0.009 ? 'Plansız' : 'Yok';
        }

        return '#'.(int) $plan->id.' '.ucfirst(str_replace('_', ' ', (string) $plan->durum));
    }

    private function planDurumuRenk(BarkodluSatis $kayit): string
    {
        return match ((string) ($this->alacakOzeti($kayit)['durum'] ?? 'kapali')) {
            'kapali' => 'success',
            'planli_acik' => 'warning',
            'plansiz_acik', 'acik' => 'danger',
            default => 'gray',
        };
    }

    private function planKalanAciklama(BarkodluSatis $kayit): string
    {
        $ozet = $this->alacakOzeti($kayit);
        $paraBirimi = strtoupper((string) ($ozet['para_birimi'] ?? $kayit->para_birimi ?? 'TRY'));

        if ($ozet['plan'] ?? null) {
            return 'Kalan: '.number_format((float) ($ozet['plan_kalan_tutar'] ?? 0), 2, ',', '.').' '.$paraBirimi;
        }

        return 'Açık: '.number_format((float) ($ozet['finansal_acik_tutar'] ?? 0), 2, ',', '.').' '.$paraBirimi;
    }

    private function tahsilatAksiyonuGorunurMu(BarkodluSatis $kayit): bool
    {
        $ozet = $this->alacakOzeti($kayit);

        return (int) ($kayit->cari_id ?? 0) > 0
            && (float) ($ozet['finansal_acik_tutar'] ?? 0) > 0.009
            && (string) ($kayit->durum ?? '') !== 'iptal';
    }

    private function tahsilatUrl(BarkodluSatis $kayit): string
    {
        $ozet = $this->alacakOzeti($kayit);
        $ilkAcikTaksit = $ozet['ilk_acik_taksit'] ?? null;
        if ($ilkAcikTaksit) {
            return TahsilatOlusturSayfasi::getUrl().'?'.http_build_query([
                'alacak_plan_taksiti_id' => (int) $ilkAcikTaksit->id,
            ]);
        }

        return TahsilatOlusturSayfasi::getUrl().'?'.http_build_query([
            'cari_id' => (int) ($kayit->cari_id ?? 0),
            'tutar' => number_format((float) ($ozet['finansal_acik_tutar'] ?? 0), 2, '.', ''),
            'kaynak_para_birimi' => strtoupper((string) ($ozet['para_birimi'] ?? $kayit->para_birimi ?? 'TRY')),
            'aciklama' => 'Barkodlu satış #'.(string) ($kayit->satis_no ?: $kayit->id).' tahsilatı',
        ]);
    }

    private function aktifTahsilatToplami(BarkodluSatis $kayit): float
    {
        return (float) $kayit->finansHareketleri
            ->filter(function ($hareket): bool {
                $durum = (string) ($hareket->durum?->value ?? $hareket->durum ?? '');

                return $durum === 'aktif';
            })
            ->sum(fn ($hareket): float => (float) ($hareket->tutar ?? 0));
    }

    private function finansOzetMetni(BarkodluSatis $kayit): string
    {
        $paraBirimi = strtoupper((string) ($kayit->para_birimi ?? 'TRY'));
        $ozet = $this->alacakOzeti($kayit);
        $toplam = (float) ($ozet['toplam_tutar'] ?? $kayit->genel_toplam ?? 0);
        $aktifTahsilat = $this->aktifTahsilatToplami($kayit);
        $kalan = (float) ($ozet['finansal_acik_tutar'] ?? max(0, $toplam - $aktifTahsilat));
        $plan = $ozet['plan'] ?? null;

        $kaynakSatirlari = [];
        foreach ($kayit->finansHareketleri as $finans) {
            $durum = (string) ($finans->durum?->value ?? $finans->durum ?? '');
            if ($durum !== 'aktif') {
                continue;
            }

            foreach ($finans->kasaHareketleri as $hareket) {
                $ad = trim((string) ($hareket->kasaHesabi?->ad ?? '-'));
                $kaynakSatirlari[] = 'Kasa - '.$ad.' ('.number_format((float) ($hareket->tutar ?? 0), 2, ',', '.').' '.$paraBirimi.')';
            }
            foreach ($finans->bankaHareketleri as $hareket) {
                $ad = trim((string) ($hareket->bankaHesabi?->ad ?? '-'));
                $kaynakSatirlari[] = 'Banka - '.$ad.' ('.number_format((float) ($hareket->tutar ?? 0), 2, ',', '.').' '.$paraBirimi.')';
            }
            foreach ($finans->posHareketleri as $hareket) {
                $ad = trim((string) ($hareket->posHesabi?->ad ?? '-'));
                $kaynakSatirlari[] = 'POS - '.$ad.' ('.number_format((float) ($hareket->tutar ?? 0), 2, ',', '.').' '.$paraBirimi.')';
            }
        }

        $kaynakMetni = count($kaynakSatirlari) > 0
            ? "- ".implode("\n- ", array_values(array_unique($kaynakSatirlari)))
            : '- Kayit bulunamadi';

        return implode("\n", [
            'Tahsilat Durumu: '.$this->tahsilatDurumuEtiketi($kayit),
            'Toplam: '.number_format($toplam, 2, ',', '.').' '.$paraBirimi,
            'Aktif Tahsilat: '.number_format($aktifTahsilat, 2, ',', '.').' '.$paraBirimi,
            'Plan: '.($plan ? '#'.(int) $plan->id.' / '.(string) $plan->durum : '-'),
            'Plan Bakiyesi: '.number_format((float) ($ozet['plan_kalan_tutar'] ?? 0), 2, ',', '.').' '.$paraBirimi,
            'Kalan: '.number_format($kalan, 2, ',', '.').' '.$paraBirimi,
            'Kaynaklar:',
            $kaynakMetni,
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function alacakOzeti(BarkodluSatis $kayit): array
    {
        $kayitId = (int) $kayit->getKey();
        if ($kayitId > 0 && isset($this->alacakOzetiOnbellek[$kayitId])) {
            return $this->alacakOzetiOnbellek[$kayitId];
        }

        $ozet = app(BarkodluSatisAlacakOzetServisi::class)->ozet($kayit);

        if ($kayitId > 0) {
            $this->alacakOzetiOnbellek[$kayitId] = $ozet;
        }

        return $ozet;
    }

    /**
     * @return array{
     *   satis_adedi:int,
     *   iptal_adedi:int,
     *   ciro:float,
     *   iade:float,
     *   net:float,
      *   cari_ad:string,
     *   tarih_etiketi:string,
     *   para_birimi_kirilimi:array<int, array{para_birimi:string,satis_adedi:int,ciro:float}>
     * }
     */
    public function perakendeOzet(): array
    {
        $sorgu = $this->perakendeSatisSorgusu();

        $toplamlar = (clone $sorgu)
            ->selectRaw("COALESCE(SUM(CASE WHEN durum = 'tamamlandi' THEN 1 ELSE 0 END), 0) as satis_adedi")
            ->selectRaw("COALESCE(SUM(CASE WHEN durum = 'iptal' THEN 1 ELSE 0 END), 0) as iptal_adedi")
            ->selectRaw("COALESCE(SUM(CASE WHEN durum = 'tamamlandi' THEN genel_toplam ELSE 0 END), 0) as ciro")
            ->first();

        $satisAdedi = (int) ($toplamlar->satis_adedi ?? 0);
        $iptalAdedi = (int) ($toplamlar->iptal_adedi ?? 0);
        $ciro = (float) ($toplamlar->ciro ?? 0);

        $iade = (float) BarkodluSatisIade::query()
            ->whereIn('satis_id', (clone $sorgu)->select('muhasebe_barkodlu_satislar.id'))
            ->sum('toplam_iade_tutari');

        $paraBirimiKirilimi = (clone $sorgu)
            ->where('durum', 'tamamlandi')
            ->selectRaw('UPPER(COALESCE(para_birimi, ?)) as para_birimi, COUNT(*) as satis_adedi, COALESCE(SUM(genel_toplam), 0) as ciro', ['TRY'])
            ->groupBy('para_birimi')
            ->orderBy('para_birimi')
            ->get()
            ->map(fn ($satir): array => [
                'para_birimi' => (string) ($satir->para_birimi ?? 'TRY'),
                'satis_adedi' => (int) ($satir->satis_adedi ?? 0),
                'ciro' => (float) ($satir->ciro ?? 0),
            ])
            ->values()
            ->all();

        return [
            'satis_adedi' => (int) $satisAdedi,
            'iptal_adedi' => (int) $iptalAdedi,
            'ciro' => round($ciro, 2),
            'iade' => round($iade, 2),
            'net' => round($ciro - $iade, 2),
            'cari_ad' => $this->perakendeCariAdi(),
            'tarih_etiketi' => $this->tarihAraligiEtiketi(...$this->aktifTarihAraligi()),
            'para_birimi_kirilimi' => $paraBirimiKirilimi,
        ];
    }

    public function perakendeOzetGoster(): bool
    {
        $musteriTipi = $this->getTableFilterState('musteri_tipi');
        $deger = is_array($musteriTipi) ? Arr::get($musteriTipi, 'value') : $musteriTipi;

        return trim((string) ($deger ?? '')) === 'perakende';
    }

    private function perakendeSatisSorgusu(): Builder
    {
        [$tarihBaslangic, $tarihBitis] = $this->aktifTarihAraligi();

        return BarkodluSatis::query()
            ->whereHas('cari', function ($query): void {
                $query->where('kod', self::PERAKENDE_CARI_KOD);
            })
            ->when($tarihBaslangic !== null, fn (Builder $q): Builder => $q->where('satis_tarihi', '>=', $tarihBaslangic.' 00:00:00'))
            ->when($tarihBitis !== null, fn (Builder $q): Builder => $q->where('satis_tarihi', '<=', $tarihBitis.' 23:59:59'));
    }

    private function perakendeCariAdi(): string
    {
        $firmaId = (int) ($this->aktifFirmaId() ?? 0);
        if ($firmaId < 1) {
            return 'Perakende Musteri';
        }

        $ad = trim((string) app(\App\Services\FirmaAyarDeposu::class)->oku(
            $firmaId,
            'barkodlu_satis_perakende_cari_ad',
            'Perakende Musteri'
        ));

        return $ad !== '' ? $ad : 'Perakende Musteri';
    }

    /**
     * @return array{0:?string,1:?string}
     */
    private function aktifTarihAraligi(): array
    {
        $ham = $this->getTableFilterState('tarih_araligi');
        $baslangic = is_array($ham) ? ($ham['baslangic'] ?? null) : null;
        $bitis = is_array($ham) ? ($ham['bitis'] ?? null) : null;

        return [
            $this->normalizeTarihDegeri($baslangic),
            $this->normalizeTarihDegeri($bitis),
        ];
    }

    private function normalizeTarihDegeri(mixed $deger): ?string
    {
        $tarih = trim((string) ($deger ?? ''));
        if ($tarih === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tarih)) {
            return null;
        }

        return $tarih;
    }

    private function tarihAraligiEtiketi(?string $baslangic, ?string $bitis): string
    {
        if ($baslangic === null && $bitis === null) {
            return 'Tum zamanlar';
        }

        if ($baslangic !== null && $bitis !== null) {
            $bugun = Carbon::today()->toDateString();
            if ($baslangic === $bugun && $bitis === $bugun) {
                return 'Bugun';
            }
        }

        if ($baslangic !== null && $bitis !== null) {
            return $baslangic.' - '.$bitis;
        }

        if ($baslangic !== null) {
            return 'Baslangic: '.$baslangic;
        }

        return 'Bitis: '.$bitis;
    }

    /**
     * @return array<string, string>
     */
    private function kaynakHesapSecenekleri(): array
    {
        if ($this->kaynakHesapSecenekleriCache !== null) {
            return $this->kaynakHesapSecenekleriCache;
        }

        $firmaId = (int) ($this->aktifFirmaId() ?? 0);
        if ($firmaId < 1) {
            return [];
        }

        $secenekler = [];

        foreach (KasaHesabi::query()
            ->where('firma_id', $firmaId)
            ->where('durum', HesapDurumu::Aktif->value)
            ->orderBy('ad')
            ->get(['id', 'ad']) as $hesap) {
            $secenekler['kasa:'.(int) $hesap->id] = 'Kasa - '.(string) $hesap->ad;
        }

        foreach (BankaHesabi::query()
            ->where('firma_id', $firmaId)
            ->where('durum', HesapDurumu::Aktif->value)
            ->orderBy('ad')
            ->get(['id', 'ad']) as $hesap) {
            $secenekler['banka:'.(int) $hesap->id] = 'Banka - '.(string) $hesap->ad;
        }

        foreach (PosHesabi::query()
            ->where('firma_id', $firmaId)
            ->where('durum', HesapDurumu::Aktif->value)
            ->orderBy('ad')
            ->get(['id', 'ad']) as $hesap) {
            $secenekler['pos:'.(int) $hesap->id] = 'POS - '.(string) $hesap->ad;
        }

        return $this->kaynakHesapSecenekleriCache = $secenekler;
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

    private function filtreUrlOlustur(?string $baslangic = null, ?string $bitis = null, ?string $musteriTipi = null): string
    {
        $filtreler = [];
        if ($baslangic !== null || $bitis !== null) {
            $filtreler['tarih_araligi'] = [
                'baslangic' => $baslangic,
                'bitis' => $bitis,
            ];
        }
        if ($musteriTipi !== null && $musteriTipi !== '') {
            $filtreler['musteri_tipi'] = [
                'value' => $musteriTipi,
            ];
        }

        return static::getUrl([
            'tableFilters' => $filtreler,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function iadeEdilebilirKalemSecenekleri(BarkodluSatis $satis): array
    {
        $iadeMiktarlari = [];
        $iadeler = BarkodluSatisIade::query()
            ->where('firma_id', (int) $satis->firma_id)
            ->where('satis_id', (int) $satis->id)
            ->with('kalemler')
            ->get();
        foreach ($iadeler as $iade) {
            foreach ($iade->kalemler as $iadeKalemi) {
                $satisKalemId = (int) $iadeKalemi->satis_kalem_id;
                $iadeMiktarlari[$satisKalemId] = (float) ($iadeMiktarlari[$satisKalemId] ?? 0) + (float) $iadeKalemi->miktar;
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

            $secenekler[$kalemId] = $kalem->stok_adi.' - Kalan: '.number_format($kalan, 2, ',', '.');
        }

        return $secenekler;
    }
}
