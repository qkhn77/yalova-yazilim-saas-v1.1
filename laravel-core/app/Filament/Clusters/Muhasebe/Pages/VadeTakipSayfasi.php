<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe as MuhasebeCluster;
use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeSayfaErisimleri;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi;
use App\Filament\Clusters\Muhasebe\Widgets\VadeTakipOzetWidget;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi;
use App\Models\Muhasebe\AlacakHatirlatmaLogu;
use App\Models\Muhasebe\AlacakPlani;
use App\Models\Muhasebe\AlacakPlanOnayTalebi;
use App\Models\Muhasebe\AlacakPlanTaksiti;
use App\Models\Muhasebe\AlacakTahsilatEslesmesi;
use App\Models\Muhasebe\AlacakTakipNotu;
use App\Models\Muhasebe\BankaHesabi;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\CariGrubu;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\PosHesabi;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Guvenlik\MuhasebeFilamentErisimYardimcisi;
use App\Muhasebe\Servisler\AlacakHatirlatmaGonderimServisi;
use App\Muhasebe\Servisler\AlacakHatirlatmaServisi;
use App\Muhasebe\Servisler\AlacakHatirlatmaMesajServisi;
use App\Muhasebe\Servisler\AlacakOperasyonServisi;
use App\Muhasebe\Servisler\AlacakPlanOnayServisi;
use App\Muhasebe\Servisler\AlacakPlanServisi;
use App\Muhasebe\Servisler\AlacakRaporServisi;
use App\Muhasebe\Servisler\AlacakTakipNotuServisi;
use App\Muhasebe\Servisler\FinansHareketServisi;
use App\Services\TenantContextService;
use App\Support\MuhasebeYetkiSablonlari;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VadeTakipSayfasi extends Page implements HasTable
{
    use InteractsWithTable;
    use MuhasebeSayfaErisimleri;

    /** @var array<int, bool> */
    private array $planIptalEdilebilirCache = [];

    /** @var array<string, bool> */
    private array $finansYetkiCache = [];

    private ?int $aktifFirmaIdCache = null;

    public bool $detayRaporlariYuklendi = false;

    public bool $hatirlatmaOzetiYuklendi = false;

    protected static ?string $cluster = MuhasebeCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Veresiye / Taksit Takibi';

    protected static ?string $slug = 'finans/vade-takibi';

    protected static string $view = 'filament.clusters.muhasebe.pages.vade-takibi';

    public function getTitle(): string|Htmlable
    {
        return 'Veresiye / Taksit Takibi';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Veresiye / Taksit Takibi';
    }

    public function getSubheading(): ?string
    {
        return 'Veresiye ve taksitli satislardan dogan acik alacak vadeleri.';
    }

    protected static function gerekliYetkiKodu(): string
    {
        return MuhasebeYetkiSablonlari::FINANS_GORUNTULE;
    }

    public function getSubNavigation(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            VadeTakipOzetWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        $operasyonModu = $this->operasyonModu();
        $operasyonModuAksiyonu = Actions\Action::make($operasyonModu ? 'hizli_gorunum' : 'operasyon_modu')
            ->label($operasyonModu ? 'Hizli Gorunum' : 'Operasyon Modu')
            ->icon($operasyonModu ? 'heroicon-o-bolt' : 'heroicon-o-adjustments-horizontal')
            ->color('gray')
            ->url($operasyonModu ? static::getUrl() : static::getUrl(['operasyon' => 1]));

        if (! $operasyonModu) {
            return [
                $operasyonModuAksiyonu,
            ];
        }

        return [
            $operasyonModuAksiyonu,
            Actions\Action::make('yeni_alacak_plani')
                ->label('Yeni Plan')
                ->icon('heroicon-o-calendar-days')
                ->color('primary')
                ->visible(fn (): bool => $this->operasyonModu() && $this->finansYetkisiVarMi(MuhasebeYetkiSablonlari::FINANS_OLUSTUR))
                ->form(fn (): array => $this->manuelPlanFormu())
                ->action(function (array $data): void {
                    $this->manuelPlanOlustur($data);
                }),
            Actions\Action::make('import_alacak_plan_csv')
                ->label('CSV Ice Aktar')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->visible(fn (): bool => $this->operasyonModu() && $this->finansYetkisiVarMi(MuhasebeYetkiSablonlari::FINANS_OLUSTUR))
                ->modalWidth('4xl')
                ->form(fn (): array => $this->csvIceAktarFormu())
                ->action(function (array $data): void {
                    $this->csvdenPlanlariIceAktar($data);
                }),
            Actions\Action::make('hatirlatma_cache_yenile')
                ->label('Hatirlatmalari Yenile')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function (): void {
                    $this->hatirlatmaCacheYenile();
                }),
            Actions\Action::make('hatirlatma_gonderim_olustur')
                ->label('Hatirlatma Gonder')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->visible(fn (): bool => $this->operasyonModu() && $this->finansYetkisiVarMi(MuhasebeYetkiSablonlari::FINANS_OLUSTUR))
                ->modalWidth('3xl')
                ->form(fn (): array => $this->hatirlatmaGonderimFormu())
                ->action(function (array $data): void {
                    $this->hatirlatmaGonderimleriOlustur($data);
                }),
            Actions\ActionGroup::make([
                Actions\Action::make('export_csv_sablon')
                    ->label('CSV Sablonu')
                    ->icon('heroicon-o-table-cells')
                    ->action(fn (): StreamedResponse => $this->csvSablonIndir(true)),
                Actions\Action::make('export_plan_ozet_csv')
                    ->label('Plan Ozeti CSV')
                    ->icon('heroicon-o-document-chart-bar')
                    ->action(fn (): StreamedResponse => $this->planOzetCsvIndir(true)),
                Actions\Action::make('export_tahsilat_performansi_csv')
                    ->label('Tahsilat Performansi CSV')
                    ->icon('heroicon-o-chart-bar')
                    ->action(fn (): StreamedResponse => $this->tahsilatPerformansiCsvIndir(true)),
                Actions\Action::make('export_risk_skoru_csv')
                    ->label('Risk Skoru CSV')
                    ->icon('heroicon-o-shield-exclamation')
                    ->action(fn (): StreamedResponse => $this->riskSkoruCsvIndir(true)),
                Actions\Action::make('export_cari_ozet_csv')
                    ->label('Cari Ozeti CSV')
                    ->icon('heroicon-o-users')
                    ->action(fn (): StreamedResponse => $this->cariOzetCsvIndir(true)),
                Actions\Action::make('export_yaslandirma_csv')
                    ->label('Yaslandirma CSV')
                    ->icon('heroicon-o-clock')
                    ->action(fn (): StreamedResponse => $this->yaslandirmaCsvIndir(true)),
                Actions\Action::make('export_vade_detay_csv')
                    ->label('Vade Detay CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn (): StreamedResponse => $this->vadeDetayCsvIndir(true)),
                Actions\Action::make('export_takip_notlari_csv')
                    ->label('Takip Notlari CSV')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->action(fn (): StreamedResponse => $this->takipNotlariCsvIndir(true)),
                Actions\Action::make('export_hatirlatma_mesajlari_csv')
                    ->label('Hatirlatma Mesaj CSV')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->form(fn (): array => $this->hatirlatmaMesajCsvFormu())
                    ->action(fn (array $data): StreamedResponse => $this->hatirlatmaMesajlariCsvIndir($data, true)),
            ])
                ->label('Disa Aktar')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => $this->operasyonModu())
                ->button(),
        ];
    }

    private function operasyonModu(): bool
    {
        return request()->boolean('operasyon');
    }

    public function detayRaporlariniYukle(): void
    {
        $this->detayRaporlariYuklendi = true;
    }

    public function hatirlatmaOzetiniYukle(): void
    {
        $this->hatirlatmaOzetiYuklendi = true;
    }

    public function table(Table $table): Table
    {
        $operasyonModu = $this->operasyonModu();

        return $table
            ->query(
                AlacakPlanTaksiti::query()
                    ->select([
                        'id',
                        'firma_id',
                        'alacak_plan_id',
                        'cari_id',
                        'cari_hareket_id',
                        'sira_no',
                        'vade_tarihi',
                        'tutar',
                        'odenen_tutar',
                        'kalan_tutar',
                        'son_tahsilat_tarihi',
                        'durum',
                    ])
                    ->when($this->aktifFirmaId() > 0, fn (Builder $query): Builder => $query->where('firma_id', $this->aktifFirmaId()))
                    ->addSelect([
                        'taksit_sayisi' => AlacakPlanTaksiti::query()
                            ->selectRaw('COUNT(*)')
                            ->whereColumn('alacak_plan_id', 'muhasebe_alacak_plan_taksitleri.alacak_plan_id'),
                    ])
                    ->with([
                        'cari:id,ad,kod',
                        'plan:id,islem_no,kaynak_turu,kaynak_id,plan_turu,toplam_tutar,pesinat_tutari,vade_farki_tipi,vade_farki_orani,vade_farki_tutari,odenen_tutar,kalan_tutar,para_birimi,durum,aciklama',
                    ])
            )
            ->columns([
                Tables\Columns\TextColumn::make('vade_tarihi')
                    ->label('Vade')
                    ->date('d.m.Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('durum_etiketi')
                    ->label('Durum')
                    ->getStateUsing(fn (AlacakPlanTaksiti $record): string => $this->durumEtiketi($record))
                    ->badge()
                    ->color(fn (AlacakPlanTaksiti $record): string => $this->durumRengi($record)),
                Tables\Columns\TextColumn::make('cari.ad')
                    ->label('Cari')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('plan.islem_no')
                    ->label('İşlem No')
                    ->searchable(),
                Tables\Columns\TextColumn::make('kaynak')
                    ->label('Modül / Kayıt')
                    ->getStateUsing(fn (AlacakPlanTaksiti $record): string => $this->kaynakMetni($record))
                    ->url(fn (AlacakPlanTaksiti $record): ?string => $this->kaynakUrl($record))
                    ->openUrlInNewTab(false)
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('plan.plan_turu')
                    ->label('Plan')
                    ->formatStateUsing(fn ($state): string => $this->raporPlanTuru($state))
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sira_no')
                    ->label('Taksit')
                    ->formatStateUsing(fn ($state, AlacakPlanTaksiti $record): string => '#'.$state.' / '.max(1, (int) ($record->getAttribute('taksit_sayisi') ?: 1)))
                    ->sortable(),
                Tables\Columns\TextColumn::make('tutar')
                    ->label('Tutar')
                    ->formatStateUsing(fn ($state, AlacakPlanTaksiti $record): string => $this->para((string) $state, (string) ($record->plan?->para_birimi ?? 'TRY')))
                    ->sortable(),
                Tables\Columns\TextColumn::make('odenen_tutar')
                    ->label('Odenen')
                    ->formatStateUsing(fn ($state, AlacakPlanTaksiti $record): string => $this->para((string) $state, (string) ($record->plan?->para_birimi ?? 'TRY')))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('kalan_tutar')
                    ->label('Kalan')
                    ->formatStateUsing(fn ($state, AlacakPlanTaksiti $record): string => $this->para((string) $state, (string) ($record->plan?->para_birimi ?? 'TRY')))
                    ->sortable(),
                Tables\Columns\TextColumn::make('plan.vade_farki_tutari')
                    ->label('Vade Farkı')
                    ->formatStateUsing(fn ($state, AlacakPlanTaksiti $record): string => $this->para((string) ($state ?? '0'), (string) ($record->plan?->para_birimi ?? 'TRY')))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('son_tahsilat_tarihi')
                    ->label('Son tahsilat')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('plan.aciklama')
                    ->label('Aciklama')
                    ->limit(45)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('vade_tarihi')
            ->filters([
                Tables\Filters\SelectFilter::make('durum')
                    ->label('Durum')
                    ->options([
                        'bekliyor' => 'Bekliyor',
                        'kismi_odendi' => 'Kismi odendi',
                        'odendi' => 'Odendi',
                        'gecikti' => 'Gecikti',
                        'iptal' => 'Iptal',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $durum = (string) ($data['value'] ?? '');
                        if ($durum === '') {
                            return $query;
                        }

                        return match ($durum) {
                            'gecikti' => $query
                                ->where('vade_tarihi', '<', Carbon::today()->toDateString())
                                ->where('kalan_tutar', '>', 0)
                                ->whereNotIn('durum', ['odendi', 'iptal']),
                            'bekliyor' => $query
                                ->where('durum', 'bekliyor')
                                ->where('vade_tarihi', '>=', Carbon::today()->toDateString()),
                            default => $query->where('durum', $durum),
                        };
                    }),
                ...($operasyonModu ? [
                Tables\Filters\SelectFilter::make('cari_id')
                    ->label('Cari')
                    ->relationship('cari', 'ad', fn (Builder $query): Builder => $query
                        ->when($this->aktifFirmaId() > 0, fn (Builder $cariQuery): Builder => $cariQuery->where('firma_id', $this->aktifFirmaId()))
                        ->where('durum', 'aktif'))
                    ->searchable(),
                Tables\Filters\SelectFilter::make('kaynak_turu')
                    ->label('Kaynak')
                    ->options([
                        'barkodlu_satis' => 'Barkodlu satis',
                        'teknik_servis' => 'Teknik servis',
                        'manuel' => 'Manuel',
                        'fatura' => 'Fatura',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $kaynakTuru = (string) ($data['value'] ?? '');
                        if ($kaynakTuru === '') {
                            return $query;
                        }

                        return $query->whereHas('plan', fn (Builder $plan): Builder => $plan->where('kaynak_turu', $kaynakTuru));
                    }),
                Tables\Filters\SelectFilter::make('plan_turu')
                    ->label('Plan turu')
                    ->options([
                        'veresiye' => 'Veresiye',
                        'taksit' => 'Taksitli',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $planTuru = (string) ($data['value'] ?? '');
                        if ($planTuru === '') {
                            return $query;
                        }

                        return $query->whereHas('plan', fn (Builder $plan): Builder => $plan->where('plan_turu', $planTuru));
                    }),
                Tables\Filters\SelectFilter::make('cari_turu')
                    ->label('Cari segmenti')
                    ->options(fn (): array => $this->cariTuruSecenekleri())
                    ->query(function (Builder $query, array $data): Builder {
                        $cariTuru = (string) ($data['value'] ?? '');
                        if ($cariTuru === '') {
                            return $query;
                        }

                        return $query->whereHas('cari', fn (Builder $cari): Builder => $cari->where('tur', $cariTuru));
                    }),
                Tables\Filters\SelectFilter::make('cari_grubu_id')
                    ->label('Cari grubu')
                    ->options(fn (): array => $this->cariGrubuSecenekleri())
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        $cariGrubuId = (int) ($data['value'] ?? 0);
                        if ($cariGrubuId < 1) {
                            return $query;
                        }

                        return $query->whereHas('cari', fn (Builder $cari): Builder => $cari->where('cari_grubu_id', $cariGrubuId));
                    }),
                Tables\Filters\SelectFilter::make('para_birimi')
                    ->label('Para birimi')
                    ->options(fn (): array => $this->planParaBirimiSecenekleri())
                    ->query(function (Builder $query, array $data): Builder {
                        $paraBirimi = strtoupper((string) ($data['value'] ?? ''));
                        if ($paraBirimi === '') {
                            return $query;
                        }

                        return $query->whereHas('plan', fn (Builder $plan): Builder => $plan->where('para_birimi', $paraBirimi));
                    }),
                ] : []),
                Tables\Filters\Filter::make('acik_alacaklar')
                    ->label('Acik alacaklar')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('kalan_tutar', '>', 0)
                        ->whereNotIn('durum', ['odendi', 'iptal'])),
                Tables\Filters\Filter::make('gecikenler')
                    ->label('Gecikenler')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('vade_tarihi', '<', Carbon::today()->toDateString())
                        ->where('kalan_tutar', '>', 0)
                        ->whereNotIn('durum', ['odendi', 'iptal'])),
                Tables\Filters\Filter::make('bugun')
                    ->label('Bugun vadesi gelen')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('vade_tarihi', Carbon::today()->toDateString())
                        ->where('kalan_tutar', '>', 0)
                        ->whereNotIn('durum', ['odendi', 'iptal'])),
                Tables\Filters\Filter::make('yaklasan')
                    ->label('7 gun icinde')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereBetween('vade_tarihi', [Carbon::today(), Carbon::today()->addDays(7)])
                        ->where('kalan_tutar', '>', 0)
                        ->whereNotIn('durum', ['odendi', 'iptal'])),
                ...($operasyonModu ? [
                Tables\Filters\Filter::make('vade_araligi')
                    ->form(fn (): array => [
                        Forms\Components\DatePicker::make('bas')
                            ->label('Baslangic'),
                        Forms\Components\DatePicker::make('bit')
                            ->label('Bitis'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['bas'] ?? null, fn (Builder $q, $d) => $q->where('vade_tarihi', '>=', $d))
                            ->when($data['bit'] ?? null, fn (Builder $q, $d) => $q->where('vade_tarihi', '<=', $d));
                    }),
                ] : []),
            ])
            ->actions([
                Tables\Actions\Action::make('tahsilat_al')
                    ->label('Tahsilat Al')
                    ->icon('heroicon-o-banknotes')
                    ->button()
                    ->color('success')
                    ->visible(fn (AlacakPlanTaksiti $record): bool => $this->finansYetkisiVarMi(MuhasebeYetkiSablonlari::FINANS_OLUSTUR)
                        && (float) $record->kalan_tutar > 0
                        && ! in_array((string) $record->durum, ['odendi', 'iptal'], true))
                    ->form(fn (AlacakPlanTaksiti $record): array => $this->tahsilatFormu($record))
                    ->action(function (AlacakPlanTaksiti $record, array $data): void {
                        $this->tahsilatKaydet($record, $data);
                    }),
                Tables\Actions\Action::make('tahsilat_iptal_ve_duzelt')
                    ->label('İptal et ve düzelt')
                    ->icon('heroicon-o-arrow-path')
                    ->iconButton()
                    ->tooltip('Tahsilatı iptal et ve düzelt')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Tahsilat iptal edilip düzeltilecek')
                    ->modalDescription('Mevcut tahsilat iptal edilir, taksit eşleşmeleri geri alınır ve girdiğiniz bilgilerle yeni tahsilat oluşturulur.')
                    ->visible(fn (AlacakPlanTaksiti $record): bool => $this->finansYetkisiVarMi(MuhasebeYetkiSablonlari::FINANS_GUNCELLE)
                        && $this->aktifTaksitTahsilati($record) !== null)
                    ->form(fn (AlacakPlanTaksiti $record): array => $this->tahsilatFormu($record))
                    ->action(function (AlacakPlanTaksiti $record, array $data): void {
                        $this->tahsilatIptalVeDuzelt($record, $data);
                    }),
                Tables\Actions\Action::make('duzenle')
                    ->label('Düzenle')
                    ->icon('heroicon-o-pencil-square')
                    ->iconButton()
                    ->tooltip('Düzenle')
                    ->color('warning')
                    ->modalWidth('2xl')
                    ->visible(fn (AlacakPlanTaksiti $record): bool => $this->finansYetkisiVarMi(MuhasebeYetkiSablonlari::FINANS_GUNCELLE)
                        && $record->plan instanceof AlacakPlani
                        && in_array((string) $record->plan->durum, ['aktif', 'kismi_odendi', 'gecikti'], true)
                        && (float) $record->kalan_tutar > 0
                        && ! in_array((string) $record->durum, ['odendi', 'iptal'], true))
                    ->form(fn (AlacakPlanTaksiti $record): array => $this->taksitDuzenleFormu($record))
                    ->action(function (AlacakPlanTaksiti $record, array $data): void {
                        $this->taksitDuzenle($record, $data);
                    }),
                Tables\Actions\Action::make('goruntule')
                    ->label('Görüntüle')
                    ->icon('heroicon-o-eye')
                    ->iconButton()
                    ->tooltip('Görüntüle')
                    ->color('gray')
                    ->modalHeading(fn (AlacakPlanTaksiti $record): string => 'Alacak Planı #'.(int) $record->alacak_plan_id)
                    ->modalWidth('7xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Kapat')
                    ->modalContent(fn (AlacakPlanTaksiti $record) => $this->detayModalIcerigi($record)),
                Tables\Actions\Action::make('ekstre')
                    ->label('Ekstre')
                    ->icon('heroicon-o-document-text')
                    ->iconButton()
                    ->tooltip('Ekstre')
                    ->color('gray')
                    ->url(fn (AlacakPlanTaksiti $record): string => e($this->taksitEkstreUrl($record))),
                Tables\Actions\Action::make('plan_kapat')
                        ->label('Planı Kapat')
                        ->icon('heroicon-o-check-circle')
                        ->iconButton()
                        ->tooltip('Planı Kapat')
                        ->color('success')
                        ->modalWidth('3xl')
                        ->visible(fn (AlacakPlanTaksiti $record): bool => $this->operasyonModu()
                            && $this->finansYetkisiVarMi(MuhasebeYetkiSablonlari::FINANS_OLUSTUR)
                            && $record->plan instanceof AlacakPlani
                            && (float) ($record->plan?->kalan_tutar ?? 0) > 0
                            && in_array((string) $record->plan->durum, ['aktif', 'kismi_odendi', 'gecikti'], true))
                        ->form(fn (AlacakPlanTaksiti $record): array => $this->planKapamaFormu($record))
                        ->action(function (AlacakPlanTaksiti $record, array $data): void {
                            $this->planKapat($record, $data);
                        }),
                Tables\Actions\Action::make('takip_notu_ekle')
                        ->label('Takip Notu Ekle')
                        ->icon('heroicon-o-chat-bubble-left-right')
                        ->iconButton()
                        ->tooltip('Takip Notu Ekle')
                        ->color('warning')
                        ->visible(fn (AlacakPlanTaksiti $record): bool => $this->operasyonModu()
                            && $this->finansYetkisiVarMi(MuhasebeYetkiSablonlari::FINANS_OLUSTUR)
                            && (float) $record->kalan_tutar > 0
                            && ! in_array((string) $record->durum, ['odendi', 'iptal'], true))
                        ->form(fn (AlacakPlanTaksiti $record): array => $this->takipNotuFormu($record))
                        ->action(function (AlacakPlanTaksiti $record, array $data): void {
                            $this->tekilTakipNotuOlustur($record, $data);
                        }),
                Tables\Actions\Action::make('plan_revizyon')
                        ->label('Plan Revizyonu')
                        ->icon('heroicon-o-calendar-days')
                        ->iconButton()
                        ->tooltip('Plan Revizyonu')
                        ->color('gray')
                        ->modalWidth('3xl')
                        ->visible(fn (AlacakPlanTaksiti $record): bool => $this->operasyonModu()
                            && $this->finansYetkisiVarMi(MuhasebeYetkiSablonlari::FINANS_GUNCELLE)
                            && $record->plan instanceof AlacakPlani
                            && in_array((string) $record->plan->durum, ['aktif', 'kismi_odendi', 'gecikti'], true))
                        ->form(fn (AlacakPlanTaksiti $record): array => $this->planRevizyonFormu($record))
                        ->action(function (AlacakPlanTaksiti $record, array $data): void {
                            $this->planRevizeEt($record, $data);
                        }),
                Tables\Actions\Action::make('plan_iptal')
                        ->label('Planı İptal Et')
                        ->icon('heroicon-o-x-circle')
                        ->iconButton()
                        ->tooltip('Planı İptal Et')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Alacak planini iptal et')
                        ->modalDescription('Bu islem planin tum acik vadelerini kapatir ve varsa plana bagli cari satis hareketlerini iptal eder. Tahsilat yapilmis planlar iptal edilemez. Iptal nedeni plan aciklamasina islenir.')
                        ->modalSubmitActionLabel('Iptal et')
                        ->visible(fn (AlacakPlanTaksiti $record): bool => $this->operasyonModu()
                            && $this->finansYetkisiVarMi(MuhasebeYetkiSablonlari::FINANS_SIL)
                            && $record->plan instanceof AlacakPlani
                            && in_array((string) $record->plan->durum, ['aktif', 'gecikti'], true)
                            && (float) ($record->plan->pesinat_tutari ?? 0) <= 0)
                        ->form(fn (): array => [
                            Forms\Components\Textarea::make('iptal_nedeni')
                                ->label('Iptal nedeni')
                                ->rows(3)
                                ->minLength(10)
                                ->required()
                                ->columnSpanFull(),
                        ])
                        ->action(function (AlacakPlanTaksiti $record, array $data): void {
                            $this->planIptalEt($record, $data);
                        }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('toplu_tahsilat_al')
                        ->label('Toplu Tahsilat Al')
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->visible(fn (): bool => $this->operasyonModu() && $this->finansYetkisiVarMi(MuhasebeYetkiSablonlari::FINANS_OLUSTUR))
                        ->modalWidth('3xl')
                        ->form(fn (): array => $this->topluTahsilatFormu())
                        ->action(function (EloquentCollection $records, array $data): void {
                            $this->topluTahsilatKaydet($records, $data);
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('toplu_takip_notu')
                        ->label('Takip Notu Ekle')
                        ->icon('heroicon-o-chat-bubble-left-right')
                        ->color('warning')
                        ->visible(fn (): bool => $this->operasyonModu() && $this->finansYetkisiVarMi(MuhasebeYetkiSablonlari::FINANS_OLUSTUR))
                        ->form(fn (): array => $this->topluTakipNotuFormu())
                        ->action(function (EloquentCollection $records, array $data): void {
                            $this->topluTakipNotuOlustur($records, $data);
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->deferLoading()
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    private function durumEtiketi(AlacakPlanTaksiti $record): string
    {
        if ((float) $record->kalan_tutar > 0 && $record->vade_tarihi?->lt(Carbon::today())) {
            return 'Gecikti';
        }

        return match ((string) $record->durum) {
            'bekliyor' => 'Bekliyor',
            'kismi_odendi' => 'Kismi odendi',
            'odendi' => 'Odendi',
            'gecikti' => 'Gecikti',
            'iptal' => 'Iptal',
            default => (string) $record->durum,
        };
    }

    private function durumRengi(AlacakPlanTaksiti $record): string
    {
        return match ($this->durumEtiketi($record)) {
            'Odendi' => 'success',
            'Kismi odendi' => 'warning',
            'Gecikti' => 'danger',
            'Iptal' => 'gray',
            default => 'info',
        };
    }

    private function kaynakMetni(AlacakPlanTaksiti $record): string
    {
        $plan = $record->plan;
        if (! $plan) {
            return '-';
        }

        return $this->raporKaynakMetni($plan->kaynak_turu, $plan->kaynak_id);
    }

    private function kaynakUrl(AlacakPlanTaksiti $record): ?string
    {
        $plan = $record->plan;
        $kaynakId = (int) ($plan?->kaynak_id ?? 0);
        if (! $plan || $kaynakId < 1) {
            return null;
        }

        return match ((string) $plan->kaynak_turu) {
            'teknik_servis' => TeknikServisKaydiKaynagi::getUrl('edit', ['record' => $kaynakId]),
            'barkodlu_satis' => BarkodluSatisFisiSayfasi::getUrl(['satis' => $kaynakId]),
            'fatura' => FaturaKaynagi::getUrl('view', ['record' => $kaynakId]),
            default => null,
        };
    }

    public function raporKaynakMetni(mixed $kaynakTuru, mixed $kaynakId = null): string
    {
        if (trim((string) $kaynakTuru) === '') {
            return '-';
        }

        $etiket = match ((string) $kaynakTuru) {
            'barkodlu_satis' => 'Barkodlu satis',
            'teknik_servis' => 'Teknik servis',
            'fatura' => 'Fatura',
            'manuel' => 'Manuel',
            default => ucfirst((string) $kaynakTuru),
        };

        return $etiket.((int) ($kaynakId ?? 0) > 0 ? ' #'.(int) $kaynakId : '');
    }

    public function raporPlanTuru(mixed $planTuru): string
    {
        return match ((string) $planTuru) {
            'taksit' => 'Taksitli',
            'veresiye' => 'Veresiye',
            default => (string) $planTuru,
        };
    }

    public function raporPara(mixed $tutar, string $paraBirimi): string
    {
        return $this->para((string) $tutar, $paraBirimi);
    }

    public function raporTarih(mixed $tarih): string
    {
        if (! $tarih) {
            return '-';
        }

        return Carbon::parse((string) $tarih)->format('d.m.Y');
    }

    public function raporTarihSaat(mixed $tarih): string
    {
        if (! $tarih) {
            return '-';
        }

        return Carbon::parse((string) $tarih)->format('d.m.Y H:i');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function yaslandirmaSatirlari(): array
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            return [];
        }

        return $this->vadeRaporCache('yaslandirma', fn (array $filtreler): array => app(AlacakRaporServisi::class)->yaslandirmaOzeti($firmaId, $filtreler));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function cariOzetSatirlari(): array
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            return [];
        }

        return $this->vadeRaporCache('cari-ozet', fn (array $filtreler): array => app(AlacakRaporServisi::class)->cariOzetleri($firmaId, 8, $filtreler));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function kaynakOzetSatirlari(): array
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            return [];
        }

        return $this->vadeRaporCache('kaynak-ozet', fn (array $filtreler): array => app(AlacakRaporServisi::class)->kaynakOzetleri($firmaId, $filtreler));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function planOzetSatirlari(): array
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            return [];
        }

        return $this->vadeRaporCache('plan-ozet', fn (array $filtreler): array => app(AlacakRaporServisi::class)->planOzetleri($firmaId, 8, $filtreler));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function tahsilatOncelikSatirlari(): array
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            return [];
        }

        return $this->vadeRaporCache('tahsilat-oncelik', fn (array $filtreler): array => app(AlacakRaporServisi::class)->tahsilatOncelikSatirlari($firmaId, 8, $filtreler));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function takipAjandasiSatirlari(): array
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            return [];
        }

        return $this->vadeRaporCache('takip-ajandasi', fn (array $filtreler): array => app(AlacakRaporServisi::class)->takipAjandasi($firmaId, 8, $filtreler, 7));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function tahsilatPerformansiSatirlari(): array
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            return [];
        }

        return $this->vadeRaporCache('tahsilat-performansi', fn (array $filtreler): array => app(AlacakRaporServisi::class)->tahsilatPerformansi($firmaId, 30, $filtreler));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function riskSkoruSatirlari(): array
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            return [];
        }

        return $this->vadeRaporCache('risk-skoru', fn (array $filtreler): array => app(AlacakRaporServisi::class)->riskSkoruSatirlari($firmaId, 8, $filtreler));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function hatirlatmaGonderimLoglari(): array
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            return [];
        }

        return $this->vadeRaporCache('hatirlatma-loglari', fn (array $_filtreler): array => AlacakHatirlatmaLogu::query()
            ->with('cari:id,ad,kod')
            ->where('firma_id', $firmaId)
            ->latest('created_at')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (AlacakHatirlatmaLogu $log): array => [
                'id' => (int) $log->getKey(),
                'created_at' => $log->created_at?->format('d.m.Y H:i') ?? '',
                'kanal' => (string) $log->kanal,
                'durum' => (string) $log->durum,
                'hedef' => (string) ($log->hedef ?? ''),
                'cari_ad' => (string) ($log->cari?->ad ?? '-'),
                'cari_kod' => (string) ($log->cari?->kod ?? ''),
                'deneme_sayisi' => (int) $log->deneme_sayisi,
                'hata' => (string) ($log->hata ?? ''),
                'gonderildi_at' => $log->gonderildi_at?->format('d.m.Y H:i') ?? '',
            ])
            ->all());
    }

    public function tahsilatOncelikEtiketi(mixed $oncelik): string
    {
        return match ((string) $oncelik) {
            'kritik' => 'Kritik',
            'yuksek' => 'Yuksek',
            'bugun' => 'Bugun',
            default => 'Normal',
        };
    }

    public function tahsilatOncelikSinifi(mixed $oncelik): string
    {
        return match ((string) $oncelik) {
            'kritik' => 'bg-red-50 text-red-700 ring-red-600/20',
            'yuksek' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
            'bugun' => 'bg-blue-50 text-blue-700 ring-blue-600/20',
            default => 'bg-gray-50 text-gray-600 ring-gray-500/20',
        };
    }

    public function riskSeviyesiEtiketi(mixed $seviye): string
    {
        return match ((string) $seviye) {
            'kritik' => 'Kritik',
            'yuksek' => 'Yuksek',
            'orta' => 'Orta',
            default => 'Normal',
        };
    }

    public function riskSeviyesiSinifi(mixed $seviye): string
    {
        return match ((string) $seviye) {
            'kritik' => 'bg-red-50 text-red-700 ring-red-600/20',
            'yuksek' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
            'orta' => 'bg-blue-50 text-blue-700 ring-blue-600/20',
            default => 'bg-gray-50 text-gray-600 ring-gray-500/20',
        };
    }

    public function hatirlatmaLogDurumEtiketi(mixed $durum): string
    {
        return match ((string) $durum) {
            AlacakHatirlatmaLogu::DURUM_GONDERILDI => 'Gonderildi',
            AlacakHatirlatmaLogu::DURUM_BASARISIZ => 'Basarisiz',
            AlacakHatirlatmaLogu::DURUM_HEDEF_YOK => 'Hedef yok',
            AlacakHatirlatmaLogu::DURUM_ATLANDI => 'Atlandi',
            default => 'Kuyrukta',
        };
    }

    public function hatirlatmaLogDurumSinifi(mixed $durum): string
    {
        return match ((string) $durum) {
            AlacakHatirlatmaLogu::DURUM_GONDERILDI => 'bg-green-50 text-green-700 ring-green-600/20',
            AlacakHatirlatmaLogu::DURUM_BASARISIZ => 'bg-red-50 text-red-700 ring-red-600/20',
            AlacakHatirlatmaLogu::DURUM_HEDEF_YOK => 'bg-gray-50 text-gray-600 ring-gray-500/20',
            default => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        };
    }

    public function takipAjandaEtiketi(mixed $durum): string
    {
        return match ((string) $durum) {
            'gecikti' => 'Gecikti',
            'bugun' => 'Bugun',
            'yaklasan' => 'Yaklasan',
            default => 'Plansiz',
        };
    }

    public function takipAjandaSinifi(mixed $durum): string
    {
        return match ((string) $durum) {
            'gecikti' => 'bg-red-50 text-red-700 ring-red-600/20',
            'bugun' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
            'yaklasan' => 'bg-blue-50 text-blue-700 ring-blue-600/20',
            default => 'bg-gray-50 text-gray-600 ring-gray-500/20',
        };
    }

    public function takipTipiEtiketi(mixed $tip): string
    {
        return match ((string) $tip) {
            'arama' => 'Telefon',
            'whatsapp' => 'WhatsApp',
            'sms' => 'SMS',
            'eposta' => 'E-posta',
            'mutabakat' => 'Mutabakat',
            default => 'Not',
        };
    }

    public function takipDurumEtiketi(mixed $durum): string
    {
        return match ((string) $durum) {
            'planlandi' => 'Planlandi',
            'ulasildi' => 'Ulasildi',
            'ulasilamadi' => 'Ulasilamadi',
            'odeme_sozu' => 'Odeme sozu',
            'takip_gerekli' => 'Takip gerekli',
            'tamamlandi' => 'Tamamlandi',
            default => ucfirst((string) $durum),
        };
    }

    public function odemeSozuDurumEtiketi(mixed $durum): string
    {
        return match ((string) $durum) {
            'bekliyor' => 'Bekliyor',
            'kismi' => 'Kismi',
            'tutuldu' => 'Tutuldu',
            'tutulmadi' => 'Tutulmadi',
            'iptal' => 'Iptal',
            default => '-',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function hatirlatmaOzeti(): array
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            return [
                'firma_id' => null,
                'yaklasan_gun' => 7,
                'olusturulma' => null,
                'geciken' => ['adet' => 0, 'para_toplamlari' => []],
                'bugun' => ['adet' => 0, 'para_toplamlari' => []],
                'yaklasan' => ['adet' => 0, 'para_toplamlari' => []],
                'satirlar' => [],
                'cache_olusturulma' => null,
            ];
        }

        return $this->vadeRaporCache('hatirlatma-ozeti', function (array $_filtreler) use ($firmaId): array {
            $ozet = app(AlacakHatirlatmaServisi::class)->ozet($firmaId, 7, 6);
            $cache = Cache::get('muhasebe:vade_hatirlatma:firma:'.$firmaId);
            if (is_array($cache)) {
                $ozet['cache_olusturulma'] = $cache['olusturulma'] ?? null;
            }

            return $ozet;
        });
    }

    /**
     * @template T of array
     * @param  callable(array<string, mixed>): T  $callback
     * @return T
     */
    private function vadeRaporCache(string $bolum, callable $callback): array
    {
        $firmaId = $this->aktifFirmaId();
        $filtreler = $this->raporFiltreleri();
        $cacheKey = 'muhasebe:vade-rapor:v2:'.$firmaId.':'.$bolum.':'.md5(json_encode($filtreler, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');

        return Cache::remember(
            $cacheKey,
            now()->addSeconds(45),
            fn (): array => $callback($filtreler),
        );
    }

    public function hatirlatmaToplamMetni(array $bolum): string
    {
        $toplamlar = (array) ($bolum['para_toplamlari'] ?? []);
        if ($toplamlar === []) {
            return '0,00 TRY';
        }

        return collect($toplamlar)
            ->map(fn (array $satir): string => $this->raporPara($satir['toplam'] ?? 0, (string) ($satir['para_birimi'] ?? 'TRY')))
            ->implode(' / ');
    }

    public function hatirlatmaWhatsappUrl(array $satir): ?string
    {
        return app(AlacakHatirlatmaMesajServisi::class)->whatsappUrl($satir);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function onayBekleyenTalepler(): array
    {
        if (! $this->finansYetkisiVarMi(MuhasebeYetkiSablonlari::FINANS_ONAY)) {
            return [];
        }

        return app(AlacakPlanOnayServisi::class)->bekleyenTalepler($this->aktifFirmaId(), 8);
    }

    public function onayTalebiniOnayla(int $talepId): void
    {
        if ($this->yetkiYoksaBildir(MuhasebeYetkiSablonlari::FINANS_ONAY)) {
            return;
        }

        try {
            $talep = $this->onayTalebiBul($talepId);
            app(AlacakPlanOnayServisi::class)->onayla($talep, auth()->id(), 'Vade takibi ekranindan onaylandi.');

            Notification::make()
                ->title('Onay talebi isleme alindi')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Onay talebi islenemedi')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function onayTalebiniReddet(int $talepId): void
    {
        if ($this->yetkiYoksaBildir(MuhasebeYetkiSablonlari::FINANS_ONAY)) {
            return;
        }

        try {
            $talep = $this->onayTalebiBul($talepId);
            app(AlacakPlanOnayServisi::class)->reddet($talep, auth()->id(), 'Vade takibi ekranindan reddedildi.');

            Notification::make()
                ->title('Onay talebi reddedildi')
                ->warning()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Onay talebi reddedilemedi')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function raporFiltreleri(): array
    {
        $vadeAraligi = $this->filtreDurumu('vade_araligi');

        return [
            'vade_baslangic' => is_array($vadeAraligi) ? ($vadeAraligi['bas'] ?? null) : null,
            'vade_bitis' => is_array($vadeAraligi) ? ($vadeAraligi['bit'] ?? null) : null,
            'cari_id' => $this->filtreDegeri('cari_id'),
            'kaynak_turu' => $this->filtreDegeri('kaynak_turu'),
            'plan_turu' => $this->filtreDegeri('plan_turu'),
            'cari_turu' => $this->filtreDegeri('cari_turu'),
            'cari_grubu_id' => $this->filtreDegeri('cari_grubu_id'),
            'para_birimi' => $this->filtreDegeri('para_birimi'),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function cariTuruSecenekleri(): array
    {
        $secenekler = [];
        foreach (CariTuru::cases() as $tur) {
            $secenekler[$tur->value] = $tur->etiket();
        }

        return $secenekler;
    }

    /**
     * @return array<int|string, string>
     */
    private function cariGrubuSecenekleri(): array
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            return [];
        }

        return Cache::remember(
            'muhasebe:vade-takip:cari-grubu-secenekleri:'.$firmaId,
            now()->addMinutes(5),
            fn (): array => CariGrubu::tenantScopeOlmadan(fn (): array => CariGrubu::query()
                ->gorunurFirmaIle($firmaId)
                ->where('aktif_mi', true)
                ->orderBy('ad')
                ->pluck('ad', 'id')
                ->all())
        );
    }

    /**
     * @return array<string, string>
     */
    private function planParaBirimiSecenekleri(): array
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            return [];
        }

        return Cache::remember(
            'muhasebe:vade-takip:para-birimi-secenekleri:'.$firmaId,
            now()->addMinutes(5),
            fn (): array => AlacakPlani::query()
                ->where('firma_id', $firmaId)
                ->whereNotNull('para_birimi')
                ->distinct()
                ->orderBy('para_birimi')
                ->pluck('para_birimi', 'para_birimi')
                ->mapWithKeys(fn ($kod): array => [strtoupper((string) $kod) => strtoupper((string) $kod)])
                ->all()
        );
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function raporFiltreOzetSatirlari(): array
    {
        $filtreler = $this->raporFiltreleri();

        return [
            ['Vade Baslangic', (string) ($filtreler['vade_baslangic'] ?: '-')],
            ['Vade Bitis', (string) ($filtreler['vade_bitis'] ?: '-')],
            ['Cari', $this->cariFiltreEtiketi($filtreler['cari_id'] ?? null)],
            ['Kaynak', $this->raporKaynakMetni((string) ($filtreler['kaynak_turu'] ?? ''))],
            ['Plan Turu', $this->raporPlanTuru((string) ($filtreler['plan_turu'] ?? '')) ?: '-'],
            ['Cari Segmenti', $this->cariTuruFiltreEtiketi($filtreler['cari_turu'] ?? null)],
            ['Cari Grubu', $this->cariGrubuFiltreEtiketi($filtreler['cari_grubu_id'] ?? null)],
            ['Para Birimi', (string) ($filtreler['para_birimi'] ?: '-')],
        ];
    }

    private function filtreDurumu(string $ad): mixed
    {
        try {
            return $this->getTableFilterState($ad);
        } catch (\Throwable) {
            return null;
        }
    }

    private function filtreDegeri(string $ad): mixed
    {
        $durum = $this->filtreDurumu($ad);

        return is_array($durum) ? ($durum['value'] ?? null) : $durum;
    }

    private function cariFiltreEtiketi(mixed $cariId): string
    {
        $cariId = (int) ($cariId ?? 0);
        if ($cariId < 1) {
            return '-';
        }

        $cari = Cari::query()->whereKey($cariId)->first();
        if (! $cari) {
            return '#'.$cariId;
        }

        return trim(($cari->kod ? $cari->kod.' - ' : '').$cari->ad);
    }

    private function cariTuruFiltreEtiketi(mixed $cariTuru): string
    {
        $cariTuru = (string) ($cariTuru ?? '');
        if ($cariTuru === '') {
            return '-';
        }

        return CariTuru::tryFrom($cariTuru)?->etiket() ?? $cariTuru;
    }

    private function cariGrubuFiltreEtiketi(mixed $cariGrubuId): string
    {
        $cariGrubuId = (int) ($cariGrubuId ?? 0);
        if ($cariGrubuId < 1) {
            return '-';
        }

        return (string) (CariGrubu::query()->whereKey($cariGrubuId)->value('ad') ?? '#'.$cariGrubuId);
    }

    private function para(string $tutar, string $paraBirimi): string
    {
        return number_format((float) $tutar, 2, ',', '.').' '.strtoupper($paraBirimi ?: 'TRY');
    }

    private function detayKaydiniYukle(AlacakPlanTaksiti $record): AlacakPlanTaksiti
    {
        return AlacakPlanTaksiti::query()
            ->with([
                'cari',
                'plan.cari',
                'plan.taksitler' => fn ($query) => $query->orderBy('sira_no'),
                'plan.taksitler.tahsilatEslesmeleri' => fn ($query) => $query->orderByDesc('tarih'),
                'plan.taksitler.tahsilatEslesmeleri.finansHareketi',
                'plan.tahsilatEslesmeleri' => fn ($query) => $query->orderByDesc('tarih'),
                'plan.tahsilatEslesmeleri.taksit',
                'plan.tahsilatEslesmeleri.finansHareketi',
                'plan.takipNotlari' => fn ($query) => $query->orderByDesc('takip_tarihi')->orderByDesc('id'),
                'plan.takipNotlari.taksit',
                'plan.takipNotlari.olusturan',
                'plan.revizyonlar' => fn ($query) => $query->orderByDesc('created_at')->orderByDesc('id'),
                'plan.revizyonlar.olusturan',
            ])
            ->whereKey($record->getKey())
            ->firstOrFail();
    }

    private function detayModalIcerigi(AlacakPlanTaksiti $record): View
    {
        $detay = $this->detayKaydiniYukle($record);
        $taksitTahsilatUrlMap = $detay->plan?->taksitler
            ->filter(fn (AlacakPlanTaksiti $taksit): bool => (float) $taksit->kalan_tutar > 0
                && ! in_array((string) $taksit->durum, ['odendi', 'iptal'], true))
            ->mapWithKeys(fn (AlacakPlanTaksiti $taksit): array => [
                (int) $taksit->getKey() => $this->taksitTahsilatUrl($taksit),
            ])
            ->all() ?? [];

        return view('filament.clusters.muhasebe.pages.partials.vade-plan-detay', [
            'record' => $detay,
            'tahsilatUrl' => $this->taksitTahsilatUrl($detay),
            'planTahsilatUrl' => $this->planTahsilatUrl($detay),
            'ekstreUrl' => $this->taksitEkstreUrl($detay),
            'taksitTahsilatUrlMap' => $taksitTahsilatUrlMap,
            'tahsilatYetkisiVarMi' => $this->finansYetkisiVarMi(MuhasebeYetkiSablonlari::FINANS_OLUSTUR),
        ]);
    }

    public function taksitTahsilatUrl(AlacakPlanTaksiti $record, float|string|null $tutar = null, ?string $aciklama = null): string
    {
        $record->loadMissing('plan');
        $tutar = $tutar !== null && (float) $tutar > 0
            ? (string) $tutar
            : ((float) $record->kalan_tutar > 0
                ? (string) $record->kalan_tutar
                : (string) ($record->plan?->kalan_tutar ?? '0'));

        return TahsilatOlusturSayfasi::getUrl().'?'.http_build_query([
            'cari_id' => (int) $record->cari_id,
            'alacak_plan_taksiti_id' => (int) $record->getKey(),
            'tutar' => number_format((float) $tutar, 2, '.', ''),
            'aciklama' => $aciklama ?: 'Vade tahsilati - '.$this->kaynakMetni($record).' / Taksit #'.(int) $record->sira_no,
        ]);
    }

    public function planTahsilatUrl(AlacakPlanTaksiti $record): string
    {
        $record->loadMissing('plan.taksitler');
        $plan = $record->plan;
        $hedefTaksit = $plan?->taksitler
            ->first(fn (AlacakPlanTaksiti $taksit): bool => (float) $taksit->kalan_tutar > 0
                && ! in_array((string) $taksit->durum, ['odendi', 'iptal'], true));

        if (! $hedefTaksit instanceof AlacakPlanTaksiti) {
            $hedefTaksit = $record;
        }

        $planKalan = (string) ($plan?->kalan_tutar ?? $hedefTaksit->kalan_tutar ?? '0');

        return $this->taksitTahsilatUrl(
            $hedefTaksit,
            $planKalan,
            'Vade tahsilati - '.$this->kaynakMetni($record).' / Plan #'.(int) ($plan?->getKey() ?? $record->alacak_plan_id),
        );
    }

    public function cariTahsilatUrl(int $cariId, string $paraBirimi, mixed $tutar): string
    {
        return TahsilatOlusturSayfasi::getUrl().'?'.http_build_query([
            'cari_id' => $cariId,
            'para_birimi' => strtoupper((string) ($paraBirimi ?: 'TRY')),
            'tutar' => number_format((float) $tutar, 2, '.', ''),
            'aciklama' => 'Vade takibi - cari bazli tahsilat',
        ]);
    }

    public function cariEkstreUrl(int $cariId, string $paraBirimi): string
    {
        return CariEkstreSayfasi::getUrl().'?'.http_build_query([
            'cari_id' => $cariId,
            'para_birimi' => strtoupper((string) ($paraBirimi ?: 'TRY')),
            'baslangic' => now()->subDays(60)->toDateString(),
            'bitis' => now()->addDays(30)->toDateString(),
            'otomatik' => 1,
        ]);
    }

    public function taksitEkstreUrl(AlacakPlanTaksiti $record): string
    {
        $record->loadMissing('plan');
        $plan = $record->plan;
        $baslangic = $plan?->baslangic_tarihi
            ? $plan->baslangic_tarihi->copy()->subDays(7)->toDateString()
            : now()->subDays(30)->toDateString();
        $bitis = $plan?->son_vade_tarihi && $plan->son_vade_tarihi->gt(Carbon::today())
            ? $plan->son_vade_tarihi->toDateString()
            : now()->toDateString();

        return CariEkstreSayfasi::getUrl().'?'.http_build_query([
            'cari_id' => (int) $record->cari_id,
            'para_birimi' => strtoupper((string) ($plan?->para_birimi ?: 'TRY')),
            'baslangic' => $baslangic,
            'bitis' => $bitis,
            'otomatik' => 1,
        ]);
    }

    public function vadeDetayCsvIndir(bool $excelUyumlu = false): StreamedResponse
    {
        $sorgu = $this->getFilteredSortedTableQuery()
            ->clone()
            ->with(['cari', 'plan']);

        return $this->csvYaniti('vade-takibi-detay-'.now()->format('Ymd_His'), $excelUyumlu, function ($out, string $delimiter) use ($sorgu): void {
            $this->csvRaporBasligi($out, $delimiter, 'Vade Takibi Detay');
            fputcsv($out, ['Cari Kodu', 'Cari', 'İşlem No', 'Modül / Kayıt', 'Plan Turu', 'Taksit', 'Vade', 'Durum', 'Tutar', 'Odenen', 'Kalan', 'Son Tahsilat', 'Aciklama'], $delimiter);

            /** @var AlacakPlanTaksiti $kayit */
            foreach ($sorgu->cursor() as $kayit) {
                $paraBirimi = strtoupper((string) ($kayit->plan?->para_birimi ?: 'TRY'));
                fputcsv($out, [
                    (string) ($kayit->cari?->kod ?? ''),
                    (string) ($kayit->cari?->ad ?? ''),
                    (string) ($kayit->plan?->islem_no ?? ''),
                    $this->kaynakMetni($kayit),
                    $this->raporPlanTuru($kayit->plan?->plan_turu),
                    '#'.(int) $kayit->sira_no,
                    $kayit->vade_tarihi?->format('d.m.Y') ?? '',
                    $this->durumEtiketi($kayit),
                    $this->raporPara($kayit->tutar, $paraBirimi),
                    $this->raporPara($kayit->odenen_tutar, $paraBirimi),
                    $this->raporPara($kayit->kalan_tutar, $paraBirimi),
                    $kayit->son_tahsilat_tarihi?->format('d.m.Y H:i') ?? '',
                    (string) ($kayit->plan?->aciklama ?? ''),
                ], $delimiter);
            }
        });
    }

    public function takipNotlariCsvIndir(bool $excelUyumlu = false): StreamedResponse
    {
        $firmaId = $this->aktifFirmaId();
        $satirlar = $firmaId > 0 ? app(AlacakRaporServisi::class)->takipNotlari($firmaId, 0, $this->raporFiltreleri()) : [];

        return $this->csvYaniti('vade-takip-notlari-'.now()->format('Ymd_His'), $excelUyumlu, function ($out, string $delimiter) use ($satirlar): void {
            $this->csvRaporBasligi($out, $delimiter, 'Vade Takip Notlari');
            fputcsv($out, ['Takip ID', 'Cari Kodu', 'Cari', 'Kaynak', 'Plan Turu', 'Taksit', 'Vade', 'Takip Tipi', 'Durum', 'Takip Tarihi', 'Sonraki Takip', 'Odeme Sozu Tarihi', 'Odeme Sozu Tutari', 'Odeme Sozu Durumu', 'Beklenen Tutar', 'Kalan Tutar', 'Not', 'Sonuc Notu'], $delimiter);

            foreach ($satirlar as $satir) {
                $paraBirimi = strtoupper((string) ($satir['para_birimi'] ?? 'TRY'));
                fputcsv($out, [
                    (string) ($satir['takip_notu_id'] ?? ''),
                    (string) ($satir['cari_kod'] ?? ''),
                    (string) ($satir['cari_ad'] ?? ''),
                    $this->raporKaynakMetni($satir['kaynak_turu'] ?? '', $satir['kaynak_id'] ?? null),
                    $this->raporPlanTuru($satir['plan_turu'] ?? ''),
                    filled($satir['sira_no'] ?? null) ? '#'.(int) $satir['sira_no'] : '',
                    $this->raporTarih($satir['vade_tarihi'] ?? null),
                    $this->takipTipiEtiketi($satir['takip_tipi'] ?? ''),
                    $this->takipDurumEtiketi($satir['takip_durumu'] ?? ''),
                    $this->raporTarihSaat($satir['takip_tarihi'] ?? null),
                    $this->raporTarihSaat($satir['sonraki_takip_tarihi'] ?? null),
                    $this->raporTarihSaat($satir['odeme_sozu_tarihi'] ?? null),
                    $this->raporPara($satir['odeme_sozu_tutari'] ?? 0, $paraBirimi),
                    $this->odemeSozuDurumEtiketi($satir['odeme_sozu_durumu'] ?? ''),
                    $this->raporPara($satir['beklenen_tutar'] ?? 0, $paraBirimi),
                    $this->raporPara($satir['kalan_tutar'] ?? 0, $paraBirimi),
                    (string) ($satir['not'] ?? ''),
                    (string) ($satir['sonuc_notu'] ?? ''),
                ], $delimiter);
            }
        });
    }

    /**
     * @param array<string,mixed> $data
     */
    public function hatirlatmaMesajlariCsvIndir(array $data = [], bool $excelUyumlu = false): StreamedResponse
    {
        $firmaId = $this->aktifFirmaId();
        $kanal = in_array((string) ($data['kanal'] ?? 'whatsapp'), ['whatsapp', 'sms', 'email'], true)
            ? (string) ($data['kanal'] ?? 'whatsapp')
            : 'whatsapp';
        $yaklasanGun = max(1, (int) ($data['yaklasan_gun'] ?? 7));
        $limit = max(1, (int) ($data['limit'] ?? 50));
        $sablon = trim((string) ($data['sablon'] ?? ''));

        $satirlar = $firmaId > 0
            ? app(AlacakHatirlatmaMesajServisi::class)->mesajlar($firmaId, $kanal, $yaklasanGun, $limit, $sablon !== '' ? $sablon : null)
            : [];

        return $this->csvYaniti('vade-hatirlatma-mesajlari-'.$kanal.'-'.now()->format('Ymd_His'), $excelUyumlu, function ($out, string $delimiter) use ($satirlar, $kanal, $yaklasanGun): void {
            $this->csvRaporBasligi($out, $delimiter, 'Vade Hatirlatma Mesajlari');
            fputcsv($out, ['Kanal', strtoupper($kanal)], $delimiter);
            fputcsv($out, ['Yaklasan Gun', (string) $yaklasanGun], $delimiter);
            fputcsv($out, [], $delimiter);
            fputcsv($out, ['Durum', 'Cari Kodu', 'Cari', 'Hedef', 'Para Birimi', 'Vade Adedi', 'Kalan Toplam', 'Geciken Toplam', 'Bugun Toplam', 'Ilk Vade', 'Baslik', 'Mesaj', 'WhatsApp URL'], $delimiter);

            foreach ($satirlar as $satir) {
                $paraBirimi = strtoupper((string) ($satir['para_birimi'] ?? 'TRY'));
                fputcsv($out, [
                    (string) ($satir['durum'] ?? ''),
                    (string) ($satir['cari_kod'] ?? ''),
                    (string) ($satir['cari_ad'] ?? ''),
                    (string) ($satir['hedef'] ?? ''),
                    $paraBirimi,
                    (string) ($satir['vade_adedi'] ?? 0),
                    $this->raporPara($satir['kalan_toplam'] ?? 0, $paraBirimi),
                    $this->raporPara($satir['geciken_toplam'] ?? 0, $paraBirimi),
                    $this->raporPara($satir['bugun_toplam'] ?? 0, $paraBirimi),
                    $this->raporTarih($satir['ilk_vade_tarihi'] ?? null),
                    (string) ($satir['baslik'] ?? ''),
                    (string) ($satir['mesaj'] ?? ''),
                    (string) ($satir['whatsapp_url'] ?? ''),
                ], $delimiter);
            }
        });
    }

    public function planOzetCsvIndir(bool $excelUyumlu = false): StreamedResponse
    {
        $firmaId = $this->aktifFirmaId();
        $satirlar = $firmaId > 0 ? app(AlacakRaporServisi::class)->planOzetleri($firmaId, 0, $this->raporFiltreleri()) : [];

        return $this->csvYaniti('vade-plan-ozeti-'.now()->format('Ymd_His'), $excelUyumlu, function ($out, string $delimiter) use ($satirlar): void {
            $this->csvRaporBasligi($out, $delimiter, 'Vade Plan Ozeti');
            fputcsv($out, ['Plan ID', 'Cari Kodu', 'Cari', 'Kaynak', 'Plan Turu', 'Durum', 'Toplam', 'Pesinat', 'Odenen', 'Kalan', 'Geciken', 'Acik Vade', 'Ilk Acik Vade', 'Son Acik Vade', 'Aciklama'], $delimiter);

            foreach ($satirlar as $satir) {
                $paraBirimi = strtoupper((string) ($satir['para_birimi'] ?? 'TRY'));
                fputcsv($out, [
                    (string) ($satir['plan_id'] ?? ''),
                    (string) ($satir['cari_kod'] ?? ''),
                    (string) ($satir['cari_ad'] ?? ''),
                    $this->raporKaynakMetni($satir['kaynak_turu'] ?? '', $satir['kaynak_id'] ?? null),
                    $this->raporPlanTuru($satir['plan_turu'] ?? ''),
                    (string) ($satir['durum'] ?? ''),
                    $this->raporPara($satir['toplam_tutar'] ?? 0, $paraBirimi),
                    $this->raporPara($satir['pesinat_tutari'] ?? 0, $paraBirimi),
                    $this->raporPara($satir['odenen_tutar'] ?? 0, $paraBirimi),
                    $this->raporPara($satir['kalan_tutar'] ?? 0, $paraBirimi),
                    $this->raporPara($satir['geciken_toplam'] ?? 0, $paraBirimi),
                    (string) ($satir['acik_vade_adedi'] ?? 0),
                    $this->raporTarih($satir['ilk_acik_vade_tarihi'] ?? null),
                    $this->raporTarih($satir['son_acik_vade_tarihi'] ?? null),
                    (string) ($satir['aciklama'] ?? ''),
                ], $delimiter);
            }
        });
    }

    public function tahsilatPerformansiCsvIndir(bool $excelUyumlu = false): StreamedResponse
    {
        $firmaId = $this->aktifFirmaId();
        $satirlar = $firmaId > 0 ? app(AlacakRaporServisi::class)->tahsilatPerformansi($firmaId, 30, $this->raporFiltreleri()) : [];

        return $this->csvYaniti('vade-tahsilat-performansi-'.now()->format('Ymd_His'), $excelUyumlu, function ($out, string $delimiter) use ($satirlar): void {
            $this->csvRaporBasligi($out, $delimiter, 'Vade Tahsilat Performansi');
            fputcsv($out, ['Donem', 'Son 30 Gun'], $delimiter);
            fputcsv($out, [], $delimiter);
            fputcsv($out, ['Para Birimi', 'Tahsil Edilen', 'Finans Hareketi', 'Plan', 'Cari', 'Taksit Eslesmesi', 'Son Tahsilat'], $delimiter);

            foreach ($satirlar as $satir) {
                $paraBirimi = strtoupper((string) ($satir['para_birimi'] ?? 'TRY'));
                fputcsv($out, [
                    $paraBirimi,
                    $this->raporPara($satir['tahsil_edilen_tutar'] ?? 0, $paraBirimi),
                    (string) ($satir['finans_hareket_adedi'] ?? 0),
                    (string) ($satir['plan_adedi'] ?? 0),
                    (string) ($satir['cari_adedi'] ?? 0),
                    (string) ($satir['taksit_eslesme_adedi'] ?? 0),
                    $this->raporTarihSaat($satir['son_tahsilat_tarihi'] ?? null),
                ], $delimiter);
            }
        });
    }

    public function riskSkoruCsvIndir(bool $excelUyumlu = false): StreamedResponse
    {
        $firmaId = $this->aktifFirmaId();
        $satirlar = $firmaId > 0 ? app(AlacakRaporServisi::class)->riskSkoruSatirlari($firmaId, 0, $this->raporFiltreleri()) : [];

        return $this->csvYaniti('vade-risk-skoru-'.now()->format('Ymd_His'), $excelUyumlu, function ($out, string $delimiter) use ($satirlar): void {
            $this->csvRaporBasligi($out, $delimiter, 'Vade Risk Skoru');
            fputcsv($out, ['Cari Kodu', 'Cari', 'Para Birimi', 'Risk Skoru', 'Risk Seviyesi', 'Acik Toplam', 'Geciken Toplam', 'Gecikme Gunu', 'Acik Vade', 'Odeme Sozu Ihlali', 'Son Tahsilat', 'Onerilen Aksiyon'], $delimiter);

            foreach ($satirlar as $satir) {
                $paraBirimi = strtoupper((string) ($satir['para_birimi'] ?? 'TRY'));
                fputcsv($out, [
                    (string) ($satir['cari_kod'] ?? ''),
                    (string) ($satir['cari_ad'] ?? ''),
                    $paraBirimi,
                    (string) ($satir['risk_skoru'] ?? 0),
                    $this->riskSeviyesiEtiketi($satir['risk_seviyesi'] ?? 'normal'),
                    $this->raporPara($satir['acik_toplam'] ?? 0, $paraBirimi),
                    $this->raporPara($satir['geciken_toplam'] ?? 0, $paraBirimi),
                    (string) ($satir['gecikme_gunu'] ?? 0),
                    (string) ($satir['acik_vade_adedi'] ?? 0),
                    (string) ($satir['odeme_sozu_ihlali_adedi'] ?? 0),
                    $this->raporTarihSaat($satir['son_tahsilat_tarihi'] ?? null),
                    (string) ($satir['onerilen_aksiyon'] ?? ''),
                ], $delimiter);
            }
        });
    }

    public function csvSablonIndir(bool $excelUyumlu = false): StreamedResponse
    {
        return $this->csvYaniti('vade-plan-import-sablonu', $excelUyumlu, function ($out, string $delimiter): void {
            fputcsv($out, ['cari_kod', 'cari_id', 'plan_turu', 'toplam_tutar', 'pesinat_tutari', 'para_birimi', 'ilk_vade_tarihi', 'taksit_sayisi', 'taksit_araligi_gun', 'vade_farki_uygula', 'vade_farki_tipi', 'vade_farki_orani', 'aciklama'], $delimiter);
            fputcsv($out, ['MUS-001', '', 'taksit', '1200.00', '200.00', 'TRY', now()->addDays(30)->toDateString(), '4', '30', '0', 'tek_seferlik', '0', 'Ornek taksitli plan'], $delimiter);
            fputcsv($out, ['MUS-002', '', 'veresiye', '500.00', '0.00', 'TRY', now()->addDays(15)->toDateString(), '1', '30', '0', 'tek_seferlik', '0', 'Ornek veresiye plan'], $delimiter);
        });
    }

    public function cariOzetCsvIndir(bool $excelUyumlu = false): StreamedResponse
    {
        $firmaId = $this->aktifFirmaId();
        $satirlar = $firmaId > 0 ? app(AlacakRaporServisi::class)->cariOzetleri($firmaId, 0, $this->raporFiltreleri()) : [];

        return $this->csvYaniti('vade-cari-ozeti-'.now()->format('Ymd_His'), $excelUyumlu, function ($out, string $delimiter) use ($satirlar): void {
            $this->csvRaporBasligi($out, $delimiter, 'Vade Cari Ozeti');
            fputcsv($out, ['Cari Kodu', 'Cari', 'Para Birimi', 'Plan Adedi', 'Acik Vade', 'Acik Toplam', 'Geciken', 'Bugun', 'Gelecek', 'Ilk Vade', 'Son Vade'], $delimiter);

            foreach ($satirlar as $satir) {
                $paraBirimi = strtoupper((string) ($satir['para_birimi'] ?? 'TRY'));
                fputcsv($out, [
                    (string) ($satir['cari_kod'] ?? ''),
                    (string) ($satir['cari_ad'] ?? ''),
                    $paraBirimi,
                    (string) ($satir['plan_adedi'] ?? 0),
                    (string) ($satir['acik_vade_adedi'] ?? 0),
                    $this->raporPara($satir['acik_toplam'] ?? 0, $paraBirimi),
                    $this->raporPara($satir['geciken_toplam'] ?? 0, $paraBirimi),
                    $this->raporPara($satir['bugun_toplam'] ?? 0, $paraBirimi),
                    $this->raporPara($satir['gelecek_toplam'] ?? 0, $paraBirimi),
                    $this->raporTarih($satir['ilk_vade_tarihi'] ?? null),
                    $this->raporTarih($satir['son_vade_tarihi'] ?? null),
                ], $delimiter);
            }
        });
    }

    public function yaslandirmaCsvIndir(bool $excelUyumlu = false): StreamedResponse
    {
        $firmaId = $this->aktifFirmaId();
        $satirlar = $firmaId > 0 ? app(AlacakRaporServisi::class)->yaslandirmaOzeti($firmaId, $this->raporFiltreleri()) : [];

        return $this->csvYaniti('vade-yaslandirma-'.now()->format('Ymd_His'), $excelUyumlu, function ($out, string $delimiter) use ($satirlar): void {
            $this->csvRaporBasligi($out, $delimiter, 'Vade Yaslandirma');
            fputcsv($out, ['Para Birimi', 'Vadesi Gelmemis', 'Bugun', '1-30 Gun', '31-60 Gun', '61-90 Gun', '90+ Gun', 'Toplam'], $delimiter);

            foreach ($satirlar as $satir) {
                $paraBirimi = strtoupper((string) ($satir['para_birimi'] ?? 'TRY'));
                fputcsv($out, [
                    $paraBirimi,
                    $this->raporPara($satir['vadesi_gelmemis'] ?? 0, $paraBirimi),
                    $this->raporPara($satir['bugun'] ?? 0, $paraBirimi),
                    $this->raporPara($satir['geciken_1_30'] ?? 0, $paraBirimi),
                    $this->raporPara($satir['geciken_31_60'] ?? 0, $paraBirimi),
                    $this->raporPara($satir['geciken_61_90'] ?? 0, $paraBirimi),
                    $this->raporPara($satir['geciken_90_plus'] ?? 0, $paraBirimi),
                    $this->raporPara($satir['toplam'] ?? 0, $paraBirimi),
                ], $delimiter);
            }
        });
    }

    private function csvYaniti(string $dosyaAdiKoku, bool $excelUyumlu, callable $yazici): StreamedResponse
    {
        $delimiter = $excelUyumlu ? ';' : ',';
        $dosyaAdi = $dosyaAdiKoku.($excelUyumlu ? '-excel' : '').'.csv';

        return response()->streamDownload(function () use ($delimiter, $excelUyumlu, $yazici): void {
            $out = fopen('php://output', 'wb');
            if (! is_resource($out)) {
                return;
            }

            if ($excelUyumlu) {
                fwrite($out, "\xEF\xBB\xBF");
            }

            $yazici($out, $delimiter);
            fclose($out);
        }, $dosyaAdi, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function csvRaporBasligi(mixed $out, string $delimiter, string $rapor): void
    {
        fputcsv($out, ['Rapor', $rapor], $delimiter);
        fputcsv($out, ['Olusturulma', now()->format('d.m.Y H:i:s')], $delimiter);
        fputcsv($out, ['Global Arama', trim((string) ($this->getTableSearch() ?? '')) ?: '-'], $delimiter);
        fputcsv($out, ['Siralama', trim((string) (($this->getTableSortColumn() ?? '').' '.($this->getTableSortDirection() ?? ''))) ?: 'varsayilan'], $delimiter);
        foreach ($this->raporFiltreOzetSatirlari() as $satir) {
            fputcsv($out, $satir, $delimiter);
        }
        fputcsv($out, [], $delimiter);
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private function manuelPlanFormu(): array
    {
        return [
            Forms\Components\Select::make('cari_id')
                ->label('Cari')
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => $this->cariAramaSonuclari($search))
                ->getOptionLabelUsing(fn ($value): ?string => $this->cariEtiketi($value))
                ->live()
                ->afterStateUpdated(function (mixed $state, Forms\Set $set): void {
                    $set('para_birimi', $this->cariParaBirimi((int) $state) ?? 'TRY');
                })
                ->required(),
            Forms\Components\Select::make('plan_turu')
                ->label('Plan turu')
                ->options([
                    'veresiye' => 'Veresiye',
                    'taksit' => 'Taksitli',
                ])
                ->default('veresiye')
                ->live()
                ->afterStateUpdated(function (mixed $state, Forms\Set $set): void {
                    $set('vade_farki_tipi', (string) $state === 'taksit' ? 'aylik' : 'tek_seferlik');
                })
                ->required(),
            Forms\Components\TextInput::make('toplam_tutar')
                ->label('Toplam tutar')
                ->numeric()
                ->minValue(0.01)
                ->step('0.01')
                ->live()
                ->required(),
            Forms\Components\TextInput::make('pesinat_tutari')
                ->label('Pesinat / onceden odenen')
                ->numeric()
                ->default('0.00')
                ->minValue(0)
                ->step('0.01')
                ->live()
                ->required(),
            Forms\Components\Toggle::make('vade_farki_uygula')
                ->label('Vade farki uygula')
                ->default(false)
                ->live(),
            Forms\Components\Select::make('vade_farki_tipi')
                ->label('Vade farki tipi')
                ->options([
                    'tek_seferlik' => 'Tek seferlik',
                    'aylik' => 'Aylik',
                    'yillik' => 'Yillik',
                ])
                ->default('tek_seferlik')
                ->live()
                ->visible(fn (Forms\Get $get): bool => (bool) $get('vade_farki_uygula')),
            Forms\Components\TextInput::make('vade_farki_orani')
                ->label('Vade farki orani (%)')
                ->numeric()
                ->default('0')
                ->minValue(0)
                ->step('0.01')
                ->live()
                ->visible(fn (Forms\Get $get): bool => (bool) $get('vade_farki_uygula')),
            Forms\Components\TextInput::make('para_birimi')
                ->label('Para birimi')
                ->default('TRY')
                ->disabled()
                ->dehydrated()
                ->required(),
            Forms\Components\Placeholder::make('planlanacak_tutar')
                ->label('Planlanacak kalan')
                ->content(fn (Forms\Get $get): string => $this->para(
                    number_format($this->manuelPlanlanacakTutar($get), 2, '.', ''),
                    (string) ($get('para_birimi') ?: 'TRY')
                )),
            Forms\Components\DatePicker::make('ilk_vade_tarihi')
                ->label('Ilk vade tarihi')
                ->default(now()->addDays(30)->toDateString())
                ->native(false)
                ->required(),
            Forms\Components\TextInput::make('taksit_sayisi')
                ->label('Taksit sayisi')
                ->numeric()
                ->default(1)
                ->minValue(1)
                ->visible(fn (Forms\Get $get): bool => (string) $get('plan_turu') === 'taksit')
                ->required(fn (Forms\Get $get): bool => (string) $get('plan_turu') === 'taksit'),
            Forms\Components\TextInput::make('taksit_araligi_gun')
                ->label('Taksit araligi (gun)')
                ->numeric()
                ->default(30)
                ->minValue(1)
                ->visible(fn (Forms\Get $get): bool => (string) $get('plan_turu') === 'taksit')
                ->required(fn (Forms\Get $get): bool => (string) $get('plan_turu') === 'taksit'),
            Forms\Components\Textarea::make('aciklama')
                ->label('Aciklama')
                ->rows(2)
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private function hatirlatmaMesajCsvFormu(): array
    {
        $mesajServisi = app(AlacakHatirlatmaMesajServisi::class);
        $degiskenler = collect($mesajServisi->degiskenler())
            ->map(fn (string $aciklama, string $degisken): string => $degisken.' = '.$aciklama)
            ->implode(', ');

        return [
            Forms\Components\Select::make('kanal')
                ->label('Kanal')
                ->options([
                    'whatsapp' => 'WhatsApp',
                    'sms' => 'SMS',
                    'email' => 'E-posta',
                ])
                ->default('whatsapp')
                ->live()
                ->required(),
            Forms\Components\TextInput::make('yaklasan_gun')
                ->label('Yaklasan gun')
                ->numeric()
                ->minValue(1)
                ->default(7)
                ->required(),
            Forms\Components\TextInput::make('limit')
                ->label('Cari limiti')
                ->numeric()
                ->minValue(1)
                ->default(50)
                ->required(),
            Forms\Components\Textarea::make('sablon')
                ->label('Mesaj sablonu')
                ->rows(8)
                ->placeholder($mesajServisi->varsayilanSablon('whatsapp'))
                ->helperText('Bos birakilirsa secili kanal icin varsayilan sablon kullanilir. Degiskenler: '.$degiskenler)
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private function hatirlatmaGonderimFormu(): array
    {
        $mesajServisi = app(AlacakHatirlatmaMesajServisi::class);
        $degiskenler = collect($mesajServisi->degiskenler())
            ->map(fn (string $aciklama, string $degisken): string => $degisken.' = '.$aciklama)
            ->implode(', ');

        return [
            Forms\Components\Select::make('kanal')
                ->label('Kanal')
                ->options([
                    'whatsapp' => 'WhatsApp',
                    'sms' => 'SMS',
                    'email' => 'E-posta',
                ])
                ->default('whatsapp')
                ->required(),
            Forms\Components\TextInput::make('yaklasan_gun')
                ->label('Yaklasan gun')
                ->numeric()
                ->minValue(1)
                ->default(7)
                ->required(),
            Forms\Components\TextInput::make('limit')
                ->label('Cari limiti')
                ->numeric()
                ->minValue(1)
                ->default(50)
                ->required(),
            Forms\Components\Toggle::make('gonder')
                ->label('Hemen gondermeyi dene')
                ->helperText('SMS/WhatsApp icin webhook ayari yoksa kayit kuyrukta kalir; e-posta mail altyapisini kullanir.')
                ->default(false),
            Forms\Components\Toggle::make('tekrar_izinli')
                ->label('Bugun tekrar gonderime izin ver')
                ->default(false),
            Forms\Components\Textarea::make('sablon')
                ->label('Mesaj sablonu')
                ->rows(8)
                ->placeholder($mesajServisi->varsayilanSablon('whatsapp'))
                ->helperText('Bos birakilirsa secili kanal icin varsayilan sablon kullanilir. Degiskenler: '.$degiskenler)
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private function tahsilatFormu(AlacakPlanTaksiti $record): array
    {
        $record->loadMissing('plan');
        $paraBirimi = strtoupper((string) ($record->plan?->para_birimi ?: 'TRY'));
        $taksitKalan = max(0, (float) $record->kalan_tutar);
        $planKalan = max((float) ($record->plan?->kalan_tutar ?? 0), $taksitKalan);
        $taksitKalanVarsayilan = number_format($taksitKalan, 2, '.', '');
        $planKalanVarsayilan = number_format($planKalan, 2, '.', '');

        return [
            Forms\Components\Radio::make('kanal')
                ->label('Tahsilat kanali')
                ->options([
                    'kasa' => 'Kasa',
                    'banka' => 'Banka',
                    'pos' => 'POS',
                ])
                ->default('kasa')
                ->inline()
                ->live()
                ->required(),
            Forms\Components\Select::make('kasa_hesap_id')
                ->label('Kasa hesabi')
                ->options(fn (): array => $this->hesapSecenekleri('kasa', (int) $record->firma_id, $paraBirimi))
                ->visible(fn (Forms\Get $get): bool => (string) $get('kanal') === 'kasa')
                ->required(fn (Forms\Get $get): bool => (string) $get('kanal') === 'kasa')
                ->searchable(),
            Forms\Components\Select::make('banka_hesap_id')
                ->label('Banka hesabi')
                ->options(fn (): array => $this->hesapSecenekleri('banka', (int) $record->firma_id, $paraBirimi))
                ->visible(fn (Forms\Get $get): bool => (string) $get('kanal') === 'banka')
                ->required(fn (Forms\Get $get): bool => (string) $get('kanal') === 'banka')
                ->searchable(),
            Forms\Components\Select::make('pos_hesap_id')
                ->label('POS hesabi')
                ->options(fn (): array => $this->hesapSecenekleri('pos', (int) $record->firma_id, $paraBirimi))
                ->visible(fn (Forms\Get $get): bool => (string) $get('kanal') === 'pos')
                ->required(fn (Forms\Get $get): bool => (string) $get('kanal') === 'pos')
                ->searchable(),
            Forms\Components\Radio::make('tahsilat_kapsami')
                ->label('Tahsilat kapsami')
                ->options([
                    'taksit' => 'Secili taksit',
                    'plan' => 'Planin kalani',
                    'ozel' => 'Ozel tutar',
                ])
                ->default('taksit')
                ->inline()
                ->live()
                ->afterStateUpdated(function (?string $state, Forms\Set $set) use ($taksitKalanVarsayilan, $planKalanVarsayilan): void {
                    if ($state === 'taksit') {
                        $set('tutar', $taksitKalanVarsayilan);
                    }
                    if ($state === 'plan') {
                        $set('tutar', $planKalanVarsayilan);
                    }
                })
                ->required()
                ->columnSpanFull(),
            Forms\Components\TextInput::make('tutar')
                ->label('Tahsilat tutari')
                ->numeric()
                ->default($taksitKalanVarsayilan)
                ->minValue(0.01)
                ->maxValue($planKalan)
                ->step('0.01')
                ->required(),
            Forms\Components\DateTimePicker::make('tarih')
                ->label('Tahsilat tarihi')
                ->default(now())
                ->native(false)
                ->seconds(false)
                ->required(),
            Forms\Components\Textarea::make('aciklama')
                ->label('Aciklama')
                ->default(fn (): string => 'Vade tahsilati - '.$this->kaynakMetni($record).' / Taksit #'.$record->sira_no)
                ->rows(2)
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private function topluTahsilatFormu(): array
    {
        $firmaId = $this->aktifFirmaId();

        return [
            Forms\Components\Radio::make('kanal')
                ->label('Tahsilat kanali')
                ->options([
                    'kasa' => 'Kasa',
                    'banka' => 'Banka',
                    'pos' => 'POS',
                ])
                ->default('kasa')
                ->inline()
                ->live()
                ->required(),
            Forms\Components\Select::make('kasa_hesap_id')
                ->label('Kasa hesabi')
                ->options(fn (): array => $this->hesapSecenekleriTumParaBirimleri('kasa', $firmaId))
                ->visible(fn (Forms\Get $get): bool => (string) $get('kanal') === 'kasa')
                ->required(fn (Forms\Get $get): bool => (string) $get('kanal') === 'kasa')
                ->searchable(),
            Forms\Components\Select::make('banka_hesap_id')
                ->label('Banka hesabi')
                ->options(fn (): array => $this->hesapSecenekleriTumParaBirimleri('banka', $firmaId))
                ->visible(fn (Forms\Get $get): bool => (string) $get('kanal') === 'banka')
                ->required(fn (Forms\Get $get): bool => (string) $get('kanal') === 'banka')
                ->searchable(),
            Forms\Components\Select::make('pos_hesap_id')
                ->label('POS hesabi')
                ->options(fn (): array => $this->hesapSecenekleriTumParaBirimleri('pos', $firmaId))
                ->visible(fn (Forms\Get $get): bool => (string) $get('kanal') === 'pos')
                ->required(fn (Forms\Get $get): bool => (string) $get('kanal') === 'pos')
                ->searchable(),
            Forms\Components\Radio::make('tahsilat_tipi')
                ->label('Tahsilat tutari')
                ->options([
                    'secili_kalan' => 'Secili vadelerin kalani',
                    'ozel' => 'Ozel tutar',
                ])
                ->default('secili_kalan')
                ->inline()
                ->live()
                ->required()
                ->columnSpanFull(),
            Forms\Components\TextInput::make('tutar')
                ->label('Ozel tahsilat tutari')
                ->numeric()
                ->minValue(0.01)
                ->step('0.01')
                ->visible(fn (Forms\Get $get): bool => (string) $get('tahsilat_tipi') === 'ozel')
                ->required(fn (Forms\Get $get): bool => (string) $get('tahsilat_tipi') === 'ozel'),
            Forms\Components\DateTimePicker::make('tarih')
                ->label('Tahsilat tarihi')
                ->default(now())
                ->native(false)
                ->seconds(false)
                ->required(),
            Forms\Components\Textarea::make('aciklama')
                ->label('Aciklama')
                ->default('Toplu vade tahsilati')
                ->rows(2)
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private function planKapamaFormu(AlacakPlanTaksiti $record): array
    {
        $record->loadMissing('plan');
        $plan = $record->plan;
        $paraBirimi = strtoupper((string) ($plan?->para_birimi ?: 'TRY'));
        $planKalan = number_format(max(0, (float) ($plan?->kalan_tutar ?? $record->kalan_tutar)), 2, '.', '');

        return [
            Forms\Components\Placeholder::make('plan_kalan_bilgi')
                ->label('Plan kalani')
                ->content($this->para($planKalan, $paraBirimi))
                ->columnSpanFull(),
            Forms\Components\TextInput::make('indirim_tutari')
                ->label('Erken kapama indirimi')
                ->numeric()
                ->default('0.00')
                ->minValue(0)
                ->maxValue((float) $planKalan)
                ->step('0.01')
                ->suffix($paraBirimi)
                ->live()
                ->required(),
            Forms\Components\Radio::make('kanal')
                ->label('Tahsilat kanali')
                ->options([
                    'kasa' => 'Kasa',
                    'banka' => 'Banka',
                    'pos' => 'POS',
                ])
                ->default('kasa')
                ->inline()
                ->live()
                ->required(),
            Forms\Components\Select::make('kasa_hesap_id')
                ->label('Kasa hesabi')
                ->options(fn (): array => $this->hesapSecenekleri('kasa', (int) $record->firma_id, $paraBirimi))
                ->visible(fn (Forms\Get $get): bool => (string) $get('kanal') === 'kasa')
                ->required(fn (Forms\Get $get): bool => (string) $get('kanal') === 'kasa')
                ->searchable(),
            Forms\Components\Select::make('banka_hesap_id')
                ->label('Banka hesabi')
                ->options(fn (): array => $this->hesapSecenekleri('banka', (int) $record->firma_id, $paraBirimi))
                ->visible(fn (Forms\Get $get): bool => (string) $get('kanal') === 'banka')
                ->required(fn (Forms\Get $get): bool => (string) $get('kanal') === 'banka')
                ->searchable(),
            Forms\Components\Select::make('pos_hesap_id')
                ->label('POS hesabi')
                ->options(fn (): array => $this->hesapSecenekleri('pos', (int) $record->firma_id, $paraBirimi))
                ->visible(fn (Forms\Get $get): bool => (string) $get('kanal') === 'pos')
                ->required(fn (Forms\Get $get): bool => (string) $get('kanal') === 'pos')
                ->searchable(),
            Forms\Components\DateTimePicker::make('tarih')
                ->label('Tahsilat tarihi')
                ->default(now())
                ->native(false)
                ->seconds(false)
                ->required(),
            Forms\Components\Textarea::make('kapama_notu')
                ->label('Kapama notu')
                ->default('Vade takibi uzerinden plan kapama islemi.')
                ->rows(3)
                ->minLength(10)
                ->required()
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private function csvIceAktarFormu(): array
    {
        return [
            Forms\Components\Toggle::make('ilk_satir_baslik')
                ->label('Ilk satir baslik')
                ->default(true),
            Forms\Components\Textarea::make('csv_icerik')
                ->label('CSV icerigi')
                ->rows(12)
                ->placeholder('cari_kod;plan_turu;toplam_tutar;pesinat_tutari;para_birimi;ilk_vade_tarihi;taksit_sayisi;taksit_araligi_gun;aciklama')
                ->helperText('Ayrac olarak noktalı virgul veya virgul kullanilabilir. Cari icin cari_kod veya cari_id yeterlidir.')
                ->required()
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private function topluTakipNotuFormu(): array
    {
        return [
            Forms\Components\Select::make('takip_tipi')
                ->label('Takip tipi')
                ->options([
                    'arama' => 'Telefon aramasi',
                    'whatsapp' => 'WhatsApp',
                    'sms' => 'SMS',
                    'eposta' => 'E-posta',
                    'mutabakat' => 'Mutabakat',
                    'not' => 'Ic not',
                ])
                ->default('arama')
                ->required(),
            Forms\Components\Select::make('durum')
                ->label('Sonuc / durum')
                ->options([
                    'planlandi' => 'Planlandi',
                    'ulasildi' => 'Ulasildi',
                    'ulasilamadi' => 'Ulasilamadi',
                    'odeme_sozu' => 'Odeme sozu alindi',
                    'takip_gerekli' => 'Tekrar takip gerekli',
                    'tamamlandi' => 'Tamamlandi',
                ])
                ->live()
                ->default('planlandi')
                ->required(),
            Forms\Components\DateTimePicker::make('takip_tarihi')
                ->label('Takip tarihi')
                ->default(now())
                ->native(false)
                ->seconds(false)
                ->required(),
            Forms\Components\DateTimePicker::make('sonraki_takip_tarihi')
                ->label('Sonraki takip')
                ->native(false)
                ->seconds(false),
            Forms\Components\DateTimePicker::make('odeme_sozu_tarihi')
                ->label('Odeme sozu tarihi')
                ->native(false)
                ->seconds(false)
                ->visible(fn (Forms\Get $get): bool => (string) $get('durum') === 'odeme_sozu')
                ->required(fn (Forms\Get $get): bool => (string) $get('durum') === 'odeme_sozu'),
            Forms\Components\TextInput::make('odeme_sozu_tutari')
                ->label('Odeme sozu tutari')
                ->numeric()
                ->minValue(0.01)
                ->step('0.01')
                ->visible(fn (Forms\Get $get): bool => (string) $get('durum') === 'odeme_sozu'),
            Forms\Components\Select::make('odeme_sozu_durumu')
                ->label('Odeme sozu durumu')
                ->options([
                    'bekliyor' => 'Bekliyor',
                    'kismi' => 'Kismi tahsil edildi',
                    'tutuldu' => 'Tutuldu',
                    'tutulmadi' => 'Tutulmadi',
                    'iptal' => 'Iptal',
                ])
                ->default('bekliyor')
                ->visible(fn (Forms\Get $get): bool => (string) $get('durum') === 'odeme_sozu'),
            Forms\Components\Textarea::make('not')
                ->label('Not')
                ->default('Geciken/acik vade icin tahsilat takibi planlandi.')
                ->rows(4)
                ->required()
                ->columnSpanFull(),
            Forms\Components\Textarea::make('sonuc_notu')
                ->label('Sonuc notu')
                ->rows(2)
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private function takipNotuFormu(AlacakPlanTaksiti $record): array
    {
        $record->loadMissing('plan');
        $paraBirimi = strtoupper((string) ($record->plan?->para_birimi ?: 'TRY'));

        return array_merge([
            Forms\Components\TextInput::make('beklenen_tutar')
                ->label('Beklenen tutar')
                ->numeric()
                ->default(number_format((float) $record->kalan_tutar, 2, '.', ''))
                ->minValue(0.01)
                ->maxValue(max((float) $record->kalan_tutar, 0.01))
                ->suffix($paraBirimi)
                ->step('0.01')
                ->required(),
        ], $this->topluTakipNotuFormu());
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private function planRevizyonFormu(AlacakPlanTaksiti $record): array
    {
        $record->loadMissing('plan.taksitler');
        $plan = $record->plan;
        $acikTaksitler = $plan?->taksitler
            ->filter(fn (AlacakPlanTaksiti $taksit): bool => (float) $taksit->kalan_tutar > 0
                && ! in_array((string) $taksit->durum, ['odendi', 'iptal'], true))
            ->mapWithKeys(fn (AlacakPlanTaksiti $taksit): array => [
                (int) $taksit->getKey() => '#'.(int) $taksit->sira_no.' - '.$taksit->vade_tarihi?->format('d.m.Y').' - '.$this->para((string) $taksit->kalan_tutar, (string) ($plan?->para_birimi ?: 'TRY')),
            ])
            ->all() ?? [];

        return [
            Forms\Components\Select::make('revizyon_turu')
                ->label('Revizyon turu')
                ->options([
                    'vade_ertele' => 'Acik taksitleri ertele',
                    'taksit_vade_degistir' => 'Tek taksit vadesini degistir',
                    'kalan_yeniden_taksitlendir' => 'Kalan tutari yeniden taksitlendir',
                    'kismi_yapilandir' => 'Kismi yapilandirma',
                    'vade_farki_ekle' => 'Gecikme / vade farki ekle',
                    'erken_kapama_indirimi' => 'Erken kapama indirimi uygula',
                ])
                ->default('vade_ertele')
                ->live()
                ->required(),
            Forms\Components\TextInput::make('erteleme_gun')
                ->label('Erteleme gunu')
                ->numeric()
                ->default(7)
                ->minValue(1)
                ->visible(fn (Forms\Get $get): bool => (string) $get('revizyon_turu') === 'vade_ertele')
                ->required(fn (Forms\Get $get): bool => (string) $get('revizyon_turu') === 'vade_ertele'),
            Forms\Components\Select::make('taksit_id')
                ->label('Taksit')
                ->options($acikTaksitler)
                ->searchable()
                ->visible(fn (Forms\Get $get): bool => (string) $get('revizyon_turu') === 'taksit_vade_degistir')
                ->required(fn (Forms\Get $get): bool => (string) $get('revizyon_turu') === 'taksit_vade_degistir'),
            Forms\Components\DatePicker::make('yeni_vade_tarihi')
                ->label('Yeni vade tarihi')
                ->native(false)
                ->visible(fn (Forms\Get $get): bool => (string) $get('revizyon_turu') === 'taksit_vade_degistir')
                ->required(fn (Forms\Get $get): bool => (string) $get('revizyon_turu') === 'taksit_vade_degistir'),
            Forms\Components\DatePicker::make('ilk_vade_tarihi')
                ->label('Yeni ilk vade')
                ->default(now()->addDays(30)->toDateString())
                ->native(false)
                ->visible(fn (Forms\Get $get): bool => in_array((string) $get('revizyon_turu'), ['kalan_yeniden_taksitlendir', 'kismi_yapilandir'], true))
                ->required(fn (Forms\Get $get): bool => in_array((string) $get('revizyon_turu'), ['kalan_yeniden_taksitlendir', 'kismi_yapilandir'], true)),
            Forms\Components\TextInput::make('taksit_sayisi')
                ->label('Yeni taksit sayisi')
                ->numeric()
                ->default(2)
                ->minValue(1)
                ->visible(fn (Forms\Get $get): bool => in_array((string) $get('revizyon_turu'), ['kalan_yeniden_taksitlendir', 'kismi_yapilandir'], true))
                ->required(fn (Forms\Get $get): bool => in_array((string) $get('revizyon_turu'), ['kalan_yeniden_taksitlendir', 'kismi_yapilandir'], true)),
            Forms\Components\TextInput::make('taksit_araligi_gun')
                ->label('Taksit araligi (gun)')
                ->numeric()
                ->default(30)
                ->minValue(1)
                ->visible(fn (Forms\Get $get): bool => in_array((string) $get('revizyon_turu'), ['kalan_yeniden_taksitlendir', 'kismi_yapilandir'], true))
                ->required(fn (Forms\Get $get): bool => in_array((string) $get('revizyon_turu'), ['kalan_yeniden_taksitlendir', 'kismi_yapilandir'], true)),
            Forms\Components\TextInput::make('vade_farki_tutari')
                ->label('Vade farki tutari')
                ->numeric()
                ->minValue(0.01)
                ->step('0.01')
                ->suffix(strtoupper((string) ($plan?->para_birimi ?: 'TRY')))
                ->visible(fn (Forms\Get $get): bool => (string) $get('revizyon_turu') === 'vade_farki_ekle')
                ->required(fn (Forms\Get $get): bool => (string) $get('revizyon_turu') === 'vade_farki_ekle'),
            Forms\Components\DatePicker::make('vade_farki_vade_tarihi')
                ->label('Vade farki vadesi')
                ->default(now()->toDateString())
                ->native(false)
                ->visible(fn (Forms\Get $get): bool => (string) $get('revizyon_turu') === 'vade_farki_ekle')
                ->required(fn (Forms\Get $get): bool => (string) $get('revizyon_turu') === 'vade_farki_ekle'),
            Forms\Components\TextInput::make('indirim_tutari')
                ->label('Indirim tutari')
                ->numeric()
                ->minValue(0.01)
                ->maxValue(max(0.01, (float) ($plan?->kalan_tutar ?? 0)))
                ->step('0.01')
                ->suffix(strtoupper((string) ($plan?->para_birimi ?: 'TRY')))
                ->visible(fn (Forms\Get $get): bool => (string) $get('revizyon_turu') === 'erken_kapama_indirimi')
                ->required(fn (Forms\Get $get): bool => (string) $get('revizyon_turu') === 'erken_kapama_indirimi'),
            Forms\Components\Textarea::make('aciklama')
                ->label('Revizyon notu')
                ->default('Vade takibi uzerinden plan revizyonu.')
                ->rows(3)
                ->minLength(10)
                ->required()
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private function taksitDuzenleFormu(AlacakPlanTaksiti $record): array
    {
        $record->loadMissing('plan');
        $paraBirimi = strtoupper((string) ($record->plan?->para_birimi ?: 'TRY'));

        return [
            Forms\Components\TextInput::make('yeni_tutar')
                ->label('Tutar')
                ->numeric()
                ->default((string) $record->tutar)
                ->minValue(max(0.01, (float) $record->odenen_tutar))
                ->suffix($paraBirimi)
                ->step('0.01')
                ->required(),
            Forms\Components\DatePicker::make('yeni_vade_tarihi')
                ->label('Vade tarihi')
                ->default($record->vade_tarihi?->toDateString())
                ->native(false)
                ->required(),
            Forms\Components\Textarea::make('plan_aciklama')
                ->label('Açıklama')
                ->default((string) ($record->plan?->aciklama ?? ''))
                ->rows(3)
                ->columnSpanFull(),
            Forms\Components\Textarea::make('aciklama')
                ->label('Düzenleme notu')
                ->default('Veresiye kaydı düzenlendi.')
                ->minLength(10)
                ->required()
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int|string, string>
     */
    private function hesapSecenekleri(string $tip, int $firmaId, string $paraBirimi): array
    {
        return match ($tip) {
            'kasa' => KasaHesabi::query()
                ->where('firma_id', $firmaId)
                ->where('durum', HesapDurumu::Aktif->value)
                ->where('para_birimi', $paraBirimi)
                ->orderBy('ad')
                ->pluck('ad', 'id')
                ->all(),
            'banka' => BankaHesabi::query()
                ->where('firma_id', $firmaId)
                ->where('durum', HesapDurumu::Aktif->value)
                ->where('para_birimi', $paraBirimi)
                ->orderBy('ad')
                ->pluck('ad', 'id')
                ->all(),
            'pos' => PosHesabi::query()
                ->where('firma_id', $firmaId)
                ->where('durum', HesapDurumu::Aktif->value)
                ->where('para_birimi', $paraBirimi)
                ->orderBy('ad')
                ->pluck('ad', 'id')
                ->all(),
            default => [],
        };
    }

    /**
     * @return array<int|string, string>
     */
    private function hesapSecenekleriTumParaBirimleri(string $tip, int $firmaId): array
    {
        if ($firmaId < 1) {
            return [];
        }

        $etiket = fn ($hesap): string => trim((string) $hesap->ad).' ('.strtoupper((string) ($hesap->para_birimi ?: 'TRY')).')';

        return match ($tip) {
            'kasa' => KasaHesabi::query()
                ->where('firma_id', $firmaId)
                ->where('durum', HesapDurumu::Aktif->value)
                ->orderBy('para_birimi')
                ->orderBy('ad')
                ->get()
                ->mapWithKeys(fn (KasaHesabi $hesap): array => [$hesap->id => $etiket($hesap)])
                ->all(),
            'banka' => BankaHesabi::query()
                ->where('firma_id', $firmaId)
                ->where('durum', HesapDurumu::Aktif->value)
                ->orderBy('para_birimi')
                ->orderBy('ad')
                ->get()
                ->mapWithKeys(fn (BankaHesabi $hesap): array => [$hesap->id => $etiket($hesap)])
                ->all(),
            'pos' => PosHesabi::query()
                ->where('firma_id', $firmaId)
                ->where('durum', HesapDurumu::Aktif->value)
                ->orderBy('para_birimi')
                ->orderBy('ad')
                ->get()
                ->mapWithKeys(fn (PosHesabi $hesap): array => [$hesap->id => $etiket($hesap)])
                ->all(),
            default => [],
        };
    }

    /**
     * @return array<int|string, string>
     */
    private function cariSecenekleri(): array
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            return [];
        }

        return Cari::query()
            ->where('firma_id', $firmaId)
            ->where('durum', 'aktif')
            ->orderBy('ad')
            ->limit(500)
            ->get()
            ->mapWithKeys(fn (Cari $cari): array => [
                $cari->id => trim(($cari->kod ? $cari->kod.' - ' : '').$cari->ad.' ('.strtoupper((string) ($cari->para_birimi ?: 'TRY')).')'),
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function cariAramaSonuclari(string $search): array
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            return [];
        }

        return Cari::query()
            ->where('firma_id', $firmaId)
            ->where('durum', 'aktif')
            ->when(trim($search) !== '', function (Builder $query) use ($search): Builder {
                $aranan = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($search)).'%';

                return $query->where(function (Builder $q) use ($aranan): void {
                    $q->where('ad', 'like', $aranan)
                        ->orWhere('kod', 'like', $aranan)
                        ->orWhere('telefon', 'like', $aranan)
                        ->orWhere('gsm', 'like', $aranan);
                });
            })
            ->orderBy('ad')
            ->limit(50)
            ->get(['id', 'ad', 'kod', 'para_birimi'])
            ->mapWithKeys(fn (Cari $cari): array => [
                (int) $cari->id => trim(($cari->kod ? $cari->kod.' - ' : '').$cari->ad.' ('.strtoupper((string) ($cari->para_birimi ?: 'TRY')).')'),
            ])
            ->all();
    }

    private function cariEtiketi(mixed $value): ?string
    {
        $id = (int) $value;
        $firmaId = $this->aktifFirmaId();
        if ($id < 1 || $firmaId < 1) {
            return null;
        }

        $cari = Cari::query()
            ->where('firma_id', $firmaId)
            ->whereKey($id)
            ->first(['id', 'ad', 'kod', 'para_birimi']);

        return $cari
            ? trim(($cari->kod ? $cari->kod.' - ' : '').$cari->ad.' ('.strtoupper((string) ($cari->para_birimi ?: 'TRY')).')')
            : null;
    }

    private function cariParaBirimi(int $cariId): ?string
    {
        if ($cariId < 1) {
            return null;
        }

        $paraBirimi = Cari::query()
            ->where('firma_id', $this->aktifFirmaId())
            ->whereKey($cariId)
            ->value('para_birimi');

        return $paraBirimi ? strtoupper((string) $paraBirimi) : null;
    }

    private function aktifFirmaId(): int
    {
        return $this->aktifFirmaIdCache ??= (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
    }

    private function finansYetkisiVarMi(string $yetkiKodu): bool
    {
        return $this->finansYetkiCache[$yetkiKodu] ??= MuhasebeFilamentErisimYardimcisi::muhasebeYetkisiVarMi($yetkiKodu);
    }

    private function yetkiYoksaBildir(string $yetkiKodu): bool
    {
        if ($this->finansYetkisiVarMi($yetkiKodu)) {
            return false;
        }

        Notification::make()
            ->title('Bu islem icin yetkiniz yok')
            ->danger()
            ->send();

        return true;
    }

    private function manuelPlanlanacakTutar(Forms\Get $get): float
    {
        $toplam = max(0, (float) ($get('toplam_tutar') ?? 0));
        $pesinat = min($toplam, max(0, (float) ($get('pesinat_tutari') ?? 0)));
        $vadeFarkiUygula = (bool) $get('vade_farki_uygula');
        $vadeFarkiOrani = $vadeFarkiUygula
            ? max(0, (float) ($get('vade_farki_orani') ?? 0))
            : 0.0;
        $varsayilanVadeFarkiTipi = (string) ($get('plan_turu') ?? 'veresiye') === 'taksit' ? 'aylik' : 'tek_seferlik';
        $vadeFarkiTipi = in_array((string) ($get('vade_farki_tipi') ?: $varsayilanVadeFarkiTipi), ['tek_seferlik', 'aylik', 'yillik'], true)
            ? (string) ($get('vade_farki_tipi') ?: $varsayilanVadeFarkiTipi)
            : $varsayilanVadeFarkiTipi;
        $anapara = max(0, $toplam - $pesinat);
        $vadeFarki = $vadeFarkiUygula
            ? $this->manuelVadeFarkiTutari($anapara, $vadeFarkiOrani, $vadeFarkiTipi, $get)
            : 0.0;

        return round(max(0, $toplam + $vadeFarki - $pesinat), 2);
    }

    private function manuelVadeFarkiTutari(float $anapara, float $oran, string $tip, Forms\Get $get): float
    {
        if ($anapara <= 0 || $oran <= 0) {
            return 0.0;
        }

        if ($tip === 'tek_seferlik') {
            return round($anapara * ($oran / 100), 2);
        }

        $taksitSayisi = (string) ($get('plan_turu') ?? 'veresiye') === 'taksit'
            ? max(1, (int) ($get('taksit_sayisi') ?? 1))
            : 1;
        $aralikGun = max(1, (int) ($get('taksit_araligi_gun') ?? 30));
        $baslangic = Carbon::today();
        $ilkVade = Carbon::parse((string) ($get('ilk_vade_tarihi') ?? now()->addDays(30)->toDateString()))->startOfDay();
        $taksitTutari = $anapara / max(1, $taksitSayisi);
        $toplam = 0.0;

        for ($index = 0; $index < $taksitSayisi; $index++) {
            $vade = $ilkVade->copy()->addDays($aralikGun * $index);
            $gun = max(0, $baslangic->diffInDays($vade, false));
            $donem = $tip === 'aylik' ? ($gun / 30) : ($gun / 365);
            $toplam += $taksitTutari * ($oran / 100) * $donem;
        }

        return round($toplam, 2);
    }

    /**
     * @param array<string,mixed> $data
     */
    private function manuelPlanOlustur(array $data): void
    {
        if ($this->yetkiYoksaBildir(MuhasebeYetkiSablonlari::FINANS_OLUSTUR)) {
            return;
        }

        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            Notification::make()
                ->title('Aktif firma bulunamadi')
                ->danger()
                ->send();

            return;
        }

        try {
            $plan = app(AlacakPlanServisi::class)->olustur($firmaId, [
                'cari_id' => (int) ($data['cari_id'] ?? 0),
                'kaynak_turu' => 'manuel',
                'plan_turu' => (string) ($data['plan_turu'] ?? 'veresiye'),
                'toplam_tutar' => (string) ($data['toplam_tutar'] ?? '0'),
                'pesinat_tutari' => (string) ($data['pesinat_tutari'] ?? '0'),
                'vade_farki_uygula' => (bool) ($data['vade_farki_uygula'] ?? false),
                'vade_farki_tipi' => (string) (($data['vade_farki_tipi'] ?? null) ?: ((string) ($data['plan_turu'] ?? 'veresiye') === 'taksit' ? 'aylik' : 'tek_seferlik')),
                'vade_farki_orani' => (string) ($data['vade_farki_orani'] ?? '0'),
                'para_birimi' => (string) ($data['para_birimi'] ?? $this->cariParaBirimi((int) ($data['cari_id'] ?? 0)) ?? 'TRY'),
                'baslangic_tarihi' => now()->toDateString(),
                'ilk_vade_tarihi' => $data['ilk_vade_tarihi'] ?? now()->addDays(30)->toDateString(),
                'taksit_sayisi' => (string) ($data['plan_turu'] ?? 'veresiye') === 'taksit' ? max(1, (int) ($data['taksit_sayisi'] ?? 1)) : 1,
                'taksit_araligi_gun' => (int) ($data['taksit_araligi_gun'] ?? 30),
                'aciklama' => $data['aciklama'] ?? 'Manuel alacak plani',
                'olusturan_id' => auth()->id(),
            ]);

            Notification::make()
                ->title('Alacak plani olusturuldu')
                ->body('Plan #'.(int) $plan->getKey().' vade takibine eklendi.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Alacak plani olusturulamadi')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * @param array<string,mixed> $data
     */
    private function planIptalEt(AlacakPlanTaksiti $record, array $data = []): void
    {
        if ($this->yetkiYoksaBildir(MuhasebeYetkiSablonlari::FINANS_SIL)) {
            return;
        }

        $record->loadMissing('plan');
        $plan = $record->plan;
        if (! $plan instanceof AlacakPlani) {
            Notification::make()
                ->title('Plan bulunamadi')
                ->danger()
                ->send();

            return;
        }
        if (! $this->planIptalEdilebilirMi($record)) {
            Notification::make()
                ->title('Alacak plani iptal edilemedi')
                ->body('Tahsilatli, pesinatli veya kapali planlar bu ekrandan iptal edilemez.')
                ->danger()
                ->send();

            return;
        }

        $iptalNedeni = trim((string) ($data['iptal_nedeni'] ?? ''));
        if (mb_strlen($iptalNedeni) < 10) {
            Notification::make()
                ->title('Alacak plani iptal edilemedi')
                ->body('Iptal nedeni en az 10 karakter olmalidir.')
                ->danger()
                ->send();

            return;
        }

        try {
            $onayServisi = app(AlacakPlanOnayServisi::class);
            if ($onayServisi->onayGerektirir($plan, $this->finansYetkisiVarMi(MuhasebeYetkiSablonlari::FINANS_ONAY))) {
                $onayServisi->talepOlustur(
                    $plan,
                    AlacakPlanOnayServisi::TUR_IPTAL,
                    ['iptal_nedeni' => $iptalNedeni],
                    $iptalNedeni,
                    auth()->id(),
                );

                Notification::make()
                    ->title('Plan iptali onaya gonderildi')
                    ->body('Limit ustu islem finans onayi bekliyor.')
                    ->warning()
                    ->send();

                return;
            }

            app(AlacakPlanServisi::class)->planiIptalEt($plan, 'Vade takibi iptal: '.$iptalNedeni);

            Notification::make()
                ->title('Alacak plani iptal edildi')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Alacak plani iptal edilemedi')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    private function planIptalEdilebilirMi(AlacakPlanTaksiti $record): bool
    {
        $record->loadMissing('plan');
        $plan = $record->plan;
        if (! $plan instanceof AlacakPlani) {
            return false;
        }

        $planId = (int) $plan->getKey();
        if (array_key_exists($planId, $this->planIptalEdilebilirCache)) {
            return $this->planIptalEdilebilirCache[$planId];
        }

        if (! in_array((string) $plan->durum, ['aktif', 'gecikti'], true)) {
            return $this->planIptalEdilebilirCache[$planId] = false;
        }
        if ((float) ($plan->pesinat_tutari ?? 0) > 0) {
            return $this->planIptalEdilebilirCache[$planId] = false;
        }

        return $this->planIptalEdilebilirCache[$planId] = ! $plan->tahsilatEslesmeleri()->exists()
            && ! $plan->taksitler()->where('odenen_tutar', '>', 0)->exists()
            && ! $plan->revizyonlar()->where('revizyon_turu', 'erken_kapama_indirimi')->exists();
    }

    /**
     * @param array<string,mixed> $data
     */
    private function tekilTakipNotuOlustur(AlacakPlanTaksiti $record, array $data): void
    {
        if ($this->yetkiYoksaBildir(MuhasebeYetkiSablonlari::FINANS_OLUSTUR)) {
            return;
        }

        try {
            app(AlacakTakipNotuServisi::class)->olustur($record, $data + ['olusturan_id' => auth()->id()]);

            Notification::make()
                ->title('Takip notu olusturuldu')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Takip notu olusturulamadi')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * @param array<string,mixed> $data
     */
    private function planRevizeEt(AlacakPlanTaksiti $record, array $data): void
    {
        if ($this->yetkiYoksaBildir(MuhasebeYetkiSablonlari::FINANS_GUNCELLE)) {
            return;
        }

        $record->loadMissing('plan');
        $plan = $record->plan;
        if (! $plan instanceof AlacakPlani) {
            Notification::make()
                ->title('Plan bulunamadi')
                ->danger()
                ->send();

            return;
        }
        $revizyonNotu = trim((string) ($data['aciklama'] ?? ''));
        if (mb_strlen($revizyonNotu) < 10) {
            Notification::make()
                ->title('Alacak plani revize edilemedi')
                ->body('Revizyon notu en az 10 karakter olmalidir.')
                ->danger()
                ->send();

            return;
        }
        $data['aciklama'] = $revizyonNotu;

        try {
            $onayServisi = app(AlacakPlanOnayServisi::class);
            if ($onayServisi->onayGerektirir($plan, $this->finansYetkisiVarMi(MuhasebeYetkiSablonlari::FINANS_ONAY))) {
                $onayServisi->talepOlustur(
                    $plan,
                    AlacakPlanOnayServisi::TUR_REVIZYON,
                    $data + ['olusturan_id' => auth()->id()],
                    $revizyonNotu,
                    auth()->id(),
                );

                Notification::make()
                    ->title('Plan revizyonu onaya gonderildi')
                    ->body('Limit ustu islem finans onayi bekliyor.')
                    ->warning()
                    ->send();

                return;
            }

            app(AlacakPlanServisi::class)->planiRevizeEt($plan, $data + ['olusturan_id' => auth()->id()]);

            Notification::make()
                ->title('Alacak plani revize edildi')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Alacak plani revize edilemedi')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * @param array<string,mixed> $data
     */
    private function taksitDuzenle(AlacakPlanTaksiti $record, array $data): void
    {
        if ($this->yetkiYoksaBildir(MuhasebeYetkiSablonlari::FINANS_GUNCELLE)) {
            return;
        }

        $record->loadMissing('plan');
        $plan = $record->plan;
        if (! $plan instanceof AlacakPlani) {
            Notification::make()->title('Alacak plani bulunamadi')->danger()->send();
            return;
        }

        $not = trim((string) ($data['aciklama'] ?? ''));
        if (mb_strlen($not) < 10) {
            Notification::make()->title('Veresiye kaydi duzenlenemedi')->body('Düzenleme notu en az 10 karakter olmalıdır.')->danger()->send();
            return;
        }

        try {
            app(AlacakPlanServisi::class)->planiRevizeEt($plan, [
                'revizyon_turu' => 'taksit_duzenle',
                'taksit_id' => (int) $record->getKey(),
                'yeni_tutar' => $data['yeni_tutar'] ?? null,
                'yeni_vade_tarihi' => $data['yeni_vade_tarihi'] ?? null,
                'plan_aciklama' => $data['plan_aciklama'] ?? null,
                'aciklama' => $not,
                'olusturan_id' => auth()->id(),
            ]);

            Notification::make()->title('Veresiye kaydı güncellendi')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Veresiye kaydı güncellenemedi')->body($e->getMessage())->danger()->send();
        }
    }

    public function takipNotunuKapat(int $takipNotuId): void
    {
        if ($this->yetkiYoksaBildir(MuhasebeYetkiSablonlari::FINANS_GUNCELLE)) {
            return;
        }

        try {
            $takipNotu = $this->takipNotuBul($takipNotuId);
            app(AlacakTakipNotuServisi::class)->kapat($takipNotu, 'Ajanda uzerinden tamamlandi.');

            Notification::make()->title('Takip kapatildi')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Takip kapatilamadi')->body($e->getMessage())->danger()->send();
        }
    }

    public function takipNotunuYarinaErtele(int $takipNotuId): void
    {
        if ($this->yetkiYoksaBildir(MuhasebeYetkiSablonlari::FINANS_GUNCELLE)) {
            return;
        }

        try {
            $takipNotu = $this->takipNotuBul($takipNotuId);
            app(AlacakTakipNotuServisi::class)->sonrakiTakibiAyarla($takipNotu, now()->addDay()->setTime(10, 0), 'Ajanda uzerinden yarina ertelendi.');

            Notification::make()->title('Takip yarina ertelendi')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Takip ertelenemedi')->body($e->getMessage())->danger()->send();
        }
    }

    private function takipNotuBul(int $takipNotuId): AlacakTakipNotu
    {
        return AlacakTakipNotu::query()
            ->where('firma_id', $this->aktifFirmaId())
            ->whereKey($takipNotuId)
            ->firstOrFail();
    }

    private function onayTalebiBul(int $talepId): AlacakPlanOnayTalebi
    {
        return AlacakPlanOnayTalebi::query()
            ->where('firma_id', $this->aktifFirmaId())
            ->whereKey($talepId)
            ->firstOrFail();
    }

    /**
     * @param EloquentCollection<int, AlacakPlanTaksiti> $records
     * @param array<string,mixed> $data
     */
    private function topluTakipNotuOlustur(EloquentCollection $records, array $data): void
    {
        if ($this->yetkiYoksaBildir(MuhasebeYetkiSablonlari::FINANS_OLUSTUR)) {
            return;
        }

        if ($records->isEmpty()) {
            Notification::make()
                ->title('Kayit secilmedi')
                ->warning()
                ->send();

            return;
        }

        $data['olusturan_id'] = auth()->id();
        $servis = app(AlacakTakipNotuServisi::class);
        $sonuc = $servis->topluOlustur($servis->siraliTaksitler($records), $data);
        $olusturulan = (int) ($sonuc['olusturulan'] ?? 0);
        $atlanan = (int) ($sonuc['atlanan'] ?? 0);

        if ($olusturulan < 1) {
            Notification::make()
                ->title('Takip notu olusturulamadi')
                ->body('Secili kayitlar icinde acik ve takip edilebilir vade bulunamadi.')
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title($olusturulan.' takip notu olusturuldu')
            ->body($atlanan > 0 ? $atlanan.' kapali/iptal vade atlandi.' : null)
            ->success()
            ->send();
    }

    /**
     * @param EloquentCollection<int, AlacakPlanTaksiti> $records
     * @param array<string,mixed> $data
     */
    private function topluTahsilatKaydet(EloquentCollection $records, array $data): void
    {
        if ($this->yetkiYoksaBildir(MuhasebeYetkiSablonlari::FINANS_OLUSTUR)) {
            return;
        }

        if ($records->isEmpty()) {
            Notification::make()
                ->title('Kayit secilmedi')
                ->warning()
                ->send();

            return;
        }

        try {
            $sonuc = app(AlacakOperasyonServisi::class)->topluTahsilatOlustur(
                $records->pluck('id')->map(fn ($id): int => (int) $id)->all(),
                $data + ['olusturan_id' => auth()->id()]
            );
            $paraBirimi = strtoupper((string) ($sonuc['para_birimi'] ?? 'TRY'));

            Notification::make()
                ->title('Toplu tahsilat kaydedildi')
                ->body($this->para((string) ($sonuc['tahsil_edilen_tutar'] ?? '0'), $paraBirimi).' tahsil edildi; '.(int) ($sonuc['kapatilan_taksit_adedi'] ?? 0).' vade kapandi.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Toplu tahsilat kaydedilemedi')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * @param array<string,mixed> $data
     */
    private function planKapat(AlacakPlanTaksiti $record, array $data): void
    {
        if ($this->yetkiYoksaBildir(MuhasebeYetkiSablonlari::FINANS_OLUSTUR)) {
            return;
        }

        $record->loadMissing('plan');
        $plan = $record->plan;
        if (! $plan instanceof AlacakPlani) {
            Notification::make()
                ->title('Plan bulunamadi')
                ->danger()
                ->send();

            return;
        }

        $indirimTutari = number_format((float) ($data['indirim_tutari'] ?? 0), 2, '.', '');
        $kapamaNotu = trim((string) ($data['kapama_notu'] ?? ''));
        if (bccomp($indirimTutari, '0.00', 2) === 1 && mb_strlen($kapamaNotu) < 10) {
            Notification::make()
                ->title('Plan kapatilamadi')
                ->body('Indirimli kapama icin en az 10 karakterlik not zorunludur.')
                ->danger()
                ->send();

            return;
        }

        try {
            if (bccomp($indirimTutari, '0.00', 2) === 1) {
                $onayServisi = app(AlacakPlanOnayServisi::class);
                if ($onayServisi->onayGerektirir($plan, $this->finansYetkisiVarMi(MuhasebeYetkiSablonlari::FINANS_ONAY))) {
                    $onayServisi->talepOlustur(
                        $plan,
                        AlacakPlanOnayServisi::TUR_REVIZYON,
                        [
                            'revizyon_turu' => 'erken_kapama_indirimi',
                            'indirim_tutari' => $indirimTutari,
                            'aciklama' => $kapamaNotu,
                            'olusturan_id' => auth()->id(),
                        ],
                        $kapamaNotu,
                        auth()->id(),
                    );

                    Notification::make()
                        ->title('Indirim onaya gonderildi')
                        ->body('Onaydan sonra plan kapama tahsilatini tamamlayabilirsiniz.')
                        ->warning()
                        ->send();

                    return;
                }
            }

            $sonuc = app(AlacakOperasyonServisi::class)->planiKapat($plan, $data + ['olusturan_id' => auth()->id()]);
            $tahsilat = (array) ($sonuc['tahsilat'] ?? []);
            $paraBirimi = strtoupper((string) ($tahsilat['para_birimi'] ?? $plan->para_birimi ?? 'TRY'));

            Notification::make()
                ->title('Plan kapatildi')
                ->body('Tahsilat: '.$this->para((string) ($tahsilat['tahsil_edilen_tutar'] ?? '0'), $paraBirimi).', indirim: '.$this->para((string) ($sonuc['indirim_tutari'] ?? '0'), $paraBirimi).'.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Plan kapatilamadi')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * @param array<string,mixed> $data
     */
    private function csvdenPlanlariIceAktar(array $data): void
    {
        if ($this->yetkiYoksaBildir(MuhasebeYetkiSablonlari::FINANS_OLUSTUR)) {
            return;
        }

        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            Notification::make()
                ->title('Aktif firma bulunamadi')
                ->danger()
                ->send();

            return;
        }

        try {
            $satirlar = $this->csvPlanSatirlariniCoz((string) ($data['csv_icerik'] ?? ''), (bool) ($data['ilk_satir_baslik'] ?? true));
            $olusturulan = 0;
            $hatalar = [];

            foreach ($satirlar as $index => $satir) {
                try {
                    $cari = $this->csvSatiriCarisiniBul($firmaId, $satir);
                    app(AlacakPlanServisi::class)->olustur($firmaId, [
                        'cari_id' => (int) $cari->getKey(),
                        'kaynak_turu' => 'manuel',
                        'plan_turu' => (string) ($satir['plan_turu'] ?? 'veresiye'),
                        'toplam_tutar' => (string) ($satir['toplam_tutar'] ?? '0'),
                        'pesinat_tutari' => (string) ($satir['pesinat_tutari'] ?? '0'),
                        'vade_farki_uygula' => $this->csvBool($satir['vade_farki_uygula'] ?? false),
                        'vade_farki_tipi' => (string) ($satir['vade_farki_tipi'] ?? 'tek_seferlik'),
                        'vade_farki_orani' => (string) ($satir['vade_farki_orani'] ?? '0'),
                        'para_birimi' => strtoupper((string) ($satir['para_birimi'] ?? $cari->para_birimi ?? 'TRY')),
                        'baslangic_tarihi' => now()->toDateString(),
                        'ilk_vade_tarihi' => (string) ($satir['ilk_vade_tarihi'] ?? now()->addDays(30)->toDateString()),
                        'taksit_sayisi' => max(1, (int) ($satir['taksit_sayisi'] ?? 1)),
                        'taksit_araligi_gun' => max(1, (int) ($satir['taksit_araligi_gun'] ?? 30)),
                        'aciklama' => (string) ($satir['aciklama'] ?? 'CSV alacak plani'),
                        'olusturan_id' => auth()->id(),
                    ]);
                    $olusturulan++;
                } catch (\Throwable $e) {
                    $hatalar[] = 'Satir '.($index + 1).': '.$e->getMessage();
                }
            }

            Notification::make()
                ->title($olusturulan.' plan ice aktarildi')
                ->body($hatalar !== [] ? implode(' | ', array_slice($hatalar, 0, 3)) : null)
                ->color($hatalar === [] ? 'success' : 'warning')
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('CSV ice aktarilamadi')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * @return array<int, array<string,string>>
     */
    private function csvPlanSatirlariniCoz(string $icerik, bool $ilkSatirBaslik): array
    {
        $satirMetinleri = preg_split('/\r\n|\r|\n/', trim($icerik)) ?: [];
        $satirMetinleri = array_values(array_filter($satirMetinleri, fn (string $satir): bool => trim($satir) !== ''));
        if ($satirMetinleri === []) {
            throw new \InvalidArgumentException('CSV icerigi bos.');
        }

        $delimiter = substr_count($satirMetinleri[0], ';') >= substr_count($satirMetinleri[0], ',') ? ';' : ',';
        $varsayilanBasliklar = [
            'cari_kod',
            'cari_id',
            'plan_turu',
            'toplam_tutar',
            'pesinat_tutari',
            'para_birimi',
            'ilk_vade_tarihi',
            'taksit_sayisi',
            'taksit_araligi_gun',
            'vade_farki_uygula',
            'vade_farki_tipi',
            'vade_farki_orani',
            'aciklama',
        ];

        $basliklar = $varsayilanBasliklar;
        if ($ilkSatirBaslik) {
            $basliklar = array_map(
                fn (string $baslik): string => $this->csvBaslikNormalizeEt($baslik),
                str_getcsv(array_shift($satirMetinleri), $delimiter)
            );
        }

        $satirlar = [];
        foreach ($satirMetinleri as $satirMetni) {
            $degerler = str_getcsv($satirMetni, $delimiter);
            $satir = [];
            foreach ($basliklar as $index => $baslik) {
                if ($baslik === '') {
                    continue;
                }
                $satir[$baslik] = trim((string) ($degerler[$index] ?? ''));
            }
            if (array_filter($satir, fn (string $deger): bool => $deger !== '') !== []) {
                $satirlar[] = $satir;
            }
        }

        return $satirlar;
    }

    private function csvBaslikNormalizeEt(string $baslik): string
    {
        $baslik = trim($baslik);
        $baslik = str_replace(["\xEF\xBB\xBF", ' ', '-'], ['', '_', '_'], $baslik);

        return strtolower($baslik);
    }

    /**
     * @param array<string,string> $satir
     */
    private function csvSatiriCarisiniBul(int $firmaId, array $satir): Cari
    {
        $cariId = (int) ($satir['cari_id'] ?? 0);
        $cariKod = trim((string) ($satir['cari_kod'] ?? ''));

        $sorgu = Cari::query()->where('firma_id', $firmaId);
        if ($cariId > 0) {
            $sorgu->whereKey($cariId);
        } elseif ($cariKod !== '') {
            $sorgu->where('kod', $cariKod);
        } else {
            throw new \InvalidArgumentException('Cari kodu veya cari ID zorunludur.');
        }

        $cari = $sorgu->first();
        if (! $cari instanceof Cari) {
            throw new \InvalidArgumentException('Cari bulunamadi.');
        }

        return $cari;
    }

    private function csvBool(mixed $deger): bool
    {
        return in_array(strtolower(trim((string) $deger)), ['1', 'true', 'evet', 'yes', 'var'], true);
    }

    private function hatirlatmaCacheYenile(): void
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            Notification::make()
                ->title('Aktif firma bulunamadi')
                ->danger()
                ->send();

            return;
        }

        $ozet = app(AlacakHatirlatmaServisi::class)->ozet($firmaId, 7, 10);
        Cache::put('muhasebe:vade_hatirlatma:firma:'.$firmaId, $ozet, now()->addDays(2));
        $this->hatirlatmaOzetiYuklendi = true;

        Notification::make()
            ->title('Hatirlatma ozeti yenilendi')
            ->body('Geciken: '.(int) ($ozet['geciken']['adet'] ?? 0).', bugun: '.(int) ($ozet['bugun']['adet'] ?? 0).', yaklasan: '.(int) ($ozet['yaklasan']['adet'] ?? 0).'.')
            ->success()
            ->send();
    }

    /**
     * @param array<string,mixed> $data
     */
    private function hatirlatmaGonderimleriOlustur(array $data): void
    {
        if ($this->yetkiYoksaBildir(MuhasebeYetkiSablonlari::FINANS_OLUSTUR)) {
            return;
        }

        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            Notification::make()
                ->title('Aktif firma bulunamadi')
                ->danger()
                ->send();

            return;
        }

        try {
            $sonuc = app(AlacakHatirlatmaGonderimServisi::class)->gonderimleriOlustur(
                $firmaId,
                (string) ($data['kanal'] ?? 'whatsapp'),
                max(1, (int) ($data['yaklasan_gun'] ?? 7)),
                max(1, (int) ($data['limit'] ?? 50)),
                trim((string) ($data['sablon'] ?? '')) !== '' ? (string) $data['sablon'] : null,
                (bool) ($data['gonder'] ?? false),
                (bool) ($data['tekrar_izinli'] ?? false),
            );

            Notification::make()
                ->title('Hatirlatma gonderimleri hazirlandi')
                ->body('Olusturulan: '.(int) ($sonuc['olusturulan'] ?? 0).', gonderilen: '.(int) ($sonuc['gonderilen'] ?? 0).', atlanan: '.(int) ($sonuc['atlanan'] ?? 0).', basarisiz: '.(int) ($sonuc['basarisiz'] ?? 0).'.')
                ->color(((int) ($sonuc['basarisiz'] ?? 0)) > 0 ? 'warning' : 'success')
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Hatirlatma gonderimi hazirlanamadi')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * @param array<string,mixed> $data
     */
    private function tahsilatKaydet(AlacakPlanTaksiti $record, array $data): void
    {
        if ($this->yetkiYoksaBildir(MuhasebeYetkiSablonlari::FINANS_OLUSTUR)) {
            return;
        }

        $record->loadMissing(['plan', 'cari']);
        $paraBirimi = strtoupper((string) ($record->plan?->para_birimi ?: 'TRY'));
        $tutar = number_format((float) ($data['tutar'] ?? 0), 2, '.', '');
        $planKalan = number_format(max((float) ($record->plan?->kalan_tutar ?? 0), (float) $record->kalan_tutar), 2, '.', '');
        $kanal = (string) ($data['kanal'] ?? '');
        $hesapId = match ($kanal) {
            'kasa' => (int) ($data['kasa_hesap_id'] ?? 0),
            'banka' => (int) ($data['banka_hesap_id'] ?? 0),
            'pos' => (int) ($data['pos_hesap_id'] ?? 0),
            default => 0,
        };

        if ((float) $tutar <= 0 || $hesapId < 1) {
            Notification::make()
                ->title('Tahsilat kaydedilemedi')
                ->body('Tutar ve hesap secimi zorunludur.')
                ->danger()
                ->send();

            return;
        }

        if (bccomp($tutar, $planKalan, 2) === 1) {
            Notification::make()
                ->title('Tahsilat kaydedilemedi')
                ->body('Tahsilat tutari planin kalan tutarini asamaz.')
                ->danger()
                ->send();

            return;
        }

        $servis = app(FinansHareketServisi::class);
        $sonuc = match ($kanal) {
            'kasa' => $servis->tahsilatKasadanKaydet(
                (int) $record->firma_id,
                (int) $record->cari_id,
                $hesapId,
                $tutar,
                $paraBirimi,
                $data['tarih'] ?? now(),
                $data['aciklama'] ?? null,
                'alacak_plan_taksiti',
                (int) $record->getKey(),
            ),
            'banka' => $servis->tahsilatBankadanKaydet(
                (int) $record->firma_id,
                (int) $record->cari_id,
                $hesapId,
                $tutar,
                $paraBirimi,
                $data['tarih'] ?? now(),
                $data['aciklama'] ?? null,
                'alacak_plan_taksiti',
                (int) $record->getKey(),
            ),
            'pos' => $servis->tahsilatPosKaydet(
                (int) $record->firma_id,
                (int) $record->cari_id,
                $hesapId,
                $tutar,
                $paraBirimi,
                $data['tarih'] ?? now(),
                $data['aciklama'] ?? null,
                'alacak_plan_taksiti',
                (int) $record->getKey(),
            ),
            default => null,
        };

        if (! is_array($sonuc) || ! isset($sonuc['finans'])) {
            Notification::make()
                ->title('Tahsilat kaydedilemedi')
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Tahsilat kaydedildi')
            ->body($this->para($tutar, $paraBirimi).' tutarinda tahsilat olusturuldu.')
            ->success()
            ->send();
    }

    private function aktifTaksitTahsilati(AlacakPlanTaksiti $record): ?FinansHareketi
    {
        $eslesme = AlacakTahsilatEslesmesi::query()
            ->where('alacak_plan_taksiti_id', (int) $record->getKey())
            ->whereHas('finansHareketi', fn (Builder $q) => $q->where('durum', 'aktif'))
            ->latest('id')
            ->first();

        return $eslesme?->finansHareketi;
    }

    /** @param array<string,mixed> $data */
    private function tahsilatIptalVeDuzelt(AlacakPlanTaksiti $record, array $data): void
    {
        $eski = $this->aktifTaksitTahsilati($record);
        if (! $eski) {
            Notification::make()->title('Aktif tahsilat bulunamadı')->danger()->send();

            return;
        }

        if (! $this->finansYetkisiVarMi(MuhasebeYetkiSablonlari::FINANS_GUNCELLE)) {
            return;
        }
        $eskiId = (int) $eski->getKey();
        app(FinansHareketServisi::class)->tersKayitOlustur($eski, 'Vade tahsilatı düzeltmesi');
        $this->tahsilatKaydet($record->refresh(), $data);

        $yeni = FinansHareketi::query()
            ->where('firma_id', (int) $record->firma_id)
            ->where('referans_turu', 'alacak_plan_taksiti')
            ->where('referans_id', (int) $record->getKey())
            ->where('durum', 'aktif')
            ->latest('id')
            ->first();
        if ($yeni) {
            $yeni->update(['duzeltme_kaynagi_id' => $eskiId]);
        }

        Notification::make()
            ->title('Veresiye tahsilatı iptal edilip düzeltildi')
            ->success()
            ->send();
    }
}
