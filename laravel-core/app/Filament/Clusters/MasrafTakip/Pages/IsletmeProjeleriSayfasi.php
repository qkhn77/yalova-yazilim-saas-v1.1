<?php

namespace App\Filament\Clusters\MasrafTakip\Pages;

use App\Filament\Clusters\MasrafTakip as MasrafTakipCluster;
use App\Filament\Clusters\MasrafTakip\Kaynaklar\MasrafTakipFilamentErisimYardimcisi;
use App\Filament\Clusters\MasrafTakip\Kaynaklar\MasrafTakipSayfaErisimleri;
use App\Models\Proje\IsletmeProjesi;
use App\Models\Muhasebe\CariHareketi;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\Muhasebe\Fatura;
use App\Models\FirmaKullanici;
use App\Filament\Clusters\ProjeYonetimi\Pages\ProjeRaporlariSayfasi;
use App\Services\TenantContextService;
use App\Support\MasrafTakipYetkiSablonlari;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IsletmeProjeleriSayfasi extends Page implements HasTable
{
    use InteractsWithTable;
    use MasrafTakipSayfaErisimleri;

    protected static ?string $cluster = MasrafTakipCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'İşletme Projeleri';

    protected static ?string $slug = 'tanimlar/projeler';

    protected static string $view = 'filament.clusters.masraf-takip.pages.isletme-projeleri';

    public function mount(): void
    {
        // Eski adresi kullanan kayıtlı yer imlerini yeni bağımsız modüle taşı.
        if (static::class === self::class) {
            $this->redirect(\App\Filament\Clusters\ProjeYonetimi\Pages\IsletmeProjeleriSayfasi::getUrl());
        }
    }

    public function getHeading(): string
    {
        return 'İşletme projeleri';
    }

    public function getSubheading(): ?string
    {
        return 'Proje bütçelerini, durumlarını ve masraf gerçekleşmelerini firma bazında yönetin.';
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('projeRaporlari')
                ->label('Proje raporları')
                ->icon('heroicon-o-chart-bar-square')
                ->color('gray')
                ->visible(fn (): bool => $this->yetkiVarMi(MasrafTakipYetkiSablonlari::GORUNTULE))
                ->url(fn (): string => ProjeRaporlariSayfasi::getUrl()),
            Action::make('projeCsvDisaAktar')
                ->label('Proje raporu CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => $this->yetkiVarMi(MasrafTakipYetkiSablonlari::GORUNTULE))
                ->action(fn (): StreamedResponse => $this->projeCsvIndir()),
            Action::make('yeniProje')
                ->label('Yeni proje')
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => $this->yetkiVarMi(MasrafTakipYetkiSablonlari::OLUSTUR))
                ->form(fn (): array => $this->projeFormu())
                ->action(fn (array $data): mixed => $this->projeKaydet($data)),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->projeSorgusu())
            ->deferLoading()
            ->defaultSort('ad')
            ->columns([
                Tables\Columns\TextColumn::make('kod')
                    ->label('Kod')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('ad')
                    ->label('Proje adı')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('kullanicilar_count')
                    ->label('Kullanıcı erişimi')
                    ->formatStateUsing(fn ($state): string => (int) $state > 0 ? (int) $state.' kullanıcı' : 'Firma erişimi')
                    ->hiddenFrom('md')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('durum')
                    ->label('Durum')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        IsletmeProjesi::DURUM_AKTIF => 'Aktif',
                        IsletmeProjesi::DURUM_TAMAMLANDI => 'Tamamlandı',
                        IsletmeProjesi::DURUM_IPTAL => 'İptal',
                        default => 'Taslak',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        IsletmeProjesi::DURUM_AKTIF => 'success',
                        IsletmeProjesi::DURUM_TAMAMLANDI => 'info',
                        IsletmeProjesi::DURUM_IPTAL => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('butce_tutari')
                    ->label('Bütçe')
                    ->formatStateUsing(fn ($state, IsletmeProjesi $record): string => $this->para($state, $record->para_birimi))
                    ->placeholder('Belirtilmedi')
                    ->sortable(),
                Tables\Columns\TextColumn::make('gerceklesen_tutar')
                    ->label('Gerçekleşen')
                    ->formatStateUsing(fn ($state, IsletmeProjesi $record): string => $this->para($state, $record->para_birimi))
                    ->sortable(),
                Tables\Columns\TextColumn::make('tahsilat_tutari')
                    ->label('Gelir / tahsilat')
                    ->formatStateUsing(fn ($state, IsletmeProjesi $record): string => $this->para($state, $record->para_birimi))
                    ->hiddenFrom('md')
                    ->sortable(),
                Tables\Columns\TextColumn::make('odeme_tutari')
                    ->label('Ödeme')
                    ->formatStateUsing(fn ($state, IsletmeProjesi $record): string => $this->para($state, $record->para_birimi))
                    ->hiddenFrom('md')
                    ->sortable(),
                Tables\Columns\TextColumn::make('net_finans_tutari')
                    ->label('Net finans')
                    ->state(fn (IsletmeProjesi $record): string => $this->para(
                        bcsub((string) ($record->tahsilat_tutari ?? 0), (string) ($record->odeme_tutari ?? 0), 2),
                        $record->para_birimi,
                    ))
                    ->hiddenFrom('md')
                    ->color(fn (IsletmeProjesi $record): string => bccomp((string) ($record->tahsilat_tutari ?? 0), (string) ($record->odeme_tutari ?? 0), 2) >= 0 ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('kalan_butce')
                    ->label('Kalan bütçe')
                    ->state(fn (IsletmeProjesi $record): ?string => $record->butce_tutari === null
                        ? null
                        : $this->para((float) $record->butce_tutari - (float) ($record->gerceklesen_tutar ?? 0), $record->para_birimi))
                    ->placeholder('—')
                    ->hiddenFrom('md')
                    ->color(fn (IsletmeProjesi $record): string => $record->butce_tutari !== null
                        && (float) ($record->gerceklesen_tutar ?? 0) > (float) $record->butce_tutari ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('baslangic_tarihi')
                    ->label('Başlangıç')
                    ->date('d.m.Y')
                    ->placeholder('—')
                    ->hiddenFrom('md')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('bitis_tarihi')
                    ->label('Bitiş')
                    ->date('d.m.Y')
                    ->placeholder('—')
                    ->hiddenFrom('md')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\Action::make('projeRaporu')
                    ->label('Proje raporu')
                    ->icon('heroicon-o-chart-bar-square')
                    ->url(fn (IsletmeProjesi $record): string => \App\Filament\Clusters\ProjeYonetimi\Pages\ProjeRaporlariSayfasi::getUrl([
                        'proje_id' => $record->getKey(),
                    ])),
                Tables\Actions\Action::make('masraflariGoruntule')
                    ->label('Masrafları görüntüle')
                    ->icon('heroicon-o-receipt-percent')
                    ->modalHeading(fn (IsletmeProjesi $record): string => $record->ad.' — masraflar')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Kapat')
                    ->modalContent(fn (IsletmeProjesi $record) => view(
                        'filament.clusters.masraf-takip.pages.isletme-proje-masraflari',
                        [
                            'proje' => $record,
                            'masraflar' => $record->masraflar()
                                ->with('kategori')
                                ->where('firma_id', $this->aktifFirmaId() ?? 0)
                                ->orderByDesc('tarih')
                                ->orderByDesc('id')
                                ->limit(50)
                                ->get(),
                            'faturalar' => Fatura::query()
                                ->where('firma_id', $this->aktifFirmaId() ?? 0)
                                ->where('isletme_proje_id', $record->getKey())
                                ->with('cari:id,ad')
                                ->latest('tarih')
                                ->limit(50)
                                ->get(['id', 'cari_id', 'fatura_no', 'tarih', 'genel_toplam', 'para_birimi', 'tur', 'durum']),
                            'finansHareketleri' => FinansHareketi::query()
                                ->where('firma_id', $this->aktifFirmaId() ?? 0)
                                ->where('isletme_proje_id', $record->getKey())
                                ->with('cari:id,ad')
                                ->latest('tarih')
                                ->limit(50)
                                ->get(['id', 'cari_id', 'tur', 'tarih', 'tutar', 'para_birimi', 'aciklama', 'durum']),
                            'cariHareketleri' => CariHareketi::query()
                                ->where('firma_id', $this->aktifFirmaId() ?? 0)
                                ->where('isletme_proje_id', $record->getKey())
                                ->with('cari:id,ad')
                                ->latest('islem_tarihi')
                                ->limit(50)
                                ->get(['id', 'cari_id', 'belge_turu', 'islem_tarihi', 'borc', 'alacak', 'para_birimi', 'durum']),
                            'finansOzetleri' => $this->finansOzetleri($record),
                        ],
                    )),
                Tables\Actions\Action::make('duzenle')
                    ->label('Düzenle')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (): bool => $this->yetkiVarMi(MasrafTakipYetkiSablonlari::GUNCELLE))
                    ->fillForm(fn (IsletmeProjesi $record): array => array_merge($record->only([
                        'kod', 'ad', 'durum', 'baslangic_tarihi', 'bitis_tarihi', 'butce_tutari', 'para_birimi', 'aciklama',
                    ]), ['kullanici_ids' => $record->kullanicilar()->pluck('users.id')->all()]))
                    ->form(fn (): array => $this->projeFormu())
                    ->action(fn (IsletmeProjesi $record, array $data): mixed => $this->projeKaydet($data, (int) $record->getKey())),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('durum')
                    ->label('Durum')
                    ->options([
                        IsletmeProjesi::DURUM_TASLAK => 'Taslak',
                        IsletmeProjesi::DURUM_AKTIF => 'Aktif',
                        IsletmeProjesi::DURUM_TAMAMLANDI => 'Tamamlandı',
                        IsletmeProjesi::DURUM_IPTAL => 'İptal',
                    ]),
                Tables\Filters\Filter::make('proje_tarihleri')
                    ->label('Proje tarihleri')
                    ->form([
                        Forms\Components\DatePicker::make('baslangic')
                            ->label('Başlangıçtan itibaren')
                            ->native(false),
                        Forms\Components\DatePicker::make('bitis')
                            ->label('Bitişe kadar')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['baslangic'] ?? null, fn (Builder $q, $tarih): Builder => $q->whereDate('baslangic_tarihi', '>=', $tarih))
                            ->when($data['bitis'] ?? null, fn (Builder $q, $tarih): Builder => $q->whereDate('bitis_tarihi', '<=', $tarih));
                    }),
            ])
            ->emptyStateHeading('İşletme projesi bulunamadı.')
            ->emptyStateDescription('Yeni bir proje ekleyerek başlayabilirsiniz.')
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    /** @return array<int, Forms\Components\Component> */
    private function projeFormu(): array
    {
        return [
            Forms\Components\TextInput::make('ad')
                ->label('Proje adı')
                ->required()
                ->maxLength(160),
            Forms\Components\Select::make('durum')
                ->label('Durum')
                ->options([
                    IsletmeProjesi::DURUM_TASLAK => 'Taslak',
                    IsletmeProjesi::DURUM_AKTIF => 'Aktif',
                    IsletmeProjesi::DURUM_TAMAMLANDI => 'Tamamlandı',
                    IsletmeProjesi::DURUM_IPTAL => 'İptal',
                ])
                ->default(IsletmeProjesi::DURUM_TASLAK)
                ->required()
                ->native(false),
            Forms\Components\TextInput::make('butce_tutari')
                ->label('Bütçe')
                ->numeric()
                ->minValue(0)
                ->step('0.01'),
            Forms\Components\Select::make('para_birimi')
                ->label('Para birimi')
                ->options(['TRY' => '₺ Türk Lirası', 'USD' => '$ Amerikan Doları', 'EUR' => '€ Euro', 'GBP' => '£ İngiliz Sterlini'])
                ->default('TRY')
                ->required()
                ->native(false),
            Forms\Components\Select::make('kullanici_ids')
                ->label('Projeyi görebilecek kullanıcılar')
                ->multiple()
                ->searchable()
                ->options(fn (): array => FirmaKullanici::query()
                    ->where('firma_id', $this->aktifFirmaId() ?? 0)
                    ->where('durum', 'aktif')
                    ->with('kullanici:id,name,ad_soyad')
                    ->get()
                    ->mapWithKeys(fn (FirmaKullanici $kayit): array => [$kayit->kullanici_id => $kayit->kullanici?->ad_soyad ?: ($kayit->kullanici?->name ?: 'Kullanıcı #'.$kayit->kullanici_id)])
                    ->all())
                ->helperText('Boş bırakırsanız firma yetkisi olan kullanıcılar görür.'),
            Forms\Components\DatePicker::make('baslangic_tarihi')
                ->label('Başlangıç tarihi')
                ->native(false),
            Forms\Components\DatePicker::make('bitis_tarihi')
                ->label('Bitiş tarihi')
                ->native(false),
            Forms\Components\Textarea::make('aciklama')
                ->label('Açıklama')
                ->rows(3)
                ->maxLength(4000)
                ->columnSpanFull(),
        ];
    }

    /** @param array<string, mixed> $data */
    private function projeKaydet(array $data, ?int $projeId = null): mixed
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId === null) {
            return $this->uyariGoster('Aktif firma bulunamadı', 'Proje kaydetmek için önce aktif firma seçin.');
        }

        $ad = trim((string) ($data['ad'] ?? ''));
        if ($ad === '') {
            return $this->uyariGoster('Proje kaydedilemedi', 'Proje adı zorunludur.');
        }

        $kullaniciIds = collect($data['kullanici_ids'] ?? [])->map(fn ($id): int => (int) $id)->filter()->values()->all();
        unset($data['kullanici_ids']);
        $gecerliKullaniciIds = FirmaKullanici::query()
            ->where('firma_id', $firmaId)
            ->where('durum', 'aktif')
            ->whereIn('kullanici_id', $kullaniciIds)
            ->pluck('kullanici_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $proje = $projeId === null
            ? new IsletmeProjesi()
            : IsletmeProjesi::query()->where('firma_id', $firmaId)->whereKey($projeId)->firstOrFail();
        $kod = $projeId === null ? $this->yeniProjeKodu($firmaId) : (string) $proje->kod;

        $ayni = IsletmeProjesi::query()
            ->where('firma_id', $firmaId)
            ->where('kod', $kod)
            ->when($projeId !== null, fn (Builder $query): Builder => $query->whereKeyNot($projeId))
            ->exists();
        if ($ayni) {
            return $this->uyariGoster('Proje kaydedilemedi', 'Bu proje kodu aktif firmada zaten kayıtlı.');
        }

        try {
            $proje->fill(array_merge($data, [
                'firma_id' => $firmaId,
                'kod' => $kod,
                'ad' => $ad,
                'butce_tutari' => ($data['butce_tutari'] ?? '') === '' ? null : $data['butce_tutari'],
            ]))->save();
        } catch (\Throwable $exception) {
            return $this->uyariGoster('Proje kaydedilemedi', $exception->getMessage());
        }

        $proje->kullanicilar()->sync($gecerliKullaniciIds);

        $this->resetTable();
        Notification::make()->title($projeId === null ? 'Proje eklendi' : 'Proje güncellendi')->success()->send();

        return null;
    }

    private function yeniProjeKodu(int $firmaId): string
    {
        $yil = now()->year;
        $sira = 1;

        do {
            $kod = sprintf('PRJ-%d-%03d', $yil, $sira++);
        } while (IsletmeProjesi::query()->where('firma_id', $firmaId)->where('kod', $kod)->exists());

        return $kod;
    }

    private function projeSorgusu(): Builder
    {
        $firmaId = $this->aktifFirmaId() ?? 0;
        $gerceklesen = DB::table('masraflar')
            ->where('firma_id', $firmaId)
            ->where('durum', 'aktif')
            ->select('isletme_proje_id', 'para_birimi')
            ->selectRaw('SUM(tutar) as gerceklesen_tutar')
            ->groupBy('isletme_proje_id', 'para_birimi');
        $finans = DB::table('finans_hareketleri')
            ->where('firma_id', $firmaId)
            ->where('durum', 'aktif')
            ->select('isletme_proje_id', 'para_birimi')
            ->selectRaw("SUM(CASE WHEN tur = 'tahsilat' THEN tutar ELSE 0 END) as tahsilat_tutari")
            ->selectRaw("SUM(CASE WHEN tur = 'odeme' THEN tutar ELSE 0 END) as odeme_tutari")
            ->groupBy('isletme_proje_id', 'para_birimi');

        return IsletmeProjesi::query()
            ->where('isletme_projeleri.firma_id', $firmaId)
            ->kullaniciIcinGorunur(null, $firmaId)
            ->withCount('kullanicilar')
            ->leftJoinSub($gerceklesen, 'proje_masraf_ozeti', function (JoinClause $join): void {
                $join->on('proje_masraf_ozeti.isletme_proje_id', '=', 'isletme_projeleri.id')
                    ->on('proje_masraf_ozeti.para_birimi', '=', 'isletme_projeleri.para_birimi');
            })
            ->leftJoinSub($finans, 'proje_finans_ozeti', function (JoinClause $join): void {
                $join->on('proje_finans_ozeti.isletme_proje_id', '=', 'isletme_projeleri.id')
                    ->on('proje_finans_ozeti.para_birimi', '=', 'isletme_projeleri.para_birimi');
            })
            ->select('isletme_projeleri.*')
            ->selectRaw('COALESCE(proje_masraf_ozeti.gerceklesen_tutar, 0) as gerceklesen_tutar')
            ->selectRaw('COALESCE(proje_finans_ozeti.tahsilat_tutari, 0) as tahsilat_tutari')
            ->selectRaw('COALESCE(proje_finans_ozeti.odeme_tutari, 0) as odeme_tutari');
    }

    private function aktifFirmaId(): ?int
    {
        $firmaId = app(TenantContextService::class)->aktifFirmaId();

        return $firmaId ? (int) $firmaId : null;
    }

    private function yetkiVarMi(string $yetki): bool
    {
        return MasrafTakipFilamentErisimYardimcisi::masrafTakipYetkisiVarMi($yetki);
    }

    private function para(mixed $tutar, string $paraBirimi): string
    {
        return number_format((float) ($tutar ?? 0), 8, ',', '.').' '.strtoupper($paraBirimi ?: 'TRY');
    }

    /** @return array<int, array{para_birimi:string, gelir:string, odeme:string, masraf:string, net:string}> */
    private function finansOzetleri(IsletmeProjesi $record): array
    {
        $ozet = [];
        $finanslar = $record->finansHareketleri()->where('firma_id', $this->aktifFirmaId() ?? 0)->where('durum', 'aktif')->get(['tutar', 'para_birimi', 'tur']);
        foreach ($finanslar as $hareket) {
            $pb = strtoupper((string) ($hareket->para_birimi ?: $record->para_birimi));
            $ozet[$pb] ??= ['para_birimi' => $pb, 'gelir' => '0.00', 'odeme' => '0.00', 'masraf' => '0.00', 'net' => '0.00'];
            if (($hareket->tur?->value ?? (string) $hareket->tur) === 'tahsilat') {
                $ozet[$pb]['gelir'] = bcadd($ozet[$pb]['gelir'], (string) $hareket->tutar, 2);
            } elseif (($hareket->tur?->value ?? (string) $hareket->tur) === 'odeme') {
                $ozet[$pb]['odeme'] = bcadd($ozet[$pb]['odeme'], (string) $hareket->tutar, 2);
            }
        }
        foreach ($record->masraflar()->where('firma_id', $this->aktifFirmaId() ?? 0)->where('durum', 'aktif')->get(['tutar', 'para_birimi']) as $masraf) {
            $pb = strtoupper((string) ($masraf->para_birimi ?: $record->para_birimi));
            $ozet[$pb] ??= ['para_birimi' => $pb, 'gelir' => '0.00', 'odeme' => '0.00', 'masraf' => '0.00', 'net' => '0.00'];
            $ozet[$pb]['masraf'] = bcadd($ozet[$pb]['masraf'], (string) $masraf->tutar, 2);
        }
        foreach ($ozet as &$satir) {
            $satir['net'] = bcsub($satir['gelir'], bcadd($satir['odeme'], $satir['masraf'], 2), 2);
        }

        return array_values($ozet);
    }

    private function projeCsvIndir(): StreamedResponse
    {
        $dosyaAdi = 'proje-raporu-'.now()->format('Ymd_His').'.csv';
        $sorgu = $this->projeSorgusu()->orderBy('isletme_projeleri.ad');

        return response()->streamDownload(function () use ($sorgu): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Kod', 'Proje', 'Durum', 'Bütçe', 'Gerçekleşen Masraf', 'Gelir / Tahsilat', 'Ödeme', 'Net Finans', 'Kalan Bütçe', 'Para Birimi', 'Başlangıç', 'Bitiş'], ';');
            foreach ($sorgu->cursor() as $proje) {
                $netFinans = bcsub((string) ($proje->tahsilat_tutari ?? 0), (string) ($proje->odeme_tutari ?? 0), 2);
                $kalan = $proje->butce_tutari === null
                    ? ''
                    : bcsub((string) $proje->butce_tutari, (string) ($proje->gerceklesen_tutar ?? 0), 2);
                $durum = match ((string) $proje->durum) {
                    IsletmeProjesi::DURUM_AKTIF => 'Aktif',
                    IsletmeProjesi::DURUM_TAMAMLANDI => 'Tamamlandı',
                    IsletmeProjesi::DURUM_IPTAL => 'İptal',
                    default => 'Taslak',
                };

                fputcsv($out, [
                    $proje->kod,
                    $proje->ad,
                    $durum,
                    (string) ($proje->butce_tutari ?? ''),
                    (string) ($proje->gerceklesen_tutar ?? '0.00'),
                    (string) ($proje->tahsilat_tutari ?? '0.00'),
                    (string) ($proje->odeme_tutari ?? '0.00'),
                    $netFinans,
                    $kalan,
                    strtoupper((string) $proje->para_birimi),
                    $proje->baslangic_tarihi?->format('d.m.Y'),
                    $proje->bitis_tarihi?->format('d.m.Y'),
                ], ';');
            }

            fclose($out);
        }, $dosyaAdi, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function uyariGoster(string $baslik, string $govde): void
    {
        Notification::make()->title($baslik)->body($govde)->warning()->send();
    }
}
