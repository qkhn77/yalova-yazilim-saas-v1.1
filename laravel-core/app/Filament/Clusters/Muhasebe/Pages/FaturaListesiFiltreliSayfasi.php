<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe;
use App\Filament\Clusters\Muhasebe\Concerns\FaturaListeKpiHesaplari;
use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeSayfaErisimleri;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi;
use App\Models\Muhasebe\Fatura;
use App\Models\Proje\IsletmeProjesi;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FaturaSinifi;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Muhasebe\Guvenlik\MuhasebeFilamentErisimYardimcisi;
use App\Muhasebe\Servisler\FaturaKopyalamaServisi;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Actions\Action as HeaderAction;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;

/**
 * FaturaKaynagi (tur alanı) ile eşleşecek liste ekranları için ortak üst sınıf.
 */
abstract class FaturaListesiFiltreliSayfasi extends Page implements HasTable
{
    use FaturaListeKpiHesaplari;
    use InteractsWithTable;
    use MuhasebeSayfaErisimleri;

    protected static ?string $cluster = Muhasebe::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.clusters.muhasebe.pages.fatura-listesi-filtreli';

    /** Alt sınıflar doldurur; KPI kartı etiketi için kullanılır. */
    protected static ?string $title = null;

    public bool $kpiKartlariYuklendi = false;

    protected static function gerekliYetkiKodu(): string
    {
        return MuhasebeYetkiSablonlari::FATURA_GORUNTULE;
    }

    /**
     * Gelecekte Fatura modelindeki tur alanı ile eşleşecek anahtar (ör. gelen, iade).
     */
    abstract public static function faturaTurleri(): array;

    public static function faturaDurumlari(): array
    {
        return [];
    }

    /** @return array<int, string> */
    public static function faturaSiniflari(): array
    {
        return [];
    }

    /**
     * Eski akışta doğrudan "iade" durumuna alınmış kaynak faturalar için,
     * iade listelerinde gösterilecek ana fatura türleri.
     *
     * @return array<int, string>
     */
    public static function dogrudanIadeEdilenFaturaTurleri(): array
    {
        return [];
    }

    protected static function olusturmaSayfasiAnahtari(): string
    {
        return 'create';
    }

    protected static function olusturmaButonEtiketi(): string
    {
        return 'Fatura Ekle';
    }

    protected function getHeaderActions(): array
    {
        return [
            HeaderAction::make('olustur')
                ->label(static::olusturmaButonEtiketi())
                ->icon('heroicon-m-plus')
                ->url(FaturaKaynagi::getUrl(static::olusturmaSayfasiAnahtari())),
        ];
    }

    public function getHeading(): string|Htmlable
    {
        return static::listeIslemEtiketi();
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Arama ve tablo filtreleri üstteki özet kartlarına yansır. Satıra tıklayarak fatura detayına gidebilirsiniz.';
    }

    public function getSubNavigation(): array
    {
        return [];
    }

    public function kpiKartlariniYukle(): void
    {
        $this->kpiKartlariYuklendi = true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(static::faturaSorgusu())
            ->heading(static::listeIslemEtiketi())
            ->defaultSort('tarih', 'desc')
            ->columns([
                TextColumn::make('fatura_no')
                    ->label('Fatura no')
                    ->searchable()
                    ->copyable()
                    ->placeholder(fn (Fatura $record): string => $record->belge_no ?: '#'.$record->getKey())
                    ->formatStateUsing(fn ($state, Fatura $record): string => trim((string) $state)
                        ?: trim((string) $record->belge_no)
                        ?: '#'.$record->getKey())
                    ->weight('medium'),
                TextColumn::make('tur')
                    ->badge()
                    ->formatStateUsing(function ($state): string {
                        if ($state instanceof FaturaTuru) {
                            return $state->etiket();
                        }

                        $tur = is_scalar($state) ? FaturaTuru::tryFrom((string) $state) : null;

                        if ($tur instanceof FaturaTuru) {
                            return $tur->etiket();
                        }

                        return is_scalar($state) ? (string) $state : '—';
                    })
                    ->toggleable(),
                TextColumn::make('fatura_sinifi')
                    ->label('Sınıf')
                    ->badge()
                    ->formatStateUsing(function ($state, Fatura $record): string {
                        if ($record->eskiGiderKaydiMi()) {
                            return 'Eski gider kaydı';
                        }

                        $sinif = $state instanceof FaturaSinifi
                            ? $state
                            : FaturaSinifi::tryFrom((string) $state);

                        return $sinif?->etiket() ?? '—';
                    })
                    ->color(fn (Fatura $record): string => $record->eskiGiderKaydiMi() ? 'warning' : 'gray')
                    ->tooltip(fn (Fatura $record): ?string => $record->eskiGiderKaydiMi()
                        ? 'Eski gider türü kullanıyor. Sınıfı güncellemeniz önerilir; kayıt otomatik değiştirilmedi.'
                        : null)
                    ->toggleable(),
                TextColumn::make('durum')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('odeme_durumu')
                    ->label('Ödeme')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => is_scalar($state) && (string) $state !== ''
                        ? str_replace('_', ' ', (string) $state)
                        : '—')
                    ->toggleable(),
                TextColumn::make('cari_ad')
                    ->label('Cari')
                    ->placeholder('—')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('cariler.ad', 'like', '%'.$search.'%')),
                TextColumn::make('proje_ad')
                    ->label('Proje')
                    ->placeholder('Projesiz')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where(function (Builder $projeQuery) use ($search): void {
                            $projeQuery
                                ->where('projeler.ad', 'like', '%'.$search.'%')
                                ->orWhere('projeler.kod', 'like', '%'.$search.'%');
                        }))
                    ->toggleable(),
                TextColumn::make('tarih')
                    ->label('Tarih')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('vade_tarihi')
                    ->label('Vade')
                    ->date('d.m.Y')
                    ->placeholder('—')
                    ->color(fn (Fatura $record): ?string => $record->vade_tarihi?->isPast() && bccomp((string) ($record->acik_tutar ?? '0'), '0', 2) > 0
                        ? 'danger'
                        : null)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('para_birimi')
                    ->label('PB')
                    ->badge()
                    ->placeholder('TRY')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('genel_toplam')
                    ->label('Genel toplam')
                    ->alignEnd()
                    ->money(fn (Fatura $record) => $record->para_birimi ?: 'TRY'),
                TextColumn::make('acik_tutar')
                    ->label('Açık tutar')
                    ->alignEnd()
                    ->money(fn (Fatura $record) => $record->para_birimi ?: 'TRY')
                    ->color(fn (Fatura $record): ?string => bccomp((string) ($record->acik_tutar ?? '0'), '0', 2) > 0 ? 'warning' : null),
            ])
            ->filters([
                ...(empty(static::faturaDurumlari()) ? [
                    SelectFilter::make('durum')
                        ->label('Durum')
                        ->attribute('faturalar.durum')
                        ->options(collect(FaturaDurumu::cases())->mapWithKeys(fn (FaturaDurumu $d) => [$d->value => $d->value])),
                ] : []),
                ...(empty(static::faturaTurleri()) ? [
                    SelectFilter::make('tur')
                        ->label('Tür')
                        ->attribute('faturalar.tur')
                        ->options(collect(FaturaTuru::uiNihaiTurler())->mapWithKeys(fn (FaturaTuru $t) => [$t->value => $t->etiket()])),
                ] : []),
                SelectFilter::make('isletme_proje_id')
                    ->label('Proje')
                    ->attribute('faturalar.isletme_proje_id')
                    ->options(fn (): array => IsletmeProjesi::query()
                        ->secilebilir()
                        ->orderBy('ad')
                        ->get(['id', 'kod', 'ad'])
                        ->mapWithKeys(fn (IsletmeProjesi $proje): array => [$proje->id => $proje->kod.' — '.$proje->ad])
                        ->all())
                    ->searchable(),
            ])
            ->recordUrl(fn (Fatura $record): string => FaturaKaynagi::getUrl('view', ['record' => $record]))
            ->actions([
                TableAction::make('detay')
                    ->label('Detay')
                    ->icon('heroicon-m-eye')
                    ->url(fn (Fatura $record): string => FaturaKaynagi::getUrl('view', ['record' => $record])),
                TableAction::make('kopyala')
                    ->label('Kopyala')
                    ->icon('heroicon-o-document-duplicate')
                    ->iconButton()
                    ->color('warning')
                    ->tooltip('İptal faturayı taslak olarak kopyala')
                    ->visible(fn (Fatura $record): bool => $record->durum === FaturaDurumu::Iptal)
                    ->action(function (Fatura $record): void {
                        if (! MuhasebeFilamentErisimYardimcisi::muhasebeYetkisiVarMi(MuhasebeYetkiSablonlari::FATURA_GUNCELLE)) {
                            throw new \Illuminate\Auth\Access\AuthorizationException('Fatura kopyalama yetkiniz yok.');
                        }

                        app(FaturaKopyalamaServisi::class)->kopyala($record);
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Fatura taslak olarak kopyalandı')
                            ->send();
                    }),
            ])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    public static function faturaSorgusu(): Builder
    {
        $q = Fatura::query()
            ->select([
                'faturalar.id',
                'faturalar.firma_id',
                'faturalar.fatura_no',
                'faturalar.belge_no',
                'faturalar.tur',
                'faturalar.fatura_sinifi',
                'faturalar.durum',
                'faturalar.odeme_durumu',
                'faturalar.tarih',
                'faturalar.vade_tarihi',
                'faturalar.genel_toplam',
                'faturalar.acik_tutar',
                'faturalar.para_birimi',
                'faturalar.isletme_proje_id',
                'cariler.ad as cari_ad',
                'projeler.kod as proje_kodu',
                'projeler.ad as proje_ad',
            ])
            ->leftJoin('cariler', function (JoinClause $join): void {
                $join
                    ->on('cariler.id', '=', 'faturalar.cari_id')
                    ->on('cariler.firma_id', '=', 'faturalar.firma_id');
            })
            ->leftJoin('isletme_projeleri as projeler', function (JoinClause $join): void {
                $join
                    ->on('projeler.id', '=', 'faturalar.isletme_proje_id')
                    ->on('projeler.firma_id', '=', 'faturalar.firma_id');
            });
        $turler = static::faturaTurleri();
        $siniflar = static::faturaSiniflari();
        $dogrudanIadeTurleri = static::dogrudanIadeEdilenFaturaTurleri();
        $durumlar = static::faturaDurumlari();
        if (! empty($turler)) {
            $q->where(function (Builder $turSorgusu) use ($turler, $dogrudanIadeTurleri): void {
                $turSorgusu->whereIn('faturalar.tur', $turler);
                if ($dogrudanIadeTurleri !== []) {
                    $turSorgusu->orWhere(function (Builder $eskiIadeSorgusu) use ($dogrudanIadeTurleri): void {
                        $eskiIadeSorgusu
                            ->where('faturalar.durum', FaturaDurumu::Iade->value)
                            ->whereIn('faturalar.tur', $dogrudanIadeTurleri);
                    });
                }
            });
        }
        if (! empty($durumlar)) {
            $q->whereIn('faturalar.durum', $durumlar);
        }
        if ($siniflar !== []) {
            $q->where(function (Builder $sinifSorgusu) use ($siniflar, $turler): void {
                $sinifSorgusu->whereIn('faturalar.fatura_sinifi', $siniflar);
                if (array_intersect($turler, [FaturaTuru::Gider->value, FaturaTuru::GiderFaturasi->value]) !== []) {
                    $sinifSorgusu->orWhere(function (Builder $eskiGiderSorgusu): void {
                        $eskiGiderSorgusu
                            ->whereNull('faturalar.fatura_sinifi')
                            ->whereIn('faturalar.tur', [FaturaTuru::Gider->value, FaturaTuru::GiderFaturasi->value]);
                    });
                }
            });
        }

        return $q;
    }

    protected static function muhasebeSayfasiYetkiKodlari(): array
    {
        return [
            MuhasebeYetkiSablonlari::FATURA_GORUNTULE,
            MuhasebeYetkiSablonlari::FATURA_GUNCELLE,
            MuhasebeYetkiSablonlari::FATURA_SIL,
            MuhasebeYetkiSablonlari::FATURA_ONAY,
        ];
    }
}
