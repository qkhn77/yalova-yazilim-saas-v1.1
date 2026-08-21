<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe as MuhasebeCluster;
use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeSayfaErisimleri;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi;
use App\Models\Muhasebe\BankaHesabi;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\PosHesabi;
use App\Models\Restoran\RestoranAdisyonTahsilati;
use App\Models\TeknikServis\TeknikServisTahsilati;
use App\Muhasebe\Enumlar\FinansHareketDurumu;
use App\Muhasebe\Enumlar\FinansHareketTuru;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Guvenlik\MuhasebeFilamentErisimYardimcisi;
use App\Muhasebe\Servisler\FinansHareketServisi;
use App\Services\Restoran\RestoranTahsilatServisi;
use App\Support\MuhasebeYetkiSablonlari;
use App\TeknikServis\Servisler\TeknikServisTahsilatServisi;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class FinansHareketleriListesiSayfasi extends Page implements HasTable
{
    use InteractsWithTable;
    use MuhasebeSayfaErisimleri;

    private array $hesapAkislariCache = [];

    private ?bool $finansGuncelleYetkisi = null;

    private ?bool $finansIptalYetkisi = null;

    protected static ?string $cluster = MuhasebeCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Finans hareketleri';

    protected static ?string $slug = 'finans/finans-hareketleri';

    protected static string $view = 'filament.clusters.muhasebe.pages.finans-hareketleri-listesi';

    public function getTitle(): string|Htmlable
    {
        return 'Finans hareketleri';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Finans hareketleri';
    }

    public function getSubheading(): ?string
    {
        return 'Tahsilat ve ödemeler; filtrelerle daraltın.';
    }

    protected static function gerekliYetkiKodu(): string
    {
        return MuhasebeYetkiSablonlari::FINANS_GORUNTULE;
    }

    public function getSubNavigation(): array
    {
        return [];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                FinansHareketi::query()
                    ->select([
                        'id',
                        'firma_id',
                        'cari_id',
                        'tur',
                        'tarih',
                        'tutar',
                        'para_birimi',
                        'referans_turu',
                        'referans_id',
                        'iptal_edilen_hareket_id',
                        'durum',
                        'aciklama',
                    ])
                    ->with([
                        'cari:id,ad,kod',
                        'referansFaturasi:id,kaynak_tipi,fatura_no',
                        'faturaKapatmalari.fatura:id,firma_id,fatura_no',
                        'iptalEdilenHareket:id,referans_turu,referans_id,iptal_edilen_hareket_id',
                        'iptalEdilenHareket.referansFaturasi:id,kaynak_tipi',
                    ])
                    ->withExists([
                        'teknikServisTahsilatlari as teknik_servis_tahsilat_kaynagi',
                        'iptalTeknikServisTahsilatlari as teknik_servis_iptal_tahsilat_kaynagi',
                        'teknikServisMuhasebeBaglantilari as teknik_servis_baglanti_kaynagi',
                    ])
            )
            ->columns([
                Tables\Columns\TextColumn::make('tarih')
                    ->label('Tarih')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('tur')
                    ->label('Tür')
                    ->formatStateUsing(function ($state, FinansHareketi $record): string {
                        if ($record->referans_turu === 'senet') {
                            return match ($state instanceof FinansHareketTuru ? $state : FinansHareketTuru::tryFrom((string) $state)) {
                                FinansHareketTuru::Tahsilat => 'Senet tahsilatı',
                                FinansHareketTuru::Odeme => 'Senet ödemesi',
                                default => (string) ($state instanceof FinansHareketTuru ? $state->value : $state),
                            };
                        }

                        if ($state instanceof FinansHareketTuru) {
                            return match ($state) {
                                FinansHareketTuru::Tahsilat => 'Tahsilat',
                                FinansHareketTuru::Odeme => 'Ödeme',
                                default => $state->value,
                            };
                        }

                        return (string) $state;
                    })
                    ->badge()
                    ->color(function ($state): string {
                        $t = $state instanceof FinansHareketTuru ? $state : FinansHareketTuru::tryFrom((string) $state);

                        return match ($t) {
                            FinansHareketTuru::Tahsilat => 'success',
                            FinansHareketTuru::Odeme => 'warning',
                            default => 'gray',
                        };
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('modul_etiketi')
                    ->label('Modül')
                    ->badge()
                    ->getStateUsing(fn (FinansHareketi $record): string => (string) $record->modul_etiketi)
                    ->color(function (string $state): string {
                        return match ($state) {
                            'Teknik Servis' => 'info',
                            'E-Ticaret' => 'success',
                            'Barkodlu Satış' => 'warning',
                            'Fatura' => 'primary',
                            'Çek' => 'info',
                            'Senet' => 'gray',
                            default => 'gray',
                        };
                    }),
                Tables\Columns\TextColumn::make('tutar')
                    ->label('Tutar')
                    ->formatStateUsing(fn ($state, FinansHareketi $r) => number_format((float) $state, 2, ',', '.').' '.strtoupper((string) ($r->para_birimi ?: 'TRY')))
                    ->sortable(),
                Tables\Columns\TextColumn::make('kaynak_kanal')
                    ->label('Kaynak')
                    ->getStateUsing(fn (FinansHareketi $r): string => $this->kaynakMetni($r))
                    ->wrap(),
                Tables\Columns\TextColumn::make('hedef_kanal')
                    ->label('Hedef')
                    ->getStateUsing(fn (FinansHareketi $r): string => $this->hedefMetni($r))
                    ->wrap(),
                Tables\Columns\TextColumn::make('bakiye_etkisi')
                    ->label('Cari bakiye etkisi')
                    ->getStateUsing(function (FinansHareketi $r): string {
                        $t = $r->tur instanceof FinansHareketTuru ? $r->tur : FinansHareketTuru::tryFrom((string) $r->tur);
                        if ($r->referans_turu === 'senet') {
                            return match ($t) {
                                FinansHareketTuru::Tahsilat => 'Senet tahsilatı (alacak kapaması)',
                                FinansHareketTuru::Odeme => 'Senet ödemesi (borç kapaması)',
                                default => $t?->value ?? '—',
                            };
                        }
                        if ($t === FinansHareketTuru::Tahsilat) {
                            return 'Tahsilat (alacak kapaması)';
                        }
                        if ($t === FinansHareketTuru::Odeme) {
                            return 'Ödeme (borç kapaması)';
                        }

                        return $t?->value ?? '—';
                    }),
                Tables\Columns\TextColumn::make('fatura_baglantisi')
                    ->label('Fatura')
                    ->getStateUsing(function (FinansHareketi $r): ?string {
                        if ($r->referans_turu === 'fatura' && $r->referans_id) {
                            return $r->referansFaturasi?->fatura_no ?: '#'.$r->referans_id;
                        }
                        $k = $r->faturaKapatmalari->first();
                        if ($k && $k->fatura) {
                            return $k->fatura->fatura_no ?: '#'.$k->fatura_id;
                        }
                        if ($k) {
                            return '#'.$k->fatura_id;
                        }

                        return null;
                    })
                    ->url(function (FinansHareketi $r): ?string {
                        $fid = null;
                        if ($r->referans_turu === 'fatura' && $r->referans_id) {
                            $fid = (int) $r->referans_id;
                        } else {
                            $k = $r->faturaKapatmalari->first();
                            $fid = $k ? (int) $k->fatura_id : null;
                        }

                        return $fid ? FaturaKaynagi::getUrl('view', ['record' => $fid]) : null;
                    })
                    ->placeholder('—')
                    ->color('primary')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('aciklama')
                    ->label('Açıklama')
                    ->limit(35)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('durum')
                    ->label('Durum')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => ($state instanceof FinansHareketDurumu ? $state->value : (string) $state) === FinansHareketDurumu::Iptal->value ? 'İptal edildi' : 'Aktif')
                    ->color(fn ($state): string => ($state instanceof FinansHareketDurumu ? $state->value : (string) $state) === FinansHareketDurumu::Iptal->value ? 'danger' : 'success'),
            ])
            ->recordClasses(fn (FinansHareketi $record): string => ($record->durum instanceof FinansHareketDurumu ? $record->durum : FinansHareketDurumu::tryFrom((string) $record->durum)) === FinansHareketDurumu::Iptal
                ? 'bg-danger-50/60 text-danger-900 line-through decoration-danger-500 decoration-2 dark:bg-danger-500/10 dark:text-danger-100'
                : '')
            ->defaultSort('tarih', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('tur')
                    ->label('Tür')
                    ->options([
                        FinansHareketTuru::Tahsilat->value => 'Tahsilat',
                        FinansHareketTuru::Odeme->value => 'Ödeme',
                        FinansHareketTuru::Virman->value => 'Virman',
                        FinansHareketTuru::Mahsup->value => 'Mahsup',
                    ]),
                Tables\Filters\SelectFilter::make('cari_id')
                    ->label('Cari')
                    ->relationship('cari', 'ad')
                    ->searchable(),
                Tables\Filters\Filter::make('tarih')
                    ->form([
                        Forms\Components\DatePicker::make('bas')
                            ->label('Başlangıç'),
                        Forms\Components\DatePicker::make('bit')
                            ->label('Bitiş'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['bas'] ?? null, fn (Builder $q, $d) => $q->where('tarih', '>=', (string) $d.' 00:00:00'))
                            ->when($data['bit'] ?? null, fn (Builder $q, $d) => $q->where('tarih', '<=', (string) $d.' 23:59:59'));
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('detay')
                    ->label('Detay')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (FinansHareketi $record): string => 'Finans hareketi detayı #'.$record->getKey())
                    ->modalWidth('6xl')
                    ->modalContent(function (FinansHareketi $record) {
                        return view('filament.clusters.muhasebe.pages.partials.finans-hareketi-detay', [
                            'hareket' => $record->fresh([
                                'firma',
                                'cari',
                                'isletmeProjesi',
                                'islemYapanKullanici',
                                'referansFaturasi',
                                'faturaKapatmalari.fatura',
                                'iptalEdilenHareket',
                                'kasaHareketleri.kasaHesabi',
                                'bankaHareketleri.bankaHesabi',
                                'posHareketleri.posHesabi',
                            ]) ?? $record,
                        ]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Kapat'),
                Tables\Actions\Action::make('duzenle')
                    ->label('İptal et ve yeni kayıt oluştur')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->visible(fn (FinansHareketi $record): bool => $this->hareketAktifMi($record)
                        && $this->finansGuncelleYetkisiVarMi()
                        && $this->listedenDuzeltilebilirMi($record))
                    ->form(fn (FinansHareketi $record): array => $this->duzenlemeFormu($record))
                    ->fillForm(fn (FinansHareketi $record): array => $this->duzenlemeFormVerisi($record))
                    ->action(function (FinansHareketi $record, array $data): void {
                        if (! $this->hareketAktifMi($record) || ! $this->finansGuncelleYetkisiVarMi() || ! $this->listedenDuzeltilebilirMi($record)) {
                            Notification::make()
                                ->title('Bu hareket düzeltilemez')
                                ->body('Hareket, kaynağı olan modülden güncellenmelidir.')
                                ->danger()
                                ->send();

                            return;
                        }

                        if ($tahsilat = $this->teknikServisTahsilatiKaydi($record)) {
                            app(TeknikServisTahsilatServisi::class)->guncelle($tahsilat, $data);

                            Notification::make()
                                ->title('Tahsilat iptal edilip yeni kayıt oluşturuldu')
                                ->body('Yeni hareket eski hareketle ilişkilendirildi.')
                                ->success()
                                ->send();

                            return;
                        }

                        app(FinansHareketServisi::class)->finansHareketiniDuzelt(
                            $record,
                            (string) ($data['tutar'] ?? $record->tutar),
                            $data['tarih'] ?? $record->tarih,
                            $data['aciklama'] ?? null,
                        );

                        Notification::make()
                            ->title('Finans hareketi iptal edilip yeni kayıt oluşturuldu')
                            ->body('Eski hareket terslendi ve yeni temiz hareket oluşturuldu.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('iptal')
                    ->label('İptal et')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (FinansHareketi $record): bool => $this->hareketAktifMi($record)
                        && $this->finansIptalYetkisiVarMi()
                        && $this->listedenIptalEdilebilirMi($record))
                    ->requiresConfirmation()
                    ->modalHeading('Finans hareketi iptal edilsin mi?')
                    ->modalDescription('Bu işlem fiziksel silme yapmaz; ters kayıt oluşturarak hareketi iptal eder.')
                    ->modalSubmitActionLabel('İptal et')
                    ->action(function (FinansHareketi $record): void {
                        if (! $this->hareketAktifMi($record) || ! $this->finansIptalYetkisiVarMi() || ! $this->listedenIptalEdilebilirMi($record)) {
                            Notification::make()
                            ->title('Bu hareket finans listesinden iptal edilemez')
                                ->body('Hareket, kaynağı olan modül üzerinden iptal edilmelidir.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $this->listedenIptalEt($record);

                        Notification::make()
                            ->title('Finans hareketi iptal edildi')
                            ->body('İptal kaydı oluşturuldu; finansal geçmiş korundu.')
                            ->success()
                            ->send();
                    }),
            ])
            ->deferLoading()
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private function duzenlemeFormu(FinansHareketi $record): array
    {
        $tahsilat = $this->teknikServisTahsilatiKaydi($record);

        if (! $tahsilat) {
            return [
                Forms\Components\TextInput::make('tutar')
                    ->label('Doğru tutar')
                    ->numeric()
                    ->required()
                    ->minValue(0.01)
                    ->step('0.01'),
                Forms\Components\DateTimePicker::make('tarih')
                    ->label('İşlem tarihi')
                    ->required()
                    ->native(false)
                    ->seconds(false),
                Forms\Components\Textarea::make('aciklama')
                    ->label('Açıklama')
                    ->rows(3)
                    ->maxLength(1000),
            ];
        }

        $kaynakParaBirimi = strtoupper((string) ($tahsilat->kaynak_para_birimi ?: $tahsilat->teknikServisKaydi?->cari?->para_birimi ?: 'TRY'));

        return [
            Forms\Components\Radio::make('kanal')
                ->label('Tahsilat kanalı')
                ->options([
                    'kasa' => 'Kasa',
                    'banka' => 'Banka',
                    'pos' => 'POS',
                ])
                ->required()
                ->inline()
                ->live()
                ->afterStateUpdated(function (Forms\Set $set): void {
                    $set('kasa_hesap_id', null);
                    $set('banka_hesap_id', null);
                    $set('pos_hesap_id', null);
                    $set('hedef_para_birimi', null);
                    $set('doviz_kuru', null);
                    $set('hedef_tutar', null);
                }),
            Forms\Components\Select::make('kasa_hesap_id')
                ->label('Kasa hesabı')
                ->options(fn (): array => $this->tahsilatHesapSecenekleri('kasa', $tahsilat))
                ->visible(fn (Forms\Get $get): bool => ($get('kanal') ?? '') === 'kasa')
                ->required(fn (Forms\Get $get): bool => ($get('kanal') ?? '') === 'kasa')
                ->live()
                ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => $this->tahsilatHedefParaBirimiGuncelle('kasa', (int) $state, $set, $get, $kaynakParaBirimi)),
            Forms\Components\Select::make('banka_hesap_id')
                ->label('Banka hesabı')
                ->options(fn (): array => $this->tahsilatHesapSecenekleri('banka', $tahsilat))
                ->visible(fn (Forms\Get $get): bool => ($get('kanal') ?? '') === 'banka')
                ->required(fn (Forms\Get $get): bool => ($get('kanal') ?? '') === 'banka')
                ->live()
                ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => $this->tahsilatHedefParaBirimiGuncelle('banka', (int) $state, $set, $get, $kaynakParaBirimi)),
            Forms\Components\Select::make('pos_hesap_id')
                ->label('POS hesabı')
                ->options(fn (): array => $this->tahsilatHesapSecenekleri('pos', $tahsilat))
                ->visible(fn (Forms\Get $get): bool => ($get('kanal') ?? '') === 'pos')
                ->required(fn (Forms\Get $get): bool => ($get('kanal') ?? '') === 'pos')
                ->live()
                ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => $this->tahsilatHedefParaBirimiGuncelle('pos', (int) $state, $set, $get, $kaynakParaBirimi)),
            Forms\Components\Hidden::make('kaynak_para_birimi')
                ->default($kaynakParaBirimi)
                ->dehydrated(),
            Forms\Components\Placeholder::make('_kaynak_pb')
                ->label('Kaynak para birimi')
                ->content(fn (Forms\Get $get): string => strtoupper((string) ($get('kaynak_para_birimi') ?: $kaynakParaBirimi))),
            Forms\Components\TextInput::make('hedef_para_birimi')
                ->label('Hedef para birimi')
                ->disabled()
                ->dehydrated()
                ->placeholder('İlgili hesap seçin'),
            Forms\Components\TextInput::make('tutar')
                ->label('Tahsilat tutarı')
                ->numeric()
                ->required()
                ->minValue(0.01)
                ->step('0.01'),
            Forms\Components\TextInput::make('doviz_kuru')
                ->label('Kur')
                ->numeric()
                ->step('0.00000001')
                ->minValue(0.00000001)
                ->visible(fn (Forms\Get $get): bool => $this->tahsilatFarkliParaBirimiSeciliMi($get, $kaynakParaBirimi))
                ->required(fn (Forms\Get $get): bool => $this->tahsilatFarkliParaBirimiSeciliMi($get, $kaynakParaBirimi)),
            Forms\Components\TextInput::make('hedef_tutar')
                ->label('Hedef tutar')
                ->numeric()
                ->step('0.01')
                ->visible(fn (Forms\Get $get): bool => $this->tahsilatFarkliParaBirimiSeciliMi($get, $kaynakParaBirimi)),
            Forms\Components\DateTimePicker::make('tarih')
                ->label('İşlem tarihi')
                ->native(false)
                ->seconds(false)
                ->required(),
            Forms\Components\Textarea::make('aciklama')
                ->label('Açıklama')
                ->rows(2)
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function duzenlemeFormVerisi(FinansHareketi $record): array
    {
        if ($tahsilat = $this->teknikServisTahsilatiKaydi($record)) {
            $kaynakParaBirimi = strtoupper((string) ($tahsilat->kaynak_para_birimi ?: $tahsilat->teknikServisKaydi?->cari?->para_birimi ?: 'TRY'));

            return [
                'kanal' => $tahsilat->kanal,
                'kasa_hesap_id' => $tahsilat->kasa_hesap_id,
                'banka_hesap_id' => $tahsilat->banka_hesap_id,
                'pos_hesap_id' => $tahsilat->pos_hesap_id,
                'kaynak_para_birimi' => $kaynakParaBirimi,
                'hedef_para_birimi' => strtoupper((string) ($tahsilat->hedef_para_birimi ?: $kaynakParaBirimi)),
                'doviz_kuru_turu' => $tahsilat->doviz_kuru_turu ?: 'manuel',
                'doviz_kuru' => $tahsilat->doviz_kuru,
                'tutar' => $tahsilat->tutar,
                'hedef_tutar' => $tahsilat->hedef_tutar,
                'tarih' => optional($tahsilat->tarih)->format('Y-m-d H:i:s'),
                'aciklama' => $tahsilat->aciklama,
            ];
        }

        return [
            'tutar' => $record->tutar,
            'tarih' => optional($record->tarih)->format('Y-m-d H:i'),
            'aciklama' => $record->aciklama,
        ];
    }

    private function teknikServisTahsilatiKaydi(FinansHareketi $record): ?TeknikServisTahsilati
    {
        return TeknikServisTahsilati::query()
            ->with(['teknikServisKaydi.cari'])
            ->where('finans_hareketi_id', (int) $record->getKey())
            ->where('durum', 'aktif')
            ->first();
    }

    private function listedenIptalEdilebilirMi(FinansHareketi $record): bool
    {
        $referans = strtolower(trim((string) ($record->referans_turu ?? '')));

        return $referans === ''
            || in_array($referans, ['fatura', 'alacak_plan_taksiti', 'teknik_servis', 'restoran_adisyon'], true);
    }

    private function hareketAktifMi(FinansHareketi $record): bool
    {
        $durum = $record->durum instanceof FinansHareketDurumu ? $record->durum->value : (string) $record->durum;

        return $durum === FinansHareketDurumu::Aktif->value;
    }

    private function listedenDuzeltilebilirMi(FinansHareketi $record): bool
    {
        $referans = strtolower(trim((string) ($record->referans_turu ?? '')));

        if ($referans === 'teknik_servis') {
            return true;
        }

        if ($referans !== '' || (int) $record->cari_id < 1) {
            return false;
        }

        $tur = $record->tur instanceof FinansHareketTuru ? $record->tur : FinansHareketTuru::tryFrom((string) $record->tur);

        return in_array($tur, [FinansHareketTuru::Tahsilat, FinansHareketTuru::Odeme], true);
    }

    private function finansGuncelleYetkisiVarMi(): bool
    {
        return $this->finansGuncelleYetkisi ??= MuhasebeFilamentErisimYardimcisi::muhasebeYetkisiVarMi(MuhasebeYetkiSablonlari::FINANS_GUNCELLE);
    }

    private function finansIptalYetkisiVarMi(): bool
    {
        return $this->finansIptalYetkisi ??= MuhasebeFilamentErisimYardimcisi::muhasebeYetkisiVarMi(MuhasebeYetkiSablonlari::FINANS_IPTAL);
    }

    private function listedenIptalEt(FinansHareketi $record): void
    {
        if ($tahsilat = $this->teknikServisTahsilatiKaydi($record)) {
            app(TeknikServisTahsilatServisi::class)->iptalEt($tahsilat, 'Finans listesinden iptal');

            return;
        }

        $restoranTahsilati = RestoranAdisyonTahsilati::query()
            ->where('finans_hareketi_id', (int) $record->getKey())
            ->where('durum', RestoranAdisyonTahsilati::DURUM_AKTIF)
            ->first();
        if ($restoranTahsilati) {
            app(RestoranTahsilatServisi::class)->tahsilatIptalEt($restoranTahsilati, 'Finans listesinden iptal');

            return;
        }

        if (! $this->listedenIptalEdilebilirMi($record)) {
            throw new IsKuraliIstisnasi('Bu hareket kendi kaynak modülünden iptal edilmelidir.');
        }

        app(FinansHareketServisi::class)->tersKayitOlustur($record, 'Finans listesinden iptal');
    }

    /**
     * @return array<int|string, string>
     */
    private function tahsilatHesapSecenekleri(string $tip, TeknikServisTahsilati $tahsilat): array
    {
        $firmaId = (int) ($tahsilat->firma_id ?? 0);

        if ($firmaId < 1) {
            return [];
        }

        return Cache::remember(
            'muhasebe:finans-hareketleri:duzenleme-hesaplar:'.$firmaId.':'.$tip,
            now()->addSeconds(45),
            fn (): array => match ($tip) {
                'kasa' => KasaHesabi::query()
                    ->where('firma_id', $firmaId)
                    ->orderBy('ad')
                    ->get(['id', 'ad', 'para_birimi'])
                    ->mapWithKeys(fn (KasaHesabi $hesap) => [$hesap->id => $hesap->ad.' ('.strtoupper((string) ($hesap->para_birimi ?: 'TRY')).')'])
                    ->all(),
                'banka' => BankaHesabi::query()
                    ->where('firma_id', $firmaId)
                    ->orderBy('ad')
                    ->get(['id', 'ad', 'para_birimi'])
                    ->mapWithKeys(fn (BankaHesabi $hesap) => [$hesap->id => $hesap->ad.' ('.strtoupper((string) ($hesap->para_birimi ?: 'TRY')).')'])
                    ->all(),
                'pos' => PosHesabi::query()
                    ->where('firma_id', $firmaId)
                    ->orderBy('ad')
                    ->get(['id', 'ad', 'para_birimi'])
                    ->mapWithKeys(fn (PosHesabi $hesap) => [$hesap->id => $hesap->ad.' ('.strtoupper((string) ($hesap->para_birimi ?: 'TRY')).')'])
                    ->all(),
                default => [],
            }
        );
    }

    private function tahsilatHedefParaBirimiGuncelle(string $tip, int $hesapId, Forms\Set $set, Forms\Get $get, string $kaynakParaBirimi): void
    {
        $pb = $this->tahsilatHesapParaBirimi($tip, $hesapId) ?? $kaynakParaBirimi;
        $set('hedef_para_birimi', $pb);

        if ($pb === strtoupper((string) ($get('kaynak_para_birimi') ?: $kaynakParaBirimi))) {
            $set('doviz_kuru', null);
            $set('hedef_tutar', null);
        }
    }

    private function tahsilatHesapParaBirimi(string $tip, int $hesapId): ?string
    {
        if ($hesapId < 1) {
            return null;
        }

        $paraBirimi = match ($tip) {
            'kasa' => KasaHesabi::query()->whereKey($hesapId)->value('para_birimi'),
            'banka' => BankaHesabi::query()->whereKey($hesapId)->value('para_birimi'),
            'pos' => PosHesabi::query()->whereKey($hesapId)->value('para_birimi'),
            default => null,
        };

        return $paraBirimi ? strtoupper((string) $paraBirimi) : null;
    }

    private function tahsilatFarkliParaBirimiSeciliMi(Forms\Get $get, string $kaynakParaBirimi): bool
    {
        $kaynak = strtoupper((string) ($get('kaynak_para_birimi') ?: $kaynakParaBirimi));
        $hedef = strtoupper((string) ($get('hedef_para_birimi') ?? ''));

        return $kaynak !== '' && $hedef !== '' && $kaynak !== $hedef;
    }

    /**
     * @return array<int, array{yon:string, ad:string, para_birimi:string, tutar:string}>
     */
    private function hesapAkislari(FinansHareketi $r): array
    {
        $cacheKey = (int) $r->getKey();
        if (array_key_exists($cacheKey, $this->hesapAkislariCache)) {
            return $this->hesapAkislariCache[$cacheKey];
        }

        $liste = [];

        foreach ($r->kasaHareketleri as $h) {
            $liste[] = [
                'yon' => ((float) $h->tutar) < 0 ? 'cikis' : 'giris',
                'ad' => 'Kasa: '.(($h->kasaHesabi?->ad) ?: ('#'.$h->kasa_hesap_id)),
                'para_birimi' => strtoupper((string) ($h->para_birimi ?: 'TRY')),
                'tutar' => number_format(abs((float) $h->tutar), 2, ',', '.'),
            ];
        }

        foreach ($r->bankaHareketleri as $h) {
            $liste[] = [
                'yon' => ((float) $h->tutar) < 0 ? 'cikis' : 'giris',
                'ad' => 'Banka: '.(($h->bankaHesabi?->ad) ?: ('#'.$h->banka_hesap_id)),
                'para_birimi' => strtoupper((string) ($h->para_birimi ?: 'TRY')),
                'tutar' => number_format(abs((float) $h->tutar), 2, ',', '.'),
            ];
        }

        foreach ($r->posHareketleri as $h) {
            $liste[] = [
                'yon' => ((float) $h->tutar) < 0 ? 'cikis' : 'giris',
                'ad' => 'POS: '.(($h->posHesabi?->ad) ?: ('#'.$h->pos_hesap_id)),
                'para_birimi' => strtoupper((string) ($h->para_birimi ?: 'TRY')),
                'tutar' => number_format(abs((float) $h->tutar), 2, ',', '.'),
            ];
        }

        return $this->hesapAkislariCache[$cacheKey] = $liste;
    }

    private function kaynakMetni(FinansHareketi $r): string
    {
        $akimlar = $this->hesapAkislari($r);
        $cikislar = array_values(array_filter($akimlar, fn (array $x): bool => $x['yon'] === 'cikis'));
        if ($cikislar !== []) {
            return implode(' | ', array_map(fn (array $x): string => $x['ad'].' '.$x['tutar'].' '.$x['para_birimi'], $cikislar));
        }

        $tur = $r->tur instanceof FinansHareketTuru ? $r->tur : FinansHareketTuru::tryFrom((string) $r->tur);
        if ($tur === FinansHareketTuru::Tahsilat && $r->cari) {
            return 'Cari: '.$r->cari->ad.' '.number_format(abs((float) $r->tutar), 2, ',', '.').' '.strtoupper((string) ($r->para_birimi ?: 'TRY'));
        }

        return '—';
    }

    private function hedefMetni(FinansHareketi $r): string
    {
        $akimlar = $this->hesapAkislari($r);
        $girisler = array_values(array_filter($akimlar, fn (array $x): bool => $x['yon'] === 'giris'));
        if ($girisler !== []) {
            return implode(' | ', array_map(fn (array $x): string => $x['ad'].' '.$x['tutar'].' '.$x['para_birimi'], $girisler));
        }

        $tur = $r->tur instanceof FinansHareketTuru ? $r->tur : FinansHareketTuru::tryFrom((string) $r->tur);
        if ($tur === FinansHareketTuru::Odeme && $r->cari) {
            return 'Cari: '.$r->cari->ad.' '.number_format(abs((float) $r->tutar), 2, ',', '.').' '.strtoupper((string) ($r->para_birimi ?: 'TRY'));
        }

        return '—';
    }
}
