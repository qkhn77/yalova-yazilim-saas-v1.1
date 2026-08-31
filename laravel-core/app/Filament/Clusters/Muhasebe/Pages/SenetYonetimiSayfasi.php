<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe as MuhasebeCluster;
use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeSayfaErisimleri;
use App\Models\Muhasebe\BankaHesabi;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\ParaBirimi;
use App\Models\Muhasebe\PosHesabi;
use App\Models\Muhasebe\Senet;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\SenetDurumu;
use App\Muhasebe\Enumlar\SenetHareketDurumu;
use App\Muhasebe\Enumlar\SenetIslemTuru;
use App\Muhasebe\Enumlar\SenetTuru;
use App\Muhasebe\Guvenlik\MuhasebeFilamentErisimYardimcisi;
use App\Muhasebe\Servisler\SenetServisi;
use App\Muhasebe\Servisler\DovizKurServisi;
use App\Services\TenantContextService;
use App\Support\MuhasebeYetkiSablonlari;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SenetYonetimiSayfasi extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;
    use MuhasebeSayfaErisimleri;

    protected static ?string $cluster = MuhasebeCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Senet';

    protected static ?string $slug = 'finans/senet';

    protected static string $view = 'filament.clusters.muhasebe.pages.senet-yonetimi';

    public function getHeading(): string|Htmlable
    {
        return 'Senet Takibi';
    }

    public function getSubheading(): ?string
    {
        return 'Senet giriş ve çıkışları belge olarak izlenir; gerçek ödeme yalnızca senet kapatılırken kaydedilir.';
    }

    public function getHeader(): ?View
    {
        return view('filament.clusters.muhasebe.pages.senet-yonetimi-header');
    }

    protected static function gerekliYetkiKodu(): string
    {
        return MuhasebeYetkiSablonlari::FINANS_GORUNTULE;
    }

    public function getSubNavigation(): array
    {
        return [];
    }

    /** @return array<int,array{etiket:string,adet:int,aciklama:string,icon:string}> */
    public function senetOzetleri(): array
    {
        $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
        $bugun = today()->toDateString();
        $aktifDurumlar = [SenetDurumu::Portfoyde->value, SenetDurumu::Verildi->value];
        $ozet = Senet::query()
            ->where('firma_id', $firmaId)
            ->selectRaw(
                'COUNT(*) as toplam, '
                .'SUM(CASE WHEN durum = ? THEN 1 ELSE 0 END) as portfoyde, '
                .'SUM(CASE WHEN durum = ? THEN 1 ELSE 0 END) as verilen, '
                .'SUM(CASE WHEN durum IN (?, ?) AND vade_tarihi < ? THEN 1 ELSE 0 END) as vadesi_gecen, '
                .'SUM(CASE WHEN durum IN (?, ?) AND vade_tarihi = ? THEN 1 ELSE 0 END) as bugun',
                [
                    SenetDurumu::Portfoyde->value,
                    SenetDurumu::Verildi->value,
                    ...$aktifDurumlar,
                    $bugun,
                    ...$aktifDurumlar,
                    $bugun,
                ],
            )
            ->first();

        return [
            ['etiket' => 'Toplam senet', 'adet' => (int) ($ozet?->toplam ?? 0), 'aciklama' => 'Kayıtlı tüm senetler', 'icon' => 'heroicon-m-document-text'],
            ['etiket' => 'Portföyde', 'adet' => (int) ($ozet?->portfoyde ?? 0), 'aciklama' => 'Aktif portföyde bulunanlar', 'icon' => 'heroicon-m-briefcase'],
            ['etiket' => 'Vadesi geçen', 'adet' => (int) ($ozet?->vadesi_gecen ?? 0), 'aciklama' => 'Vadesi geçmiş aktif senetler', 'icon' => 'heroicon-m-exclamation-triangle'],
            ['etiket' => 'Bugün vadeli', 'adet' => (int) ($ozet?->bugun ?? 0), 'aciklama' => 'Bugün vadesi gelenler', 'icon' => 'heroicon-m-calendar-days'],
            ['etiket' => 'Verilen', 'adet' => (int) ($ozet?->verilen ?? 0), 'aciklama' => 'Karşı tarafa verilenler', 'icon' => 'heroicon-m-arrow-up-tray'],
        ];
    }

    /** @return array<int, Actions\Action> */
    protected function getHeaderActions(): array
    {
        $yazmaYetkisi = fn (): bool => MuhasebeFilamentErisimYardimcisi::muhasebeYetkisiVarMi(MuhasebeYetkiSablonlari::FINANS_OLUSTUR);

        return [
            Actions\Action::make('senetGiris')
                ->label('Senet girişi')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->visible($yazmaYetkisi)
                ->form(fn (): array => $this->senetGirisFormu())
                ->action(function (array $data): void {
                    try {
                        app(SenetServisi::class)->girisKaydet((int) (app(TenantContextService::class)->aktifFirmaId() ?? 0), $data);
                        Notification::make()->title('Senet girişi kaydedildi')->success()->send();
                        $this->resetTable();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Senet girişi kaydedilemedi')->body($e->getMessage())->danger()->send();
                    }
                }),
            Actions\Action::make('senetCikisi')
                ->label('Senet çıkışı')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('warning')
                ->visible($yazmaYetkisi)
                ->form(fn (): array => $this->senetCikisiFormu())
                ->action(function (array $data): void {
                    try {
                        app(SenetServisi::class)->cikisKaydet((int) (app(TenantContextService::class)->aktifFirmaId() ?? 0), $data);
                        Notification::make()->title('Senet çıkışı kaydedildi')->success()->send();
                        $this->resetTable();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Senet çıkışı kaydedilemedi')->body($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }

    public function table(Table $table): Table
    {
        $yazmaYetkisi = fn (): bool => MuhasebeFilamentErisimYardimcisi::muhasebeYetkisiVarMi(MuhasebeYetkiSablonlari::FINANS_OLUSTUR);

        return $table
            ->heading('Senet')
            ->query(fn (): Builder => Senet::query()->with([
                'girisHareketi.cari:id,ad,kod',
                'cikisHareketi.cari:id,ad,kod',
                'tahsilatHareketi.finansHareketi:id,tur,tutar,para_birimi,durum',
                'odemeHareketi.finansHareketi:id,tur,tutar,para_birimi,durum',
            ]))
            ->columns([
                Tables\Columns\TextColumn::make('turu')
                    ->label('Tür')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof SenetTuru ? ($state === SenetTuru::Alinan ? 'Alınan' : 'Verilen') : (string) $state)
                    ->color(fn ($state): string => $state === SenetTuru::Alinan || $state === SenetTuru::Alinan->value ? 'info' : 'warning'),
                Tables\Columns\TextColumn::make('girisHareketi.cari.ad')
                    ->label('Borçlu / senedi veren')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('cikisHareketi.cari.ad')
                    ->label('Lehtar / verildiği cari')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('tutar')
                    ->label('Tutar')
                    ->formatStateUsing(fn ($state, Senet $record): string => number_format((float) $state, 2, ',', '.').' '.strtoupper((string) ($record->para_birimi ?: 'TRY')))
                    ->sortable(),
                Tables\Columns\TextColumn::make('kur')
                    ->label('Kur')
                    ->formatStateUsing(fn ($state): string => $state === null ? '—' : number_format((float) $state, 8, ',', '.'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('baz_tutar')
                    ->label('Baz tutar')
                    ->formatStateUsing(fn ($state, Senet $record): string => $state === null
                        ? '—'
                        : number_format((float) $state, 2, ',', '.').' '.strtoupper((string) ($record->baz_para_birimi ?: config('muhasebe.baz_para_birimi', 'TRY'))))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('islem_tarihi')
                    ->label('Kayıt tarihi')
                    ->state(fn (Senet $record) => ($record->turu === SenetTuru::Alinan ? $record->girisHareketi?->islem_tarihi : $record->cikisHareketi?->islem_tarihi))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('vade_tarihi')
                    ->label('Vade')
                    ->date('d.m.Y')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('vade_durumu')
                    ->label('Vade durumu')
                    ->state(fn (Senet $record): string => $this->vadeDurumu($record))
                    ->badge()
                    ->color(fn (Senet $record): string => $this->vadeDurumuRengi($record))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('durum')
                    ->label('Durum')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => match ($state instanceof SenetDurumu ? $state : SenetDurumu::tryFrom((string) $state)) {
                        SenetDurumu::Portfoyde => 'Portföyde',
                        SenetDurumu::Verildi => 'Verildi',
                        SenetDurumu::Odendi => 'Ödendi',
                        SenetDurumu::IadeEdildi => 'İade edildi',
                        SenetDurumu::ImhaEdildi => 'İmha edildi',
                        SenetDurumu::Iptal => 'İptal',
                        default => '—',
                    })
                    ->color(fn ($state): string => match ($state instanceof SenetDurumu ? $state : SenetDurumu::tryFrom((string) $state)) {
                        SenetDurumu::Portfoyde => 'info',
                        SenetDurumu::Verildi => 'warning',
                        SenetDurumu::Odendi => 'success',
                        SenetDurumu::IadeEdildi, SenetDurumu::ImhaEdildi => 'gray',
                        SenetDurumu::Iptal => 'danger',
                         default => 'gray',
                     }),
                Tables\Columns\TextColumn::make('kapanma_sekli')
                    ->label('Kapanış')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'odendi_iade' => 'Ödendi, geri verildi',
                        'odendi_imha' => 'Ödendi, imha edildi',
                        'iade_edildi' => 'İade edildi',
                        'imha_edildi' => 'İmha edildi',
                        default => '—',
                    })
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('id')
                    ->label('Finans hareketi')
                    ->formatStateUsing(fn (int $state, Senet $record): string => match ($record->tahsilatHareketi?->finansHareketi?->tur ?? $record->odemeHareketi?->finansHareketi?->tur) {
                        'tahsilat' => 'Senet tahsilatı',
                        'odeme' => 'Senet ödemesi',
                        default => '—',
                    })
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('kapanma_tarihi')
                    ->label('Kapanış tarihi')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('turu')
                    ->label('Tür')
                    ->options([
                        SenetTuru::Alinan->value => 'Alınan',
                        SenetTuru::Verilen->value => 'Verilen',
                    ]),
                Tables\Filters\SelectFilter::make('durum')
                    ->label('Durum')
                    ->options([
                        SenetDurumu::Portfoyde->value => 'Portföyde',
                        SenetDurumu::Verildi->value => 'Verildi',
                        SenetDurumu::Odendi->value => 'Ödendi',
                        SenetDurumu::IadeEdildi->value => 'İade edildi',
                        SenetDurumu::ImhaEdildi->value => 'İmha edildi',
                        SenetDurumu::Iptal->value => 'İptal',
                    ]),
                Tables\Filters\Filter::make('vade_tarihi')
                    ->label('Vade aralığı')
                    ->form([
                        Forms\Components\DatePicker::make('baslangic')->label('Başlangıç'),
                        Forms\Components\DatePicker::make('bitis')->label('Bitiş'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['baslangic'] ?? null, fn (Builder $q, $tarih): Builder => $q->whereDate('vade_tarihi', '>=', $tarih))
                        ->when($data['bitis'] ?? null, fn (Builder $q, $tarih): Builder => $q->whereDate('vade_tarihi', '<=', $tarih))),
                Tables\Filters\Filter::make('vade_durumu')
                    ->label('Vade durumu')
                    ->form([
                        Forms\Components\Select::make('durum')->label('Vade durumu')->options([
                            'gecmis' => 'Vadesi geçti',
                            'bugun' => 'Bugün',
                            'bekliyor' => 'Bekliyor',
                            'kapandi' => 'Kapandı',
                        ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $secim = (string) ($data['durum'] ?? '');
                        $aktifDurumlar = [SenetDurumu::Portfoyde->value, SenetDurumu::Verildi->value];
                        $kapaliDurumlar = [SenetDurumu::Odendi->value, SenetDurumu::IadeEdildi->value, SenetDurumu::ImhaEdildi->value, SenetDurumu::Iptal->value];

                        return match ($secim) {
                            'gecmis' => $query->whereIn('durum', $aktifDurumlar)->whereDate('vade_tarihi', '<', today()),
                            'bugun' => $query->whereIn('durum', $aktifDurumlar)->whereDate('vade_tarihi', today()),
                            'bekliyor' => $query->whereIn('durum', $aktifDurumlar)->whereDate('vade_tarihi', '>', today()),
                            'kapandi' => $query->whereIn('durum', $kapaliDurumlar),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('tahsilatEkle')
                    ->label('Tahsilat Ekle')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (Senet $record): bool => $record->turu === SenetTuru::Alinan && $record->durum === SenetDurumu::Portfoyde && $yazmaYetkisi())
                    ->modalHeading('Senet tahsilatı')
                    ->modalSubmitActionLabel('Tahsilatı kaydet')
                    ->form(fn (Senet $record): array => $this->senetTahsilatFormu($record))
                    ->action(function (Senet $record, array $data): void {
                        try {
                            app(SenetServisi::class)->tahsilatEkle($record, $data);
                            Notification::make()->title('Senet tahsilatı kaydedildi')->body('Senet ödendi; seçilen kapanış şekline göre geri verildi veya imha edildi.')->success()->send();
                            $this->resetTable();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Senet tahsilatı kaydedilemedi')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('odemeYap')
                    ->label('Senet ödemesi yap')
                    ->icon('heroicon-o-banknotes')
                    ->color('warning')
                    ->visible(fn (Senet $record): bool => $record->turu === SenetTuru::Verilen && $record->durum === SenetDurumu::Verildi && $yazmaYetkisi())
                    ->modalHeading('Senet ödemesi')
                    ->modalSubmitActionLabel('Ödemeyi kaydet')
                    ->form(fn (Senet $record): array => $this->senetOdemeFormu($record, true))
                    ->action(function (Senet $record, array $data): void {
                        try {
                            app(SenetServisi::class)->odemeYap($record, $data);
                            Notification::make()->title('Senet ödemesi kaydedildi ve senet kapatıldı')->success()->send();
                            $this->resetTable();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Senet ödemesi kaydedilemedi')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('odemesizKapat')
                    ->label('Ödemesiz iade / imha')
                    ->icon('heroicon-o-archive-box')
                    ->color('gray')
                    ->visible(fn (Senet $record): bool => in_array($record->durum, [SenetDurumu::Portfoyde, SenetDurumu::Verildi], true) && $yazmaYetkisi())
                    ->form(fn (): array => $this->senetOdemesizKapatmaFormu())
                    ->action(function (Senet $record, array $data): void {
                        try {
                            app(SenetServisi::class)->odemesizKapat($record, $data);
                            Notification::make()->title('Senet ödeme olmadan kapatıldı')->success()->send();
                            $this->resetTable();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Senet kapatılamadı')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('iptal')
                    ->label('İptal et')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Senet $record): bool => $record->durum !== SenetDurumu::Iptal && MuhasebeFilamentErisimYardimcisi::muhasebeYetkisiVarMi(MuhasebeYetkiSablonlari::FINANS_SIL))
                    ->action(function (Senet $record): void {
                        try {
                            app(SenetServisi::class)->iptalEt($record);
                            Notification::make()->title('Senet hareketi ters kayıtla iptal edildi')->success()->send();
                            $this->resetTable();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Senet iptal edilemedi')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('iptalVeDuzelt')
                    ->label('İptal et ve düzelt')
                    ->icon('heroicon-o-arrow-path')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Senet hareketi iptal edilip düzeltilecek')
                    ->modalDescription('Aktif senet hareketi terslenir ve yeni tahsilat/ödeme hareketi oluşturulur.')
                    ->visible(fn (Senet $record): bool => $this->senetHareketDuzeltilebilirMi($record)
                        && MuhasebeFilamentErisimYardimcisi::muhasebeYetkisiVarMi(MuhasebeYetkiSablonlari::FINANS_GUNCELLE))
                    ->form(fn (Senet $record): array => $this->senetHareketTuru($record) === SenetIslemTuru::Tahsilat
                        ? $this->senetTahsilatFormu($record)
                        : $this->senetOdemeFormu($record, true))
                    ->action(function (Senet $record, array $data): void {
                        try {
                            app(SenetServisi::class)->hareketIptalEtVeDuzelt($record, $data);
                            Notification::make()->title('Senet hareketi iptal edilip düzeltildi')->success()->send();
                            $this->resetTable();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Senet düzeltilemedi')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('detay')
                    ->label('Detay')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (Senet $record): string => 'Senet detayı — '.$record->senet_no)
                    ->modalWidth('5xl')
                    ->modalContent(function (Senet $record) {
                        $senet = $record->load([
                            'girisHareketi.cari',
                            'cikisHareketi.cari',
                            'olusturanKullanici',
                            'sorumluKullanici',
                            'kapatmaKullanici',
                        ]);

                        return view('filament.clusters.muhasebe.pages.senet-detay', [
                            'senet' => $senet,
                            'vadeDurumu' => $this->vadeDurumu($senet),
                            'onGorselUrl' => $senet->on_gorsel_yolu ? Storage::disk('public')->url($senet->on_gorsel_yolu) : null,
                            'arkaGorselUrl' => $senet->arka_gorsel_yolu ? Storage::disk('public')->url($senet->arka_gorsel_yolu) : null,
                        ]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Kapat'),
                Tables\Actions\Action::make('hareketGecmisi')
                    ->label('Hareket geçmişi')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->modalHeading(fn (Senet $record): string => 'Senet hareket geçmişi — '.$record->senet_no)
                    ->modalWidth('5xl')
                    ->modalContent(function (Senet $record) {
                        $firmaId = (int) $record->firma_id;

                        return view('filament.clusters.muhasebe.pages.senet-hareket-gecmisi', [
                            'senet' => $record->load([
                                'hareketleri.cari',
                                'hareketleri.islemYapanKullanici',
                                'hareketleri.finansHareketi',
                                'hareketleri.finansHareketi.kasaHareketleri.kasaHesabi' => fn ($query) => $query->where('firma_id', $firmaId),
                                'hareketleri.finansHareketi.bankaHareketleri.bankaHesabi' => fn ($query) => $query->where('firma_id', $firmaId),
                                'hareketleri.finansHareketi.posHareketleri.posHesabi' => fn ($query) => $query->where('firma_id', $firmaId),
                            ]),
                        ]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Kapat'),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Senet kaydı yok')
            ->emptyStateDescription('Henüz senet giriş veya çıkış kaydı bulunmuyor.')
            ->deferLoading()
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    /** @return array<int, Forms\Components\Component> */
    private function senetGirisFormu(): array
    {
        return [
            Forms\Components\Select::make('cari_id')
                ->label('Senedi veren cari')
                ->required()
                ->searchable()
                ->options(fn (): array => $this->cariAramaSonuclari(''))
                ->optionsLimit(50)
                ->getSearchResultsUsing(fn (string $search): array => $this->cariAramaSonuclari($search))
                ->getOptionLabelUsing(fn ($value): ?string => $this->cariEtiketi($value))
                ->createOptionForm($this->hizliCariFormu())
                ->createOptionUsing(fn (array $data): int => $this->hizliCariOlustur($data))
                ->live()
                ->afterStateUpdated(function ($state, Forms\Set $set): void {
                    $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
                    $cari = Cari::query()->where('firma_id', $firmaId)->whereKey((int) $state)->first();
                    if ($cari) {
                        $set('para_birimi', strtoupper((string) ($cari->para_birimi ?: 'TRY')));
                    }
                }),
            Forms\Components\TextInput::make('senet_no')->label('Senet no')->required()->maxLength(80),
            Forms\Components\TextInput::make('duzenleme_yeri')->label('Düzenleme yeri')->maxLength(160),
            Forms\Components\TextInput::make('odeme_yeri')->label('Ödeme yeri')->maxLength(160),
            Forms\Components\TextInput::make('avalist_adi')->label('Avalist / kefil')->maxLength(160),
            Forms\Components\TextInput::make('tutar')->label('Tutar')->numeric()->required()->minValue(0.01)->step('0.01'),
            Forms\Components\Select::make('para_birimi')->label('Para birimi')->options(fn (): array => $this->paraBirimiSecenekleri())->required()->default('TRY'),
            Forms\Components\DatePicker::make('duzenleme_tarihi')->label('Düzenleme tarihi')->native(false),
            Forms\Components\DatePicker::make('vade_tarihi')->label('Vade tarihi')->native(false)->required(),
            Forms\Components\DateTimePicker::make('islem_tarihi')->label('Giriş tarihi')->native(false)->seconds(false)->required()->default(now()),
            ...$this->gorselAlanlari('muhasebe/senetler'),
            Forms\Components\Textarea::make('aciklama')->label('Açıklama')->rows(2)->maxLength(2000)->columnSpanFull(),
        ];
    }

    /** @return array<int, Forms\Components\Component> */
    private function senetCikisiFormu(): array
    {
        return [
            Forms\Components\Radio::make('kaynak')
                ->label('Senet kaynağı')
                ->options(['kendi' => 'İşletmenin kendi senedi', 'portfoy' => 'Portföydeki alınan senet'])
                ->default('kendi')->required()->inline()->live(),
            Forms\Components\Select::make('senet_id')
                ->label('Portföy senedi')
                ->options(fn (): array => $this->portfoySenetSecenekleri())
                ->searchable()
                ->visible(fn (Get $get): bool => ($get('kaynak') ?? 'kendi') === 'portfoy')
                ->required(fn (Get $get): bool => ($get('kaynak') ?? 'kendi') === 'portfoy'),
            Forms\Components\Select::make('cari_id')
                ->label('Senedin verildiği cari')
                ->required()
                ->searchable()
                ->options(fn (): array => $this->cariAramaSonuclari(''))
                ->optionsLimit(50)
                ->getSearchResultsUsing(fn (string $search): array => $this->cariAramaSonuclari($search))
                ->getOptionLabelUsing(fn ($value): ?string => $this->cariEtiketi($value))
                ->createOptionForm($this->hizliCariFormu())
                ->createOptionUsing(fn (array $data): int => $this->hizliCariOlustur($data)),
            Forms\Components\TextInput::make('senet_no')->label('Senet no')->visible(fn (Get $get): bool => ($get('kaynak') ?? 'kendi') === 'kendi')->required(fn (Get $get): bool => ($get('kaynak') ?? 'kendi') === 'kendi')->maxLength(80),
            Forms\Components\TextInput::make('duzenleme_yeri')->label('Düzenleme yeri')->visible(fn (Get $get): bool => ($get('kaynak') ?? 'kendi') === 'kendi')->maxLength(160),
            Forms\Components\TextInput::make('odeme_yeri')->label('Ödeme yeri')->visible(fn (Get $get): bool => ($get('kaynak') ?? 'kendi') === 'kendi')->maxLength(160),
            Forms\Components\TextInput::make('avalist_adi')->label('Avalist / kefil')->visible(fn (Get $get): bool => ($get('kaynak') ?? 'kendi') === 'kendi')->maxLength(160),
            Forms\Components\TextInput::make('tutar')->label('Tutar')->numeric()->visible(fn (Get $get): bool => ($get('kaynak') ?? 'kendi') === 'kendi')->required(fn (Get $get): bool => ($get('kaynak') ?? 'kendi') === 'kendi')->minValue(0.01)->step('0.01'),
            Forms\Components\Select::make('para_birimi')->label('Para birimi')->options(fn (): array => $this->paraBirimiSecenekleri())->visible(fn (Get $get): bool => ($get('kaynak') ?? 'kendi') === 'kendi')->required(fn (Get $get): bool => ($get('kaynak') ?? 'kendi') === 'kendi')->default('TRY'),
            Forms\Components\DatePicker::make('duzenleme_tarihi')->label('Düzenleme tarihi')->native(false)->visible(fn (Get $get): bool => ($get('kaynak') ?? 'kendi') === 'kendi'),
            Forms\Components\DatePicker::make('vade_tarihi')->label('Vade tarihi')->native(false)->visible(fn (Get $get): bool => ($get('kaynak') ?? 'kendi') === 'kendi')->required(fn (Get $get): bool => ($get('kaynak') ?? 'kendi') === 'kendi'),
            Forms\Components\DateTimePicker::make('islem_tarihi')->label('Çıkış tarihi')->native(false)->seconds(false)->required()->default(now()),
            ...$this->gorselAlanlari('muhasebe/senetler', fn (Get $get): bool => ($get('kaynak') ?? 'kendi') === 'kendi'),
            Forms\Components\Textarea::make('aciklama')->label('Açıklama')->rows(2)->maxLength(2000)->columnSpanFull(),
        ];
    }

    /** @return array<int, Forms\Components\Component> */
    private function senetOdemeFormu(Senet $record, bool $kendi): array
    {
        return [
            Forms\Components\Placeholder::make('_senet_bilgi')->label('Senet')->content($record->senet_no.' — '.number_format((float) $record->tutar, 2, ',', '.').' '.strtoupper((string) $record->para_birimi)),
            Forms\Components\Placeholder::make('_cari_bilgi')->label($kendi ? 'Lehtar / cari' : 'Borçlu / senedi veren')->content((string) (($kendi ? $record->cikisHareketi?->cari?->ad : $record->girisHareketi?->cari?->ad) ?: '—')),
            Forms\Components\Placeholder::make('_hareket_turu')->label('Hareket türü')->content($kendi ? 'Senet ödemesi' : 'Senet tahsilatı'),
            Forms\Components\Hidden::make('kaynak_para_birimi')->default(strtoupper((string) ($record->para_birimi ?: 'TRY')))->dehydrated(),
            Forms\Components\Radio::make('kanal')
                ->label($kendi ? 'Ödeme kanalı' : 'Tahsilat kanalı')
                ->options($kendi ? ['kasa' => 'Kasa', 'banka' => 'Banka'] : ['kasa' => 'Kasa', 'banka' => 'Banka', 'pos' => 'POS'])
                ->default('kasa')->required()->inline()->live()
                ->afterStateUpdated(function (Forms\Set $set): void {
                    foreach (['kasa_hesap_id', 'banka_hesap_id', 'pos_hesap_id', 'hedef_para_birimi', 'doviz_kuru', 'hedef_tutar'] as $alan) {
                        $set($alan, null);
                    }
                    $set('doviz_kuru_turu', 'otomatik');
                }),
            Forms\Components\Placeholder::make('_kaynak_para_birimi')->label('Senet para birimi')->content(strtoupper((string) ($record->para_birimi ?: 'TRY'))),
            Forms\Components\Select::make('kasa_hesap_id')
                ->label('Kasa hesabı')->options(fn (): array => $this->hesapSecenekleri('kasa'))
                ->visible(fn (Get $get): bool => ($get('kanal') ?? 'kasa') === 'kasa')
                ->required(fn (Get $get): bool => ($get('kanal') ?? 'kasa') === 'kasa')->searchable()->live()
                ->afterStateUpdated(fn ($state, Forms\Set $set, Get $get) => $this->hedefParaBirimiGuncelle('kasa', (int) $state, $set, $get)),
            Forms\Components\Select::make('banka_hesap_id')
                ->label('Banka hesabı')->options(fn (): array => $this->hesapSecenekleri('banka'))
                ->visible(fn (Get $get): bool => ($get('kanal') ?? 'kasa') === 'banka')
                ->required(fn (Get $get): bool => ($get('kanal') ?? 'kasa') === 'banka')->searchable()->live()
                ->afterStateUpdated(fn ($state, Forms\Set $set, Get $get) => $this->hedefParaBirimiGuncelle('banka', (int) $state, $set, $get)),
            Forms\Components\Select::make('pos_hesap_id')
                ->label('POS hesabı')->options(fn (): array => $this->hesapSecenekleri('pos'))
                ->visible(fn (Get $get): bool => ($get('kanal') ?? 'kasa') === 'pos')
                ->required(fn (Get $get): bool => ($get('kanal') ?? 'kasa') === 'pos')->searchable()->live()
                ->afterStateUpdated(fn ($state, Forms\Set $set, Get $get) => $this->hedefParaBirimiGuncelle('pos', (int) $state, $set, $get)),
            Forms\Components\TextInput::make('hedef_para_birimi')->label('Hesap para birimi')->disabled()->dehydrated()->placeholder('Hesap seçin'),
            Forms\Components\TextInput::make('tutar')->label($kendi ? 'Ödeme tutarı' : 'Tahsilat tutarı')->default(number_format((float) $record->tutar, 2, '.', ''))->numeric()->required()->disabled()->dehydrated()->minValue(0.01),
            Forms\Components\Radio::make('doviz_kuru_turu')
                ->label('Kur türü')->options(['otomatik' => 'Otomatik çek', 'manuel' => 'Manuel'])
                ->default('otomatik')->inline()->live()
                ->visible(fn (Get $get): bool => $this->farkliParaBirimiSeciliMi($get))
                ->afterStateUpdated(fn (Get $get, Forms\Set $set) => $this->otomatikKurDoldur($get, $set)),
            Forms\Components\TextInput::make('doviz_kuru')
                ->label('Kur')->numeric()->step('0.00000001')->minValue(0.00000001)
                ->helperText(fn (Get $get): string => $this->kurGosterimMetni($get))
                ->live(onBlur: true)->afterStateUpdated(fn (Get $get, Forms\Set $set) => $this->hedefTutarGuncelle($get, $set))
                ->visible(fn (Get $get): bool => $this->farkliParaBirimiSeciliMi($get))
                ->required(fn (Get $get): bool => $this->farkliParaBirimiSeciliMi($get))
                ->suffixAction(Forms\Components\Actions\Action::make('kur_cek')->label('Kur çek')->icon('heroicon-o-bolt')->color('warning')->action(fn (Get $get, Forms\Set $set) => $this->otomatikKurDoldur($get, $set))),
            Forms\Components\TextInput::make('hedef_tutar')
                ->label('Hesap tutarı')->numeric()->step('0.01')->minValue(0.01)->live(onBlur: true)
                ->afterStateUpdated(fn (Get $get, Forms\Set $set) => $this->kurGuncelleHedefTutardan($get, $set))
                ->visible(fn (Get $get): bool => $this->farkliParaBirimiSeciliMi($get)),
            Forms\Components\Select::make('kapanma_sekli')->label('Kapanış şekli')->options($kendi
                ? ['odendi_iade' => 'Ödeme yapıldı, senet geri alındı', 'odendi_imha' => 'Ödeme yapıldı, senet imha edildi']
                : ['odendi_iade' => 'Tahsil edildi, senet geri verildi', 'odendi_imha' => 'Tahsil edildi, senet imha edildi'])
                ->required()->default('odendi_iade'),
            Forms\Components\DateTimePicker::make('islem_tarihi')
                ->label($kendi ? 'Ödeme tarihi' : 'Tahsilat tarihi')->native(false)->seconds(false)->required()->default(now())
                ->live()->afterStateUpdated(fn (Get $get, Forms\Set $set) => $this->otomatikKurDoldur($get, $set)),
            Forms\Components\Textarea::make('aciklama')->label('Açıklama')->rows(2)->maxLength(2000)->columnSpanFull(),
        ];
    }

    /** @return array<int, Forms\Components\Component> */
    private function senetTahsilatFormu(Senet $record): array
    {
        return $this->senetOdemeFormu($record, false);
    }

    private function senetHareketTuru(Senet $record): ?SenetIslemTuru
    {
        $hareket = $record->hareketleri()
            ->where('durum', SenetHareketDurumu::Aktif->value)
            ->latest('id')
            ->first();

        return $hareket?->islem_turu instanceof SenetIslemTuru
            ? $hareket->islem_turu
            : SenetIslemTuru::tryFrom((string) ($hareket?->islem_turu ?? ''));
    }

    private function senetHareketDuzeltilebilirMi(Senet $record): bool
    {
        return $this->senetHareketTuru($record) instanceof SenetIslemTuru
            && $record->durum !== SenetDurumu::Iptal;
    }

    /** @return array<int, Forms\Components\Component> */
    private function senetOdemesizKapatmaFormu(): array
    {
        return [
            Forms\Components\Radio::make('kapanma_sekli')->label('Kapanış şekli')->options(['iade_edildi' => 'İade edildi', 'imha_edildi' => 'İmha edildi'])->default('iade_edildi')->required()->inline(),
            Forms\Components\DateTimePicker::make('islem_tarihi')->label('Kapanış tarihi')->native(false)->seconds(false)->required()->default(now()),
            Forms\Components\Textarea::make('aciklama')->label('Açıklama')->rows(2)->maxLength(2000),
        ];
    }

    /** @return array<int, Forms\Components\Component> */
    private function gorselAlanlari(string $klasor, ?callable $visible = null): array
    {
        $visible ??= fn (): bool => true;
        $klasor .= '/'.(int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);

        return [
            Forms\Components\FileUpload::make('on_gorsel_yolu')
                ->label('Senet ön yüz görseli')->helperText('İsteğe bağlı. JPG, PNG veya WebP; en fazla 5 MB.')
                ->image()->disk('public')->directory($klasor)->visibility('public')->maxSize(5120)->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->imagePreviewHeight('150')->visible($visible)->columnSpanFull(),
            Forms\Components\FileUpload::make('arka_gorsel_yolu')
                ->label('Senet arka yüz görseli')->helperText('İsteğe bağlı. JPG, PNG veya WebP; en fazla 5 MB.')
                ->image()->disk('public')->directory($klasor)->visibility('public')->maxSize(5120)->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->imagePreviewHeight('150')->visible($visible)->columnSpanFull(),
        ];
    }

    /** @return array<int, Forms\Components\Component> */
    private function hizliCariFormu(): array
    {
        return [
            Forms\Components\TextInput::make('ad')
                ->label('Ad / ünvan')
                ->required()
                ->maxLength(255),
            Forms\Components\Select::make('tur')
                ->label('Cari türü')
                ->options(collect(CariTuru::cases())->mapWithKeys(fn (CariTuru $tur): array => [$tur->value => $tur->etiket()])->all())
                ->required()
                ->default(CariTuru::Musteri->value),
            Forms\Components\Select::make('para_birimi')
                ->label('Para birimi')
                ->options(fn (): array => $this->paraBirimiSecenekleri())
                ->required()
                ->default('TRY'),
        ];
    }

    private function hizliCariOlustur(array $data): int
    {
        $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
        if ($firmaId < 1) {
            throw ValidationException::withMessages(['cari_id' => 'Aktif firma bulunamadı.']);
        }

        $ad = trim((string) ($data['ad'] ?? ''));
        if ($ad === '') {
            throw ValidationException::withMessages(['ad' => 'Ad / ünvan zorunludur.']);
        }

        $tur = CariTuru::tryFrom((string) ($data['tur'] ?? ''));
        if (! $tur) {
            throw ValidationException::withMessages(['tur' => 'Geçerli bir cari türü seçin.']);
        }

        $paraBirimi = strtoupper(trim((string) ($data['para_birimi'] ?? '')));
        if ($paraBirimi === '' || ! array_key_exists($paraBirimi, $this->paraBirimiSecenekleri())) {
            throw ValidationException::withMessages(['para_birimi' => 'Geçerli bir para birimi seçin.']);
        }

        $cari = new Cari();
        $cari->firma_id = $firmaId;
        $cari->ad = $ad;
        $cari->tur = $tur;
        $cari->para_birimi = $paraBirimi;
        $cari->durum = CariDurumu::Aktif;
        // Cari::creating mevcut firma içi kod üretimini otomatik olarak çalıştırır.
        $cari->save();

        return (int) $cari->getKey();
    }

    /** @return array<int,string> */
    private function cariAramaSonuclari(string $arama): array
    {
        $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
        $aranan = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($arama)).'%';

        return Cari::query()->where('firma_id', $firmaId)->where('durum', CariDurumu::Aktif)
            ->where(fn (Builder $q): Builder => $q->where('ad', 'like', $aranan)->orWhere('kod', 'like', $aranan))
            ->orderBy('ad')->limit(50)->get(['id', 'ad', 'kod'])
            ->mapWithKeys(fn (Cari $cari): array => [$cari->id => ($cari->kod ? $cari->kod.' — ' : '').$cari->ad])->all();
    }

    private function cariEtiketi(mixed $id): ?string
    {
        $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
        $cari = Cari::query()->where('firma_id', $firmaId)->whereKey((int) $id)->first(['id', 'ad', 'kod']);

        return $cari ? (($cari->kod ? $cari->kod.' — ' : '').$cari->ad) : null;
    }

    /** @return array<int|string,string> */
    private function paraBirimiSecenekleri(): array
    {
        $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
        $secenekler = ParaBirimi::query()->gorunurFirmaIle($firmaId)->where('aktif_mi', true)->orderBy('kod')->get(['kod', 'ad'])
            ->mapWithKeys(fn (ParaBirimi $para): array => [strtoupper((string) $para->kod) => strtoupper((string) $para->kod).($para->ad ? ' — '.$para->ad : '')])->all();

        return $secenekler !== [] ? $secenekler : ['TRY' => 'TRY'];
    }

    /** @return array<int,string> */
    private function portfoySenetSecenekleri(): array
    {
        return Senet::query()->where('turu', SenetTuru::Alinan->value)->where('durum', SenetDurumu::Portfoyde->value)
            ->whereDoesntHave('cikisHareketi', fn (Builder $query): Builder => $query->where('durum', SenetHareketDurumu::Aktif->value))
            ->orderBy('vade_tarihi')->limit(100)->get(['id', 'senet_no', 'tutar', 'para_birimi', 'vade_tarihi'])
            ->mapWithKeys(fn (Senet $senet): array => [$senet->id => $senet->senet_no.' — '.number_format((float) $senet->tutar, 2, ',', '.').' '.strtoupper((string) $senet->para_birimi).' — '.optional($senet->vade_tarihi)->format('d.m.Y')])->all();
    }

    /** @return array<int,string> */
    private function hesapSecenekleri(string $kanal): array
    {
        $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
        $model = match ($kanal) {
            'kasa' => KasaHesabi::class,
            'banka' => BankaHesabi::class,
            'pos' => PosHesabi::class,
        };

        return $model::query()->where('firma_id', $firmaId)->where('durum', HesapDurumu::Aktif->value)->orderBy('ad')->get(['id', 'ad', 'para_birimi'])
            ->mapWithKeys(fn ($hesap): array => [$hesap->id => $hesap->ad.' — '.strtoupper((string) ($hesap->para_birimi ?: 'TRY'))])->all();
    }

    private function vadeDurumu(Senet $record): string
    {
        if (in_array($record->durum, [SenetDurumu::Odendi, SenetDurumu::IadeEdildi, SenetDurumu::ImhaEdildi, SenetDurumu::Iptal], true)) {
            return 'Kapandı';
        }

        if (! $record->vade_tarihi) {
            return 'Tarih yok';
        }

        $vade = Carbon::parse($record->vade_tarihi)->startOfDay();
        $bugun = now()->startOfDay();

        return $vade->lt($bugun) ? 'Vadesi geçti' : ($vade->equalTo($bugun) ? 'Bugün' : 'Bekliyor');
    }

    private function vadeDurumuRengi(Senet $record): string
    {
        return match ($this->vadeDurumu($record)) {
            'Vadesi geçti' => 'danger',
            'Bugün' => 'warning',
            'Kapandı' => 'success',
            default => 'gray',
        };
    }

    private function hedefParaBirimiGuncelle(string $kanal, int $hesapId, Forms\Set $set, Get $get): void
    {
        if ($hesapId < 1) {
            $set('hedef_para_birimi', null);
            $set('doviz_kuru', null);
            $set('hedef_tutar', null);

            return;
        }

        $hedef = $this->formHesapParaBirimi($kanal, $hesapId);
        $set('hedef_para_birimi', $hedef);
        $set('doviz_kuru', null);
        $set('hedef_tutar', null);
        $this->otomatikKurDoldur($get, $set);
    }

    private function formHesapParaBirimi(string $kanal, int $hesapId): ?string
    {
        $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
        if ($firmaId < 1 || $hesapId < 1) {
            return null;
        }

        $model = match ($kanal) {
            'kasa' => KasaHesabi::class,
            'banka' => BankaHesabi::class,
            'pos' => PosHesabi::class,
            default => null,
        };
        if ($model === null) {
            return null;
        }

        $paraBirimi = $model::query()->where('firma_id', $firmaId)->whereKey($hesapId)->value('para_birimi');

        return $paraBirimi ? strtoupper((string) $paraBirimi) : null;
    }

    private function farkliParaBirimiSeciliMi(Get $get): bool
    {
        $kaynak = strtoupper((string) ($get('kaynak_para_birimi') ?? ''));
        $hedef = strtoupper((string) ($get('hedef_para_birimi') ?? ''));

        return $kaynak !== '' && $hedef !== '' && $kaynak !== $hedef;
    }

    private function hedefTutarGuncelle(Get $get, Forms\Set $set): void
    {
        $tutar = (string) ($get('tutar') ?? '0');
        $kur = (string) ($get('doviz_kuru') ?? '0');
        $kaynak = strtoupper((string) ($get('kaynak_para_birimi') ?? ''));
        $hedef = strtoupper((string) ($get('hedef_para_birimi') ?? ''));

        if (bccomp($tutar, '0', 8) <= 0 || (float) $kur <= 0 || $kaynak === '' || $hedef === '' || $kaynak === $hedef) {
            $set('hedef_tutar', null);

            return;
        }

        $set('hedef_tutar', $kaynak === 'TRY' && $hedef !== 'TRY' ? bcdiv($tutar, $kur, 8) : bcmul($tutar, $kur, 8));
    }

    private function kurGuncelleHedefTutardan(Get $get, Forms\Set $set): void
    {
        $tutar = (string) ($get('tutar') ?? '0');
        $hedefTutar = (string) ($get('hedef_tutar') ?? '0');
        $kaynak = strtoupper((string) ($get('kaynak_para_birimi') ?? ''));
        $hedef = strtoupper((string) ($get('hedef_para_birimi') ?? ''));
        if (bccomp($tutar, '0', 8) <= 0 || bccomp($hedefTutar, '0', 8) <= 0 || $kaynak === '' || $hedef === '') {
            return;
        }

        $set('doviz_kuru', $kaynak === 'TRY' && $hedef !== 'TRY' ? bcdiv($tutar, $hedefTutar, 8) : bcdiv($hedefTutar, $tutar, 8));
    }

    private function otomatikKurDoldur(Get $get, Forms\Set $set): void
    {
        if ((string) ($get('doviz_kuru_turu') ?? 'otomatik') !== 'otomatik') {
            return;
        }

        $kaynak = strtoupper((string) ($get('kaynak_para_birimi') ?? ''));
        $hedef = strtoupper((string) ($get('hedef_para_birimi') ?? ''));
        if ($kaynak === '' || $hedef === '' || $kaynak === $hedef) {
            return;
        }

        try {
            $tarih = Carbon::parse((string) ($get('islem_tarihi') ?: now()))->toDateString();
            $kurTipi = $kaynak !== 'TRY' && $hedef === 'TRY' ? 'alis' : 'satis';
            $sonuc = app(DovizKurServisi::class)->otomatikKurGetirKurTipi($kaynak, $hedef, $tarih, $kurTipi);
            $kur = number_format((float) ($sonuc['kur'] ?? 0), 8, '.', '');
            if ($kaynak === 'TRY' && $hedef !== 'TRY' && (float) $kur > 0) {
                $kur = number_format((float) bcdiv('1', $kur, 8), 8, '.', '');
            }
            if ((float) $kur > 0) {
                $set('doviz_kuru', $kur);
                $this->hedefTutarGuncelle($get, $set);
            }
        } catch (\Throwable) {
            // Kur bulunamazsa kullanıcı manuel kur girebilir.
        }
    }

    private function kurGosterimMetni(Get $get): string
    {
        $kaynak = strtoupper((string) ($get('kaynak_para_birimi') ?? ''));
        $hedef = strtoupper((string) ($get('hedef_para_birimi') ?? ''));
        $kur = number_format((float) ($get('doviz_kuru') ?? 0), 8, '.', '');

        if ($kaynak === '' || $hedef === '' || (float) $kur <= 0) {
            return 'Hesaplamada kullanılacak kur bu alandaki değerdir.';
        }

        return 'Kullanılan kur: '.$kur.' | '.$kaynak.' → '.$hedef;
    }
}
